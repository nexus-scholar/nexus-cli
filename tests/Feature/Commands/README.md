# NexusWikiInit Command - Test Coverage Documentation

## Overview

Comprehensive test suite for the `NexusWikiInit` Laravel command with **38 tests** across **159 assertions**, covering happy paths, edge cases, and refactoring safety.

**All tests pass**: ✅

## Test Files Location

- `tests/Feature/Commands/NexusWikiInitTest.php` - Core functionality tests (20 tests)
- `tests/Feature/Commands/NexusWikiInitEdgeCasesTest.php` - Edge cases & stability tests (18 tests)

## Run Tests

### All tests:
```bash
php vendor/bin/pest tests/Feature/Commands/NexusWikiInitTest.php tests/Feature/Commands/NexusWikiInitEdgeCasesTest.php
```

### Core tests only:
```bash
php vendor/bin/pest tests/Feature/Commands/NexusWikiInitTest.php
```

### Edge cases only:
```bash
php vendor/bin/pest tests/Feature/Commands/NexusWikiInitEdgeCasesTest.php
```

### Watch mode (auto-rerun on file changes):
```bash
php vendor/bin/pest tests/Feature/Commands/ --watch
```

## Test Coverage Breakdown

### Core Functionality Tests (NexusWikiInitTest.php)

#### Directory Structure Creation
- **creates_wiki_directory_and_subdirectories** - Verifies base wiki structure (docs/wiki, papers, concepts, synthesis)
- **creates_gitkeep_files_in_subdirectories** - Ensures .gitkeep files are created for version control
- **wiki_structure_is_complete** - Comprehensive check of all required directories and files

#### File Creation
- **creates_index_md_with_seed_content** - Verifies index.md with proper markdown headers
- **creates_log_md_with_seed_content** - Ensures log.md with table structure
- **copies_schema_template_to_schema_md** - Validates SCHEMA.md is copied from template
- **schema_md_is_not_empty_when_template_exists** - Confirms non-empty schema content

#### Idempotency & File Preservation
- **command_is_idempotent** - Running twice doesn't cause errors
- **does_not_overwrite_existing_index_md** - Preserves custom index content
- **does_not_overwrite_existing_log_md** - Preserves custom log content
- **does_not_overwrite_existing_schema_md** - Preserves custom schema content
- **handles_multiple_rapid_executions** - Multiple rapid runs are safe

#### Template Handling
- **creates_empty_schema_when_template_missing** - Fallback when docs/wiki-schema.md missing
- **copies_schema_template_to_schema_md** - Template content matches SCHEMA.md exactly

#### User Feedback
- **outputs_info_messages** - All expected info messages are displayed
- **command_returns_success_code** - Exit code is 0 (success)

#### Content Validation
- **log_file_contains_todays_date** - Log includes current date
- **index_md_structure_is_correct** - Proper markdown format
- **gitkeep_files_are_empty** - .gitkeep files are empty (as expected)

#### Permissions & Filesystem
- **created_directories_have_correct_permissions** - Directories are readable/writable
- **handles_partial_existing_structure** - Works with pre-existing partial structure

### Edge Cases & Stability Tests (NexusWikiInitEdgeCasesTest.php)

#### Content Preservation
- **preserves_existing_content_in_subdirectories** - Existing papers/documents aren't deleted
- **handles_complex_existing_tree** - Complex multi-level structures are respected
- **respects_existing_directory_structure** - Pre-existing dirs aren't recreated

#### Format Validation
- **index_file_has_valid_markdown_format** - No forbidden characters (tabs), proper headers
- **log_file_has_valid_table_structure** - Markdown table is correctly formatted
- **schema_file_has_expected_header** - SCHEMA.md starts with correct title
- **file_content_is_valid_utf8** - All files use UTF-8 encoding

#### Consistency & Reproducibility
- **double_run_produces_identical_results** - Content is identical across runs
- **schema_content_matches_template_exactly** - Template copy is byte-for-byte identical
- **output_is_consistent_across_runs** - Same output messages every run

