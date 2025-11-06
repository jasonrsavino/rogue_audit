# Rogue Audit

A Drupal maintenance module that identifies and optionally removes orphaned database tables from your Drupal installation. This module helps keep your database clean by detecting tables that are no longer needed after module uninstallations, field deletions, or Drupal 7 to 8+ upgrades.

## Features

- **Scan for orphaned tables**: Identify database tables that are no longer declared by installed modules
- **Field storage cleanup**: Detect orphaned field storage tables from deleted or misconfigured fields
- **Drupal 7 migration cleanup**: Find leftover `field_deleted_*` tables from D7 upgrades
- **Safe removal**: Drop orphaned tables with confirmation and dry-run options
- **Flexible filtering**: Include or exclude specific tables using pattern matching
- **Drush integration**: Command-line interface for automated maintenance workflows

## Requirements

- Drupal 10 or 11
- Drush (for command-line functionality)

## Installation

### Option 1: Composer Installation (Recommended)

Add this module to your project's `composer.json` file:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/jasonrsavino/rogue_audit.git"
        }
    ],
    "require": {
        "drupal/rogue_audit": "dev-main"
    }
}
```

Then run:
```bash
composer require drupal/rogue_audit:dev-main
drush en rogue_audit
```

### Option 2: Manual Installation

1. Download or clone this module to your `modules/custom/` directory
2. Enable the module: `drush en rogue_audit`

## Usage

### Scanning for Rogue Tables

To identify potentially orphaned tables:

```bash
drush rogue:scan
# or use the alias:
drush rscan
```

This will display a table showing:
- **Table name**: The database table name
- **Reason**: Why the table is considered orphaned

Example output:
```
Table                           Reason
field_deleted_field_old_image  D7 field_deleted_* leftover
node__field_removed_text       Orphan field storage (no matching field.storage config)
old_module_data                Not declared by any installed module schema
```

### Cleaning Up Rogue Tables

⚠️ **Always take a database backup before dropping tables!**

#### Drop all detected rogue tables:
```bash
drush rogue:clean --all
```

#### Drop specific tables by name:
```bash
drush rogue:clean --tables="field_deleted_*,old_module_*"
```

#### Drop all except certain tables:
```bash
drush rogue:clean --all --ignore="important_table,*_backup"
```

#### Dry run (see what would be dropped without actually dropping):
```bash
drush rogue:clean --all --dry-run
```

### Command Options

- `--all`: Drop all detected rogue tables
- `--tables=`: Comma-separated list of table names or patterns to include
- `--ignore=`: Comma-separated list of table names or patterns to exclude
- `--dry-run`: Show what would be dropped without making changes

Pattern matching supports wildcards (`*`) for flexible table selection.

## Detection Logic

The module identifies rogue tables using three criteria:

1. **Module Schema Tables**: Tables not declared by any installed module's `hook_schema()`
2. **Field Storage Tables**: Field tables (containing `__`) that don't match current field storage definitions
3. **Drupal 7 Leftovers**: Tables starting with `field_deleted_` from D7 upgrades

## Safety Features

- **Confirmation prompts**: Interactive confirmation before dropping tables
- **Dry-run mode**: Preview changes without executing them
- **Detailed reporting**: Clear reasons for why each table is flagged
- **Pattern-based exclusions**: Protect specific tables from accidental removal

## Common Use Cases

### After Module Uninstallation
Some modules may leave behind database tables after uninstallation:
```bash
drush rogue:scan
drush rogue:clean --tables="old_module_*" --dry-run
```

### Field Cleanup
After deleting fields or field storage configurations:
```bash
drush rogue:scan
drush rogue:clean --tables="*__field_old_*"
```

### Post-Migration Cleanup
After upgrading from Drupal 7:
```bash
drush rogue:scan
drush rogue:clean --tables="field_deleted_*"
```

## Troubleshooting

### False Positives
If a table is incorrectly identified as rogue:
1. Verify the module declaring the table is enabled
2. Check if the module properly implements `hook_schema()`
3. Use `--ignore` to exclude the table from cleanup

### Missing Tables
If expected rogue tables aren't detected:
1. Ensure the module declaring them is actually uninstalled
2. Check if field storage configurations still exist
3. Verify database connectivity and permissions

## Development

### Service Architecture
- `RogueScanner`: Core service for table detection and removal
- `RogueCommands`: Drush command interface

### Extending the Module
The `RogueScanner` service can be used programmatically:

```php
$scanner = \Drupal::service('rogue_audit.scanner');
$rogues = $scanner->findRogues();
foreach ($rogues as $rogue) {
  // Process $rogue['table'] and $rogue['reason']
}
```

## License

This module follows Drupal's licensing. See LICENSE.txt for details.

## Support

For issues, feature requests, or contributions, please use the project's issue queue.
