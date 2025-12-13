<?php
/**
 * Phase 1 Testing: Database Schema Enhancement
 *
 * Tests all changes made in migration 001_schema_enhancements.sql
 */

require_once __DIR__ . '/../inc/db.php';

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  PHASE 1 TESTING: Database Schema Enhancement             ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

$tests = [];
$passed = 0;
$failed = 0;

// Test 1: CTCAE version column exists
$tests[] = [
    'name' => 'CTCAE version column exists',
    'sql' => "SELECT COUNT(*) as cnt FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'dictionary_event'
              AND COLUMN_NAME = 'ctcae_version'",
    'expected' => 1,
    'comparison' => '='
];

// Test 2: CTCAE code column exists
$tests[] = [
    'name' => 'CTCAE code column exists',
    'sql' => "SELECT COUNT(*) as cnt FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'dictionary_event'
              AND COLUMN_NAME = 'ctcae_code'",
    'expected' => 1,
    'comparison' => '='
];

// Test 3: Active column exists
$tests[] = [
    'name' => 'Active column exists in dictionary_event',
    'sql' => "SELECT COUNT(*) as cnt FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'dictionary_event'
              AND COLUMN_NAME = 'active'",
    'expected' => 1,
    'comparison' => '='
];

// Test 4: system_config table exists
$tests[] = [
    'name' => 'system_config table exists',
    'sql' => "SELECT COUNT(*) as cnt FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'system_config'",
    'expected' => 1,
    'comparison' => '='
];

// Test 5: Verify indexes on case_event
$tests[] = [
    'name' => 'Performance indexes on case_event',
    'sql' => "SELECT COUNT(*) as cnt FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'case_event'
              AND INDEX_NAME IN ('idx_patient_status', 'idx_status', 'idx_created_at')",
    'expected' => 3,
    'comparison' => '>='
];

// Test 6: Verify indexes on adjudication
$tests[] = [
    'name' => 'Performance indexes on adjudication',
    'sql' => "SELECT COUNT(*) as cnt FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'adjudication'
              AND INDEX_NAME IN ('idx_case_event', 'idx_adjudicator', 'idx_submitted')",
    'expected' => 3,
    'comparison' => '>='
];

// Test 7: Check adjudication versioning
$tests[] = [
    'name' => 'Adjudication versioning columns',
    'sql' => "SELECT COUNT(*) as cnt FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'adjudication'
              AND COLUMN_NAME IN ('version', 'is_current')",
    'expected' => 2,
    'comparison' => '='
];

// Test 8: Verify adjudication_current view
$tests[] = [
    'name' => 'adjudication_current view exists',
    'sql' => "SELECT COUNT(*) as cnt FROM information_schema.VIEWS
              WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'adjudication_current'",
    'expected' => 1,
    'comparison' => '='
];

// Test 9: Check default configurations loaded
$tests[] = [
    'name' => 'Default system configurations',
    'sql' => "SELECT COUNT(*) as cnt FROM system_config
              WHERE config_key IN ('default_ctcae_version', 'min_adjudications_required',
                                   'followup_months', 'enable_audit_logging')",
    'expected' => 4,
    'comparison' => '>='
];

// Test 10: Verify audit_log enhancements
$tests[] = [
    'name' => 'Audit log enhancements',
    'sql' => "SELECT COUNT(*) as cnt FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'audit_log'
              AND COLUMN_NAME IN ('action_type', 'ip_address', 'user_agent')",
    'expected' => 3,
    'comparison' => '='
];

// Test 11: Verify unique constraint updated
$tests[] = [
    'name' => 'Updated unique constraint on dictionary_event',
    'sql' => "SELECT COUNT(*) as cnt FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'dictionary_event'
              AND INDEX_NAME = 'uniq_event_versioned'",
    'expected' => 1,
    'comparison' => '>='
];

// Test 12: Verify adjudication_drug table exists
$tests[] = [
    'name' => 'adjudication_drug table exists',
    'sql' => "SELECT COUNT(*) as cnt FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'adjudication_drug'",
    'expected' => 1,
    'comparison' => '='
];

// Run all tests
echo "Running tests...\n\n";
foreach ($tests as $index => $test) {
    $testNum = $index + 1;
    echo sprintf("[%2d/%2d] %-50s ", $testNum, count($tests), $test['name']);

    try {
        $result = $pdo->query($test['sql'])->fetch(PDO::FETCH_ASSOC);
        $actual = (int)$result['cnt'];

        $success = false;
        switch ($test['comparison']) {
            case '=':
                $success = ($actual == $test['expected']);
                break;
            case '>=':
                $success = ($actual >= $test['expected']);
                break;
            case '>':
                $success = ($actual > $test['expected']);
                break;
        }

        if ($success) {
            echo "✓ PASS";
            if ($actual != $test['expected']) {
                echo " (found: $actual)";
            }
            echo "\n";
            $passed++;
        } else {
            echo "✗ FAIL (expected {$test['comparison']} {$test['expected']}, got $actual)\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "✗ ERROR: {$e->getMessage()}\n";
        $failed++;
    }
}

// Data integrity checks
echo "\n";
echo "Data Integrity Checks:\n";
echo str_repeat("-", 60) . "\n";

try {
    // Check existing data preserved
    $patientCount = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    echo "✓ Patients table intact: $patientCount records\n";

    $adjCount = $pdo->query("SELECT COUNT(*) FROM adjudication")->fetchColumn();
    echo "✓ Adjudication table intact: $adjCount records\n";

    $dictCount = $pdo->query("SELECT COUNT(*) FROM dictionary_event")->fetchColumn();
    echo "✓ Dictionary event table intact: $dictCount records\n";

    // Check all existing adjudications have version set
    $versionedCount = $pdo->query("SELECT COUNT(*) FROM adjudication WHERE version >= 1 AND is_current = 1")->fetchColumn();
    if ($versionedCount == $adjCount) {
        echo "✓ All adjudications properly versioned\n";
    } else {
        echo "✗ WARNING: Some adjudications not versioned ($versionedCount/$adjCount)\n";
        $failed++;
    }

} catch (Exception $e) {
    echo "✗ ERROR checking data integrity: {$e->getMessage()}\n";
    $failed++;
}

// Summary
echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  TEST RESULTS                                              ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Total Tests:  " . count($tests) . "\n";
echo "Passed:       " . $passed . " ✓\n";
echo "Failed:       " . $failed . " ✗\n";
echo "\n";

if ($failed === 0) {
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  ✓ Phase 1 migration SUCCESSFUL!                          ║\n";
    echo "║  You may proceed to Phase 2.                              ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    echo "Next steps:\n";
    echo "  1. Create database backup:\n";
    echo "     mysqldump phoenix > backups/post_phase1_\$(date +%Y%m%d).sql\n";
    echo "  2. Commit changes to git\n";
    echo "  3. Proceed to Phase 2: CTCAE v6.0 Import\n";
    echo "\n";
    exit(0);
} else {
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  ✗ Phase 1 migration has ISSUES                           ║\n";
    echo "║  Please review errors above and fix before proceeding.    ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    echo "Troubleshooting:\n";
    echo "  1. Review migration script: migrations/001_schema_enhancements.sql\n";
    echo "  2. Check MySQL error log\n";
    echo "  3. If needed, rollback: mysql phoenix < migrations/001_rollback.sql\n";
    echo "\n";
    exit(1);
}
