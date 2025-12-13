#!/usr/bin/env php
<?php
/**
 * CTCAE v6.0 Import Script - Custom for "CTCAE v6.0 Clean Copy" Sheet
 *
 * This script imports from the specific Excel file format with multiple sheets.
 * Uses Sheet 2: "CTCAE v6.0 Clean Copy"
 *
 * Each CTCAE term has multiple rows (Definition, Grade 1-5)
 * We need to group them and extract grading criteria.
 *
 * Usage: php scripts/import_ctcae_v6_custom.php [--dry-run] [--verbose]
 */

// Parse command line options
$options = getopt('', ['dry-run', 'verbose', 'help']);
$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);
$showHelp = isset($options['help']);

if ($showHelp) {
    echo "Usage: php scripts/import_ctcae_v6_custom.php [options]\n\n";
    echo "Options:\n";
    echo "  --dry-run    Preview import without committing to database\n";
    echo "  --verbose    Show detailed output\n";
    echo "  --help       Show this help message\n";
    exit(0);
}

require_once __DIR__ . '/../inc/db.php';

// Check if Composer autoloader exists
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    echo "ERROR: Composer dependencies not installed.\n";
    echo "Please run: composer install\n";
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$xlsxFile = __DIR__ . '/../data/CTCAE v6.0 Final Clean-Tracked-Mapping_w_OS_July2025.xlsx';

// Banner
echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  CTCAE v6.0 Import Script (Custom Format)                 ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

if ($dryRun) {
    echo "⚠️  DRY RUN MODE - No changes will be committed\n\n";
}

// Check if file exists
if (!file_exists($xlsxFile)) {
    echo "❌ ERROR: CTCAE v6.0 file not found at:\n";
    echo "   $xlsxFile\n\n";
    exit(1);
}

echo "📁 File found: " . basename($xlsxFile) . "\n";
echo "📊 File size: " . number_format(filesize($xlsxFile)) . " bytes\n\n";

