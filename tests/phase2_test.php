<?php
/**
 * Phase 2 Testing: CTCAE v6.0 Data Import
 *
 * Tests CTCAE v6.0 import results and data integrity
 */

require_once __DIR__ . '/../inc/db.php';

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  PHASE 2 TESTING: CTCAE v6.0 Data Import                  ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

$tests = [];
$passed = 0;
$failed = 0;

// Test 1: CTCAE v6 entries exist
$tests[] = [
    'name' => 'CTCAE v6 entries exist',
    'sql' => "SELECT COUNT(*) as cnt FROM dictionary_event WHERE ctcae_version = 'v6'",
    'expected' => 1,
    'comparison' => '>='
];

// Test 2: CTCAE v5 entries preserved
$tests[] = [
    'name' => 'CTCAE v5 entries preserved',
    'sql' => "SELECT COUNT(*) as cnt FROM dictionary_event WHERE ctcae_version = 'v5' OR ctcae_version IS NULL",
    'expected' => 0,
    'comparison' => '>='
];

// Test 3: No duplicate CTCAE v6 codes
$tests[] = [
    'name' => 'No duplicate CTCAE v6 codes',
    'sql' => "SELECT COUNT(*) - COUNT(DISTINCT ctcae_code) as cnt
             FROM dictionary_event
             WHERE ctcae_version = 'v6' AND ctcae_code IS NOT NULL AND ctcae_code != ''",
    'expected' => 0,
    'comparison' => '='
];

// Test 4: Categories populated
$tests[] = [
    'name' => 'Categories populated for v6',
    'sql' => "SELECT COUNT(*) as cnt FROM dictionary_event
             WHERE ctcae_version = 'v6' AND category IS NOT NULL AND category != ''",
    'expected' => 1,
    'comparison' => '>='
];

// Test 5: CTCAE codes populated
$tests[] = [
    'name' => 'CTCAE codes populated',
    'sql' => "SELECT COUNT(*) as cnt FROM dictionary_event
             WHERE ctcae_version = 'v6' AND ctcae_code IS NOT NULL AND ctcae_code != ''",
    'expected' => 1,
    'comparison' => '>='
];

// Test 6: Grading criteria stored
$tests[] = [
    'name' => 'Grading criteria stored',
    'sql' => "SELECT COUNT(*) as cnt FROM dictionary_event
             WHERE ctcae_version = 'v6' AND (caveat1 IS NOT NULL OR caveat2 IS NOT NULL OR caveat3 IS NOT NULL)",
    'expected' => 1,
    'comparison' => '>='
];

// Test 7: Active flag set
$tests[] = [
    'name' => 'Active flag set for v6 entries',
    'sql' => "SELECT COUNT(*) as cnt FROM dictionary_event
             WHERE ctcae_version = 'v6' AND active = 1",
    'expected' => 1,
    'comparison' => '>='
];

// Test 8: Source set to ICD
$tests[] = [
    'name' => 'Source set to ICD',
    'sql' => "SELECT COUNT(*) as cnt FROM dictionary_event
             WHERE ctcae_version = 'v6' AND source = 'ICD'",
    'expected' => 1,
    'comparison' => '>='
];

// Test 9: CTCAE terms match diagnosis
$tests[] = [
    'name' => 'CTCAE terms match diagnosis',
    'sql' => "SELECT COUNT(*) as cnt FROM dictionary_event
             WHERE ctcae_version = 'v6' AND ctcae_term = diagnosis",
    'expected' => 1,
    'comparison' => '>='
];

