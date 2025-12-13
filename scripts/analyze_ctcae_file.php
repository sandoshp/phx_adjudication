#!/usr/bin/env php
<?php
/**
 * Analyze CTCAE Excel File Structure
 *
 * This script reads the Excel file and shows all column headers
 * to help us understand the file format.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$xlsxFile = __DIR__ . '/../data/CTCAE v6.0 Final Clean-Tracked-Mapping_w_OS_July2025.xlsx';

if (!file_exists($xlsxFile)) {
    echo "ERROR: File not found at: $xlsxFile\n";
    exit(1);
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  CTCAE File Structure Analyzer                            ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

try {
    $spreadsheet = IOFactory::load($xlsxFile);
    $worksheet = $spreadsheet->getActiveSheet();

    echo "Sheet Name: " . $worksheet->getTitle() . "\n";
    echo "Total Rows: " . $worksheet->getHighestRow() . "\n";
    echo "Total Columns: " . $worksheet->getHighestColumn() . "\n";
    echo "\n";

    // Read first 5 rows to understand structure
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  Column Headers (Row 1)                                   ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n";
    echo "\n";

    $highestColumn = $worksheet->getHighestColumn();
    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

    $headers = [];
    for ($col = 1; $col <= $highestColumnIndex; $col++) {
        $cellValue = $worksheet->getCellByColumnAndRow($col, 1)->getValue();
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $headers[$col] = trim((string)$cellValue);

        if (!empty($headers[$col])) {
            echo sprintf("  Column %2s (%-3d): %s\n", $columnLetter, $col, $headers[$col]);
        }
    }

    echo "\n";
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  Sample Data (Rows 2-6)                                   ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n";
    echo "\n";

    for ($row = 2; $row <= min(6, $worksheet->getHighestRow()); $row++) {
        echo "Row $row:\n";
        for ($col = 1; $col <= min(12, $highestColumnIndex); $col++) {
            $cellValue = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
            $header = $headers[$col] ?? "Col$col";

            if (!empty($cellValue)) {
                $truncated = strlen($cellValue) > 60 ? substr($cellValue, 0, 60) . '...' : $cellValue;
                echo sprintf("  %-25s: %s\n", $header, $truncated);
            }
        }
        echo "\n";
    }

    // Check if there are multiple sheets
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  Available Sheets                                          ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n";
    echo "\n";

    $sheetNames = $spreadsheet->getSheetNames();
    foreach ($sheetNames as $idx => $name) {
        echo sprintf("  Sheet %d: %s\n", $idx + 1, $name);

        // Get row count for each sheet
        $sheet = $spreadsheet->getSheet($idx);
        echo sprintf("           Rows: %d, Columns: %s\n",
            $sheet->getHighestRow(),
            $sheet->getHighestColumn()
        );
    }

    echo "\n";
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  Analysis Complete                                         ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    echo "Next steps:\n";
    echo "1. Review the column headers above\n";
    echo "2. Check if this is the correct file for CTCAE v6.0 import\n";
    echo "3. If it's a mapping file, we may need to adapt the import script\n";
    echo "4. Or locate the actual CTCAE v6.0 term database file\n";
    echo "\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
