<?php

namespace Drupal\rogue_audit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

final class RogueScanner {

  public function __construct(
    private readonly EntityFieldManagerInterface $efm,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly Connection $db
  ) {}

  /**
   * List all tables in the current connection.
   *
   * @return string[]
   */
  public function listAllTables(): array {
    // Portable list via Schema::findTables('%') per driver.
    // On MySQL, '%' matches any number of chars.
    $schema = $this->db->schema();
    return array_values($schema->findTables('%'));
  }

  /**
   * Tables that belong to installed modules per hook_schema().
   *
   * @return string[]
   */
  public function moduleOwnedTables(): array {
    $owned = [];
    foreach (array_keys($this->moduleHandler->getModuleList()) as $module) {
      // In D8+ call the module's implementation of hook_schema via the
      // ModuleHandler service. Older procedural helper drupal_get_schema_unprocessed()
      // is not available in modern Drupal.
      $schema = $this->moduleHandler->invoke($module, 'schema');
      foreach (array_keys($schema ?: []) as $table) {
        $owned[$table] = TRUE;
      }
    }
    return array_keys($owned);
  }

  /**
   * Tables expected by current field storage definitions.
   *
   * @return string[] List of SQL table names like node__field_x, node_revision__field_x...
   */
  public function expectedFieldTables(): array {
    $expected = [];
    // EntityFieldManager requires an entity type id for getFieldStorageDefinitions()
    // in modern Drupal. Iterate all entity types and collect SQL-backed field
    // storage definitions per-entity type.
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_definitions = $entity_type_manager->getDefinitions();
    foreach (array_keys($entity_definitions) as $entity_type_id) {
      try {
        $storages = $this->efm->getFieldStorageDefinitions($entity_type_id);
      }
      catch (\Throwable $e) {
        // Some entity types don't support field storage or throw when asked
        // for base field storage. Skip those.
        continue;
      }
      foreach ($storages as $storage) {
        $field = $storage->getName();
        // Current and revision tables.
        $expected[] = "{$entity_type_id}__{$field}";
        $expected[] = "{$entity_type_id}_revision__{$field}";
      }
    }
    return array_unique($expected);
  }

  /**
   * Compute candidates with reasons.
   *
   * @return array<int, array{table:string, reason:string}>
   */
  public function findRogues(): array {
    $all = $this->listAllTables();
    $owned = array_flip($this->moduleOwnedTables());
    $expectedField = array_flip($this->expectedFieldTables());

    $candidates = [];
    foreach ($all as $t) {
      $is_field_style = str_contains($t, '__'); // heuristic for field tables.
      $is_d7_leftover = str_starts_with($t, 'field_deleted_'); // D7 upgrade leftovers.

      if ($is_field_style && !isset($expectedField[$t])) {
        $candidates[] = ['table' => $t, 'reason' => 'Orphan field storage (no matching field.storage config)'];
        continue;
      }
      if (!isset($owned[$t]) && !$is_field_style) {
        $candidates[] = ['table' => $t, 'reason' => 'Not declared by any installed module schema'];
        continue;
      }
      if ($is_d7_leftover) {
        $candidates[] = ['table' => $t, 'reason' => 'D7 field_deleted_* leftover'];
      }
    }
    return $candidates;
  }

  /**
   * Drop a table safely.
   */
  public function dropTable(string $table): void {
    $schema = $this->db->schema();
    if ($schema->tableExists($table)) {
      $schema->dropTable($table);
    }
  }
}