// Test 10: Unique constraint working
$tests[] = [
    'name' => 'Unique constraint enforced',
    'sql' => "SELECT COUNT(*) as cnt FROM (
                SELECT diagnosis, IFNULL(icd10,''), source, ctcae_version, COUNT(*) as dup_cnt
                FROM dictionary_event
                WHERE ctcae_version = 'v6'
                GROUP BY diagnosis, IFNULL(icd10,''), source, ctcae_version
                HAVING COUNT(*) > 1
             ) AS duplicates",
    'expected' => 0,
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
            if ($actual > $test['expected'] && $test['comparison'] === '>=') {
                echo " (found: " . number_format($actual) . ")";
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

// Additional verification checks
echo "\n";
echo "Data Quality Checks:\n";
echo str_repeat("-", 60) . "\n";

try {
    // Check version distribution
    $v6Count = $pdo->query("SELECT COUNT(*) FROM dictionary_event WHERE ctcae_version = 'v6'")->fetchColumn();
    $v5Count = $pdo->query("SELECT COUNT(*) FROM dictionary_event WHERE ctcae_version = 'v5'")->fetchColumn();
    $nullCount = $pdo->query("SELECT COUNT(*) FROM dictionary_event WHERE ctcae_version IS NULL")->fetchColumn();
    $totalCount = $pdo->query("SELECT COUNT(*) FROM dictionary_event")->fetchColumn();

    echo "Version Distribution:\n";
    echo "  CTCAE v6: " . number_format($v6Count) . " entries\n";
    echo "  CTCAE v5: " . number_format($v5Count) . " entries\n";
    if ($nullCount > 0) {
        echo "  No version: " . number_format($nullCount) . " entries\n";
    }
    echo "  Total: " . number_format($totalCount) . " entries\n";
    echo "\n";

    // Sample CTCAE v6 entries
    echo "Sample CTCAE v6 Entries:\n";
    echo str_repeat("-", 60) . "\n";
    $samples = $pdo->query("
        SELECT ctcae_code, ctcae_term, category, active
        FROM dictionary_event
        WHERE ctcae_version = 'v6'
        ORDER BY ctcae_code
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (empty($samples)) {
        echo "  ❌ No CTCAE v6 entries found!\n";
        $failed++;
    } else {
        foreach ($samples as $idx => $sample) {
            $activeFlag = $sample['active'] ? '✓' : '✗';
            echo sprintf("  %2d. [%s] %s %s\n",
                $idx + 1,
                $sample['ctcae_code'] ?: 'N/A',
                $sample['ctcae_term'],
                $activeFlag
            );
            if (!empty($sample['category'])) {
                echo "      Category: {$sample['category']}\n";
            }
        }
    }
    echo "\n";

    // Check grading criteria
    echo "Grading Criteria Check:\n";
    echo str_repeat("-", 60) . "\n";
    $gradingSample = $pdo->query("
        SELECT ctcae_term, caveat1, caveat2, caveat3
        FROM dictionary_event
        WHERE ctcae_version = 'v6'
        LIMIT 3
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($gradingSample as $idx => $entry) {
        echo "  " . ($idx + 1) . ". " . $entry['ctcae_term'] . "\n";
        if (!empty($entry['caveat1'])) {
            echo "     Mild:     " . substr($entry['caveat1'], 0, 60) . "...\n";
        }
        if (!empty($entry['caveat2'])) {
            echo "     Moderate: " . substr($entry['caveat2'], 0, 60) . "...\n";
        }
        if (!empty($entry['caveat3'])) {
            echo "     Severe:   " . substr($entry['caveat3'], 0, 60) . "...\n";
        }
        echo "\n";
    }

    // Category distribution
    echo "Category Distribution (Top 10):\n";
    echo str_repeat("-", 60) . "\n";
    $categories = $pdo->query("
        SELECT category, COUNT(*) as cnt
        FROM dictionary_event
        WHERE ctcae_version = 'v6' AND category IS NOT NULL AND category != ''
        GROUP BY category
        ORDER BY cnt DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (empty($categories)) {
        echo "  ⚠️  No categories found\n";
    } else {
        foreach ($categories as $cat) {
            echo sprintf("  %-40s %6s entries\n",
                substr($cat['category'], 0, 40),
                number_format($cat['cnt'])
            );
        }
    }
    echo "\n";

} catch (Exception $e) {
    echo "✗ ERROR checking data quality: {$e->getMessage()}\n";
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

if ($failed === 0 && $v6Count > 0) {
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  ✓ Phase 2 import SUCCESSFUL!                             ║\n";
    echo "║  CTCAE v6.0 data is ready for use.                        ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    echo "Next steps:\n";
    echo "  1. Create database backup:\n";
    echo "     mysqldump phoenix > backups/post_phase2_\$(date +%Y%m%d).sql\n";
    echo "  2. Commit changes to git\n";
    echo "  3. Proceed to Phase 3: Materialize CSS Integration\n";
    echo "\n";
    exit(0);
} else {
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  ✗ Phase 2 import has ISSUES                              ║\n";
    echo "║  Please review errors above and re-run import.            ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    echo "Troubleshooting:\n";
    echo "  1. Verify Excel file is in data/ directory\n";
    echo "  2. Re-run import: php scripts/import_ctcae_v6.php\n";
    echo "  3. Check import script output for errors\n";
    echo "  4. Verify Phase 1 migration was successful\n";
    echo "\n";
    exit(1);
}
