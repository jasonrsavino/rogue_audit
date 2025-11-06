<?php

namespace Drupal\rogue_audit\Commands;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drupal\rogue_audit\Service\RogueScanner;

final class RogueCommands extends DrushCommands {

  public function __construct(
    private readonly RogueScanner $scanner
  ) {
    parent::__construct();
  }

  /**
   * List suspected rogue tables.
   *
   * @command rogue:scan
   * @aliases rscan
   */
  public function scan() {
    $rows = $this->scanner->findRogues();
    if (!$rows) {
      $this->io()->success('No rogue tables detected.');
      return;
    }
    $this->io()->table(['Table', 'Reason'], $rows);
    $this->io()->note('Review carefully before cleaning. Consider taking a backup.');
  }

  /**
   * Drop rogue tables.
   *
   * @command rogue:clean
   * @option all Drop all detected candidates
   * @option tables CSV of table names or patterns to include
   * @option ignore CSV of table names or patterns to skip
   * @option dry-run Show what would be dropped but do nothing
   * @aliases rclean
   */
  public function clean($options = ['all' => FALSE, 'tables' => '', 'ignore' => '', 'dry-run' => FALSE]) {
    $candidates = $this->scanner->findRogues();
    if (!$candidates) {
      $this->io()->success('No candidates to drop.');
      return;
    }

    $include = $this->csv($options['tables']);
    $ignore = $this->csv($options['ignore']);
    $to_drop = [];
    foreach ($candidates as $row) {
      $t = $row['table'];
      if ($ignore && $this->matches($t, $ignore)) {
        continue;
      }
      if ($options['all'] || ($include && $this->matches($t, $include))) {
        $to_drop[] = $t;
      }
    }

    if (!$to_drop) {
      $this->io()->warning('Nothing selected for drop. Use --all or --tables=');
      return;
    }

    $this->io()->title('Drop plan');
    foreach ($to_drop as $t) {
      $this->io()->writeln(" - {$t}");
    }

    if (!empty($options['dry-run'])) {
      $this->io()->success('Dry run complete. No changes made.');
      return;
    }

    if (!$this->io()->confirm('Proceed with dropping the tables listed above?')) {
      $this->io()->warning('Aborted.');
      return;
    }

    foreach ($to_drop as $t) {
      $this->scanner->dropTable($t);
      $this->logger()->notice("Dropped %t", ['%t' => $t]);
    }
    $this->io()->success('Drop complete.');
  }

  private function csv(string $csv): array {
    $csv = trim($csv);
    return $csv === '' ? [] : array_map('trim', explode(',', $csv));
  }

  private function matches(string $table, array $patterns): bool {
    foreach ($patterns as $p) {
      // Simple glob-like match: convert '*' to regex '.*'.
      $rx = '/^' . str_replace('\*', '.*', preg_quote($p, '/')) . '$/i';
      if (preg_match($rx, $table)) {
        return TRUE;
      }
    }
    return FALSE;
  }


}