try {
    // Load spreadsheet
    echo "📖 Loading Excel file...\n";
    $spreadsheet = IOFactory::load($xlsxFile);

    // Get the "CTCAE v6.0 Clean Copy" sheet (index 1, second sheet)
    $sheetIndex = 1; // Sheet 2
    $worksheet = $spreadsheet->getSheet($sheetIndex);
    $sheetName = $worksheet->getTitle();

    echo "   Using Sheet: $sheetName\n";
    echo "   Rows: " . number_format($worksheet->getHighestRow()) . "\n";
    echo "   Columns: " . $worksheet->getHighestColumn() . "\n\n";

    // Read headers from row 1
    echo "🔍 Reading column structure...\n";
    $headers = [];
    $highestColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($worksheet->getHighestColumn());

    for ($col = 1; $col <= $highestColumn; $col++) {
        $headers[$col] = trim((string)$worksheet->getCellByColumnAndRow($col, 1)->getValue());
        if ($verbose && !empty($headers[$col])) {
            echo "   Column $col: {$headers[$col]}\n";
        }
    }
    echo "\n";

    // Expected columns (adjust based on actual file)
    // We'll read all columns dynamically
    $codeCol = 1;      // MedDRA Code
    $socCol = 2;       // SOC
    $termCol = 3;      // Term
    $gradeTypeCol = 4; // Grade/Definition
    $descCol = 5;      // Description

    if (!$dryRun) {
        $pdo->beginTransaction();
    }

    $insertStmt = null;
    if (!$dryRun) {
        $insertStmt = $pdo->prepare("
            INSERT INTO dictionary_event
            (diagnosis, category, ctcae_term, ctcae_version, ctcae_code, source,
             caveat1, outcome1, caveat2, outcome2, caveat3, outcome3, active)
            VALUES (?, ?, ?, 'v6', ?, 'ICD', ?, ?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
                category = VALUES(category),
                ctcae_code = VALUES(ctcae_code),
                caveat1 = VALUES(caveat1),
                outcome1 = VALUES(outcome1),
                caveat2 = VALUES(caveat2),
                outcome2 = VALUES(outcome2),
                caveat3 = VALUES(caveat3),
                outcome3 = VALUES(outcome3),
                updated_at = CURRENT_TIMESTAMP
        ");
    }

    // Group rows by MedDRA code + term
    echo "📥 Processing data...\n";
    $terms = [];
    $currentTerm = null;
    $stats = ['imported' => 0, 'skipped' => 0, 'errors' => 0];
    $sampleEntries = [];

    $highestRow = $worksheet->getHighestRow();

    // Start from row 2 (skip header)
    for ($row = 2; $row <= $highestRow; $row++) {
        $code = trim((string)$worksheet->getCellByColumnAndRow($codeCol, $row)->getValue());
        $soc = trim((string)$worksheet->getCellByColumnAndRow($socCol, $row)->getValue());
        $term = trim((string)$worksheet->getCellByColumnAndRow($termCol, $row)->getValue());
        $gradeType = trim((string)$worksheet->getCellByColumnAndRow($gradeTypeCol, $row)->getValue());
        $description = trim((string)$worksheet->getCellByColumnAndRow($descCol, $row)->getValue());

        // Skip empty rows
        if (empty($code) || empty($term)) {
            $stats['skipped']++;
            continue;
        }

        // Create unique key for this term
        $termKey = $code . '|' . $term;

        // Initialize term if not exists
        if (!isset($terms[$termKey])) {
            $terms[$termKey] = [
                'code' => $code,
                'soc' => $soc,
                'term' => $term,
                'definition' => '',
                'grade1' => '',
                'grade2' => '',
                'grade3' => '',
                'grade4' => '',
                'grade5' => ''
            ];
        }

        // Store data based on grade type
        $gradeTypeLower = strtolower($gradeType);
        if (stripos($gradeTypeLower, 'definition') !== false) {
            $terms[$termKey]['definition'] = $description;
        } elseif (stripos($gradeTypeLower, 'grade 1') !== false) {
            $terms[$termKey]['grade1'] = $description;
        } elseif (stripos($gradeTypeLower, 'grade 2') !== false) {
            $terms[$termKey]['grade2'] = $description;
        } elseif (stripos($gradeTypeLower, 'grade 3') !== false) {
            $terms[$termKey]['grade3'] = $description;
        } elseif (stripos($gradeTypeLower, 'grade 4') !== false) {
            $terms[$termKey]['grade4'] = $description;
        } elseif (stripos($gradeTypeLower, 'grade 5') !== false) {
            $terms[$termKey]['grade5'] = $description;
        }

        // Progress indicator
        if ($row % 100 === 0) {
            $progress = number_format(($row / $highestRow) * 100, 1);
            echo "   Progress: $progress% ($row / $highestRow rows processed)\n";
        }
    }

    echo "\n📊 Grouped into " . count($terms) . " unique CTCAE terms\n\n";
    echo "💾 Importing to database...\n";

    // Now import the grouped terms
    foreach ($terms as $termKey => $data) {
        // Prepare grading criteria
        $caveat1 = !empty($data['grade1']) ? "Grade 1: {$data['grade1']}" : "Grade 1: Mild";
        $outcome1 = "Mild";

        $caveat2 = !empty($data['grade2']) ? "Grade 2: {$data['grade2']}" : "Grade 2: Moderate";
        $outcome2 = "Moderate";

        // Combine grades 3-5 for severe
        $severeParts = [];
        if (!empty($data['grade3'])) $severeParts[] = "Grade 3: {$data['grade3']}";
        if (!empty($data['grade4'])) $severeParts[] = "Grade 4: {$data['grade4']}";
        if (!empty($data['grade5'])) $severeParts[] = "Grade 5: {$data['grade5']}";
        $caveat3 = !empty($severeParts) ? implode(" | ", $severeParts) : "Grade 3-5: Severe";
        $outcome3 = "Severe";

        // Store sample entries
        if (count($sampleEntries) < 5) {
            $sampleEntries[] = [
                'code' => $data['code'],
                'term' => $data['term'],
                'category' => $data['soc']
            ];
        }

        // Insert or update
        if (!$dryRun) {
            try {
                $insertStmt->execute([
                    $data['term'],     // diagnosis
                    $data['soc'],      // category
                    $data['term'],     // ctcae_term
                    $data['code'],     // ctcae_code
                    $caveat1,          // caveat1
                    $outcome1,         // outcome1
                    $caveat2,          // caveat2
                    $outcome2,         // outcome2
                    $caveat3,          // caveat3
                    $outcome3          // outcome3
                ]);

                $stats['imported']++;
            } catch (PDOException $e) {
                $stats['errors']++;
                if ($verbose) {
                    echo "   ⚠️  Error importing {$data['term']}: {$e->getMessage()}\n";
                }
            }
        } else {
            $stats['imported']++;
        }

        // Progress for import
        if ($stats['imported'] % 50 === 0) {
            echo "   Imported: {$stats['imported']} terms\n";
        }
    }

    // Commit or rollback
    if (!$dryRun) {
        if ($stats['errors'] > 0) {
            echo "\n⚠️  Errors encountered. Rolling back...\n";
            $pdo->rollBack();
        } else {
            $pdo->commit();
            echo "\n✅ Transaction committed successfully\n";
        }
    }

    // Display results
    echo "\n";
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  Import Complete                                           ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    echo "📊 Statistics:\n";
    echo "   Imported:  " . number_format($stats['imported']) . " CTCAE terms\n";
    echo "   Skipped:   " . number_format($stats['skipped']) . " empty rows\n";
    echo "   Errors:    " . number_format($stats['errors']) . "\n";
    echo "\n";

    // Display sample entries
    if (!empty($sampleEntries)) {
        echo "📋 Sample entries imported:\n";
        foreach ($sampleEntries as $entry) {
            echo "   [{$entry['code']}] {$entry['term']}\n";
            echo "      Category: {$entry['category']}\n";
        }
        echo "\n";
    }

    // Verify import in database
    if (!$dryRun && $stats['errors'] === 0) {
        echo "🔍 Verifying import...\n";

        $v6Count = $pdo->query("SELECT COUNT(*) FROM dictionary_event WHERE ctcae_version = 'v6'")->fetchColumn();
        $v5Count = $pdo->query("SELECT COUNT(*) FROM dictionary_event WHERE ctcae_version = 'v5'")->fetchColumn();
        $totalCount = $pdo->query("SELECT COUNT(*) FROM dictionary_event")->fetchColumn();

        echo "   CTCAE v6 entries: " . number_format($v6Count) . "\n";
        echo "   CTCAE v5 entries: " . number_format($v5Count) . "\n";
        echo "   Total entries:    " . number_format($totalCount) . "\n";
        echo "\n";

        if ($v6Count > 0) {
            echo "✅ SUCCESS: CTCAE v6.0 data imported successfully!\n\n";
            echo "Next steps:\n";
            echo "  1. Run verification tests: php tests\\phase2_test.php\n";
            echo "  2. Review sample data in database\n";
            echo "  3. Proceed to Phase 3 if all tests pass\n";
            echo "\n";
            exit(0);
        } else {
            echo "❌ WARNING: No CTCAE v6 entries found in database\n\n";
            exit(1);
        }
    } elseif ($dryRun) {
        echo "ℹ️  Dry run complete - no changes made to database\n";
        echo "   Run without --dry-run to perform actual import\n\n";
        exit(0);
    } else {
        echo "❌ Import failed due to errors\n\n";
        exit(1);
    }

} catch (Exception $e) {
    if (!$dryRun && isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ FATAL ERROR: {$e->getMessage()}\n";
    echo "\nStack trace:\n{$e->getTraceAsString()}\n\n";
    exit(1);
}