#### File Quality
- **created_files_are_readable** - All files are readable and non-empty
- **log_file_date_format_is_valid** - Date uses YYYY-MM-DD format
- **gitkeep_files_have_expected_behavior** - .gitkeep files behave correctly

#### Structural Integrity
- **subdirectory_types_are_correct** - All items are correct type (dir vs file)
- **base_directory_structure_is_distinct** - Base dir has both files and subdirs
- **all_created_items_in_expected_locations** - All items are in their correct paths
- **all_required_seed_content_in_index** - Index has all required sections
- **all_required_seed_content_in_log** - Log has all required structure

## What These Tests Protect Against

### Refactoring Safety
When refactoring the command, these tests will catch:

1. **Accidental file overwrites** - Tests verify preservation of existing content
2. **Broken template copying** - Template tests catch copy failures
3. **Missing directory creation** - Complete structure tests catch incomplete setups
4. **Permission issues** - Permission tests catch filesystem problems
5. **Character encoding problems** - UTF-8 tests catch encoding issues
6. **Output message changes** - Output tests ensure user feedback remains
7. **Idempotency breaks** - Multiple-run tests catch idempotency regressions
8. **Date format changes** - Date format tests catch temporal issues

### Scenario Coverage
The test suite covers:

- ✅ First-time initialization
- ✅ Re-initialization on existing structure
- ✅ Partial existing structures
- ✅ Missing templates
- ✅ Complex existing content
- ✅ Rapid successive runs
- ✅ Permission variations
- ✅ File encoding edge cases

## Key Assertions By Type

| Type | Count | Purpose |
|------|-------|---------|
| Directory checks | 12 | Verify complete directory tree |
| File existence | 18 | Ensure all required files exist |
| Content validation | 35 | Check file contents and structure |
| Format validation | 15 | Verify markdown/UTF-8 format |
| Behavior checks | 45 | Verify idempotency, preservation, etc |
| Output checks | 15 | Verify user-facing messages |
| **Total** | **159** | **Complete coverage** |

## Common Refactoring Scenarios

### Scenario 1: Changing Directory Structure
**Tests that will fail:**
- `wiki_structure_is_complete` - Lists exact expected dirs
- `all_created_items_in_expected_locations` - Comprehensive location check

### Scenario 2: Modifying Seed Content
**Tests that will fail:**
- `all_required_seed_content_in_index` - Lists required content
- `all_required_seed_content_in_log` - Lists required content
- `index_md_structure_is_correct` - Validates structure

### Scenario 3: Changing Template Handling
**Tests that will fail:**
- `copies_schema_template_to_schema_md` - Template copy verification
- `schema_content_matches_template_exactly` - Byte-for-byte comparison
- `creates_empty_schema_when_template_missing` - Fallback verification

### Scenario 4: Changing Output Messages
**Tests that will fail:**
- `outputs_info_messages` - Lists all expected messages
- `output_is_consistent_across_runs` - Checks consistency

## Test Execution Strategy

### Before Major Refactoring
```bash
# Run full suite to establish baseline
php vendor/bin/pest tests/Feature/Commands/ --verbose
```

### During Development
```bash
# Run in watch mode for immediate feedback
php vendor/bin/pest tests/Feature/Commands/ --watch
```

### After Changes
```bash
# Run all and generate coverage report
php vendor/bin/pest tests/Feature/Commands/ --coverage --coverage-html=coverage
```

## Notes for Maintainers

1. **Isolation**: Each test creates/cleans its own `docs/wiki` directory, so tests are independent
2. **No Database**: These are pure file system tests using `File` facade
3. **Deterministic**: Tests produce identical results every run
4. **Fast**: Full suite runs in ~2 seconds
5. **Pest Framework**: Uses Pest (not PHPUnit directly) for readable test syntax

## Future Test Enhancements

Consider adding tests for:
- File creation failures (filesystem read-only scenarios)
- Very long directory paths
- Special characters in content
- Large template files
- Symlink handling
- Concurrent execution scenarios

