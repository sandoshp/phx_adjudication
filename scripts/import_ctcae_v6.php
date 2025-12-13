#!/usr/bin/env php
<?php
/**
 * CTCAE v6.0 Import Script
 *
 * Imports CTCAE v6.0 data from Excel file into dictionary_event table
 *
 * Requirements:
 *   - PHP 8.0+
 *   - Composer dependencies installed (phpoffice/phpspreadsheet)
 *   - Phase 1 database migration completed
 *   - Excel file placed in data/ directory
 *
 * Usage: php scripts/import_ctcae_v6.php [options]
 *
 * Options:
 *   --dry-run    Preview import without committing to database
 *   --verbose    Show detailed output
 *   --help       Show this help message
 */

// Parse command line options
$options = getopt('', ['dry-run', 'verbose', 'help']);
$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);
$showHelp = isset($options['help']);

if ($showHelp) {
    echo file_get_contents(__FILE__);
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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// Configuration
$xlsxFile = __DIR__ . '/../data/CTCAE v6.0 Final Clean-Tracked-Mapping_w_OS_July2025.xlsx';
$expectedHeaders = [
    'CTCAE Term', 'MedDRA Term', 'Code', 'CTCAE Code',
    'Category', 'SOC', 'System Organ Class',
    'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5'
];

// Banner
echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  CTCAE v6.0 Import Script                                 ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

if ($dryRun) {
    echo "⚠️  DRY RUN MODE - No changes will be committed\n\n";
}

// Check if file exists
if (!file_exists($xlsxFile)) {
    echo "❌ ERROR: CTCAE v6.0 file not found at:\n";
    echo "   $xlsxFile\n\n";
    echo "Please place the Excel file in the data/ directory with the exact name:\n";
    echo "   CTCAE v6.0 Final Clean-Tracked-Mapping_w_OS_July2025.xlsx\n\n";
    exit(1);
}

echo "📁 File found: " . basename($xlsxFile) . "\n";
echo "📊 File size: " . number_format(filesize($xlsxFile)) . " bytes\n\n";

try {
    // Load spreadsheet
    echo "📖 Loading Excel file...\n";
    $spreadsheet = IOFactory::load($xlsxFile);
    $worksheet = $spreadsheet->getActiveSheet();
    $highestRow = $worksheet->getHighestRow();
    $highestColumn = $worksheet->getHighestColumn();
    $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

    echo "   Sheet: " . $worksheet->getTitle() . "\n";
    echo "   Rows: " . number_format($highestRow) . "\n";
    echo "   Columns: $highestColumn ($highestColumnIndex columns)\n\n";

    // Read header row
    echo "🔍 Analyzing headers...\n";
    $headerRow = 1;
    $headers = [];

    for ($col = 1; $col <= $highestColumnIndex; $col++) {
        $cellValue = $worksheet->getCellByColumnAndRow($col, $headerRow)->getValue();
        $headers[$col] = trim((string)$cellValue);
        if ($verbose && !empty($headers[$col])) {
            echo "   Column $col: {$headers[$col]}\n";
        }
    }

    // Auto-detect column mapping
    $colMap = [
        'CTCAE_TERM' => null,
        'CTCAE_CODE' => null,
        'CATEGORY'   => null,
        'GRADE_1'    => null,
        'GRADE_2'    => null,
        'GRADE_3'    => null,
        'GRADE_4'    => null,
        'GRADE_5'    => null,
    ];

    foreach ($headers as $colIndex => $header) {
        $headerLower = strtolower($header);

        if (stripos($header, 'CTCAE Term') !== false || stripos($header, 'MedDRA Term') !== false) {
            $colMap['CTCAE_TERM'] = $colIndex;
        } elseif (stripos($header, 'CTCAE Code') !== false || stripos($header, 'Code') !== false && !$colMap['CTCAE_CODE']) {
            $colMap['CTCAE_CODE'] = $colIndex;
        } elseif (stripos($header, 'Category') !== false || stripos($header, 'SOC') !== false || stripos($header, 'System Organ Class') !== false) {
            $colMap['CATEGORY'] = $colIndex;
        } elseif (stripos($header, 'Grade 1') !== false) {
            $colMap['GRADE_1'] = $colIndex;
        } elseif (stripos($header, 'Grade 2') !== false) {
            $colMap['GRADE_2'] = $colIndex;
        } elseif (stripos($header, 'Grade 3') !== false) {
            $colMap['GRADE_3'] = $colIndex;
        } elseif (stripos($header, 'Grade 4') !== false) {
            $colMap['GRADE_4'] = $colIndex;
        } elseif (stripos($header, 'Grade 5') !== false) {
            $colMap['GRADE_5'] = $colIndex;
        }
    }

    // Verify all required columns found
    $missingColumns = [];
    foreach ($colMap as $field => $colIndex) {
        if ($colIndex === null) {
            $missingColumns[] = $field;
        }
    }

    if (!empty($missingColumns)) {
        echo "\n❌ ERROR: Could not find required columns:\n";
        foreach ($missingColumns as $col) {
            echo "   - $col\n";
        }
        echo "\nAvailable headers:\n";
        foreach ($headers as $idx => $header) {
            if (!empty($header)) {
                echo "   Column $idx: $header\n";
            }
        }
        exit(1);
    }

    echo "\n✅ Column mapping detected:\n";
    foreach ($colMap as $field => $colIndex) {
        $headerName = $headers[$colIndex] ?? 'unknown';
        echo "   $field => Column $colIndex ($headerName)\n";
    }
    echo "\n";

    // Prepare database statements
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

    // Import data
    echo "📥 Importing data...\n";
    $stats = [
        'imported' => 0,
        'skipped' => 0,
        'errors' => 0,
        'updated' => 0
    ];

    $errorLog = [];
    $sampleEntries = [];

    for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
        // Read row data
        $ctcaeTerm = trim((string)$worksheet->getCellByColumnAndRow($colMap['CTCAE_TERM'], $row)->getValue());
        $ctcaeCode = trim((string)$worksheet->getCellByColumnAndRow($colMap['CTCAE_CODE'], $row)->getValue());
        $category  = trim((string)$worksheet->getCellByColumnAndRow($colMap['CATEGORY'], $row)->getValue());
        $grade1    = trim((string)$worksheet->getCellByColumnAndRow($colMap['GRADE_1'], $row)->getValue());
        $grade2    = trim((string)$worksheet->getCellByColumnAndRow($colMap['GRADE_2'], $row)->getValue());
        $grade3    = trim((string)$worksheet->getCellByColumnAndRow($colMap['GRADE_3'], $row)->getValue());
        $grade4    = trim((string)$worksheet->getCellByColumnAndRow($colMap['GRADE_4'], $row)->getValue());
        $grade5    = trim((string)$worksheet->getCellByColumnAndRow($colMap['GRADE_5'], $row)->getValue());

        // Skip empty rows
        if (empty($ctcaeTerm) || $ctcaeTerm === '-' || $ctcaeTerm === 'N/A') {
            $stats['skipped']++;
            continue;
        }

        // Prepare grading criteria
        $caveat1 = !empty($grade1) ? "Grade 1: $grade1" : "Grade 1: Mild";
        $outcome1 = "Mild";
        $caveat2 = !empty($grade2) ? "Grade 2: $grade2" : "Grade 2: Moderate";
        $outcome2 = "Moderate";

        // Combine grades 3-5 for severe category
        $severeCriteria = [];
        if (!empty($grade3)) $severeCriteria[] = "Grade 3: $grade3";
        if (!empty($grade4)) $severeCriteria[] = "Grade 4: $grade4";
        if (!empty($grade5)) $severeCriteria[] = "Grade 5: $grade5";
        $caveat3 = !empty($severeCriteria) ? implode(" | ", $severeCriteria) : "Grade 3-5: Severe";
        $outcome3 = "Severe";

        // Store sample entries for verification
        if (count($sampleEntries) < 5) {
            $sampleEntries[] = [
                'code' => $ctcaeCode,
                'term' => $ctcaeTerm,
                'category' => $category
            ];
        }

        // Insert or update
        if (!$dryRun) {
            try {
                $insertStmt->execute([
                    $ctcaeTerm,    // diagnosis
                    $category,     // category
                    $ctcaeTerm,    // ctcae_term
                    $ctcaeCode,    // ctcae_code
                    $caveat1,      // caveat1
                    $outcome1,     // outcome1
                    $caveat2,      // caveat2
                    $outcome2,     // outcome2
                    $caveat3,      // caveat3
                    $outcome3      // outcome3
                ]);

                $stats['imported']++;

                // Check if it was an update
                if ($insertStmt->rowCount() === 2) {
                    $stats['updated']++;
                }
            } catch (PDOException $e) {
                $stats['errors']++;
                $errorMsg = "Row $row: {$e->getMessage()}";
                $errorLog[] = $errorMsg;

                if (count($errorLog) <= 10) {
                    echo "   ⚠️  $errorMsg\n";
                }
            }
        } else {
            $stats['imported']++;
        }

        // Progress indicator
        if ($row % 100 === 0) {
            $progress = number_format(($row / $highestRow) * 100, 1);
            echo "   Progress: $progress% ($row / $highestRow rows)\n";
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
    echo "   Imported:  " . number_format($stats['imported']) . "\n";
    if ($stats['updated'] > 0) {
        echo "   Updated:   " . number_format($stats['updated']) . "\n";
    }
    echo "   Skipped:   " . number_format($stats['skipped']) . "\n";
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

    // Display error summary
    if (!empty($errorLog) && count($errorLog) > 10) {
        echo "⚠️  Total errors: " . count($errorLog) . " (showing first 10 above)\n\n";
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
            echo "  1. Run verification tests: php tests/phase2_test.php\n";
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
    if (!$dryRun && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ FATAL ERROR: {$e->getMessage()}\n";
    echo "\nStack trace:\n{$e->getTraceAsString()}\n\n";
    exit(1);
}
