<?php
/**
 * Phase 3 Testing: Materialize CSS Integration
 *
 * Tests template files, CSS, and component structure
 */

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  PHASE 3 TESTING: Materialize CSS Integration             ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

$tests = [];
$passed = 0;
$failed = 0;

// Define required files
$requiredFiles = [
    'Header template' => __DIR__ . '/../inc/templates/header.php',
    'Footer template' => __DIR__ . '/../inc/templates/footer.php',
    'Dark theme CSS' => __DIR__ . '/../public/assets/css/theme-dark.css',
];

// Test 1-3: File existence and size
echo "File Existence Tests:\n";
echo str_repeat("-", 60) . "\n";

foreach ($requiredFiles as $description => $file) {
    echo sprintf("%-50s ", $description);

    if (file_exists($file)) {
        $size = filesize($file);
        if ($size > 500) { // Reasonable minimum size
            echo "✓ PASS (" . number_format($size) . " bytes)\n";
            $passed++;
        } else {
            echo "✗ FAIL (file too small: $size bytes)\n";
            $failed++;
        }
    } else {
        echo "✗ FAIL (not found)\n";
        $failed++;
    }
}

echo "\n";
echo "Content Verification Tests:\n";
echo str_repeat("-", 60) . "\n";

// Test 4: Check Materialize CSS in header
echo sprintf("%-50s ", "Materialize CSS linked in header");
$headerContent = file_get_contents($requiredFiles['Header template']);
if (strpos($headerContent, 'materialize') !== false) {
    echo "✓ PASS\n";
    $passed++;
} else {
    echo "✗ FAIL\n";
    $failed++;
}

// Test 5: Check Material Icons in header
echo sprintf("%-50s ", "Material Icons linked in header");
if (strpos($headerContent, 'Material+Icons') !== false || strpos($headerContent, 'Material Icons') !== false) {
    echo "✓ PASS\n";
    $passed++;
} else {
    echo "✗ FAIL\n";
    $failed++;
}

// Test 6: Check custom dark theme CSS linked
echo sprintf("%-50s ", "Dark theme CSS linked");
if (strpos($headerContent, 'theme-dark.css') !== false) {
    echo "✓ PASS\n";
    $passed++;
} else {
    echo "✗ FAIL\n";
    $failed++;
}

// Test 7: Check Materialize JS in footer
echo sprintf("%-50s ", "Materialize JS linked in footer");
$footerContent = file_get_contents($requiredFiles['Footer template']);
if (strpos($footerContent, 'materialize') !== false && strpos($footerContent, '.js') !== false) {
    echo "✓ PASS\n";
    $passed++;
} else {
    echo "✗ FAIL\n";
    $failed++;
}

// Test 8: Check component initialization in footer
echo sprintf("%-50s ", "Materialize components initialized");
if (strpos($footerContent, 'M.Sidenav.init') !== false) {
    echo "✓ PASS\n";
    $passed++;
} else {
    echo "✗ FAIL\n";
    $failed++;
}

// Test 9: Check navigation structure
echo sprintf("%-50s ", "Navigation structure present");
if (strpos($headerContent, '<nav') !== false && strpos($headerContent, 'sidenav') !== false) {
    echo "✓ PASS\n";
    $passed++;
} else {
    echo "✗ FAIL\n";
    $failed++;
}

// Test 10: Check responsive meta tag
echo sprintf("%-50s ", "Responsive viewport meta tag");
if (strpos($headerContent, 'viewport') !== false && strpos($headerContent, 'width=device-width') !== false) {
    echo "✓ PASS\n";
    $passed++;
} else {
    echo "✗ FAIL\n";
    $failed++;
}

// Test 11: Check dark theme CSS variables
echo sprintf("%-50s ", "Dark theme CSS variables defined");
$cssContent = file_get_contents($requiredFiles['Dark theme CSS']);
if (strpos($cssContent, '--bg-primary') !== false && strpos($cssContent, '--accent') !== false) {
    echo "✓ PASS\n";
    $passed++;
} else {
    echo "✗ FAIL\n";
    $failed++;
}

// Test 12: Check custom status badges in CSS
echo sprintf("%-50s ", "Custom status badge styles");
if (strpos($cssContent, '.status-badge') !== false) {
    echo "✓ PASS\n";
    $passed++;
} else {
    echo "✗ FAIL\n";
    $failed++;
}

// Test 13: Check for utility functions
echo sprintf("%-50s ", "Utility functions (showToast, etc.)");
if (strpos($footerContent, 'showToast') !== false && strpos($footerContent, 'showLoading') !== false) {
    echo "✓ PASS\n";
    $passed++;
} else {
    echo "✗ FAIL\n";
    $failed++;
}

// Test 14: Check footer structure
echo sprintf("%-50s ", "Footer structure present");
if (strpos($footerContent, '<footer') !== false && strpos($footerContent, 'page-footer') !== false) {
    echo "✓ PASS\n";
    $passed++;
} else {
    echo "✗ FAIL\n";
    $failed++;
}

// Test 15: Check responsive styles in CSS
echo sprintf("%-50s ", "Responsive media queries");
if (strpos($cssContent, '@media') !== false) {
    echo "✓ PASS\n";
    $passed++;
} else {
    echo "✗ FAIL\n";
    $failed++;
}

// Component coverage check
echo "\n";
echo "Component Coverage:\n";
echo str_repeat("-", 60) . "\n";

$components = [
    'Cards' => '.card',
    'Forms' => 'input[type',
    'Buttons' => '.btn',
    'Tables' => 'table',
    'Modals' => '.modal',
    'Tooltips' => '.tooltipped',
    'Dropdowns' => '.dropdown',
    'Collapsibles' => '.collapsible',
    'Tabs' => '.tabs',
    'Chips' => '.chip',
    'Badges' => '.badge',
];

foreach ($components as $name => $selector) {
    $found = strpos($cssContent, $selector) !== false;
    echo sprintf("  %-30s %s\n", $name, $found ? '✓' : '✗');
}

// Summary
echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  TEST RESULTS                                              ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Total Tests:  15\n";
echo "Passed:       " . $passed . " ✓\n";
echo "Failed:       " . $failed . " ✗\n";
echo "\n";

// CSS Statistics
$cssLines = count(file($requiredFiles['Dark theme CSS']));
$cssSize = filesize($requiredFiles['Dark theme CSS']);
echo "CSS Statistics:\n";
echo "  Lines:  " . number_format($cssLines) . "\n";
echo "  Size:   " . number_format($cssSize) . " bytes\n";
echo "\n";

if ($failed === 0) {
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  ✓ Phase 3 integration SUCCESSFUL!                        ║\n";
    echo "║  Materialize CSS templates are ready.                     ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    echo "Manual Testing Required:\n";
    echo "  1. Create a test page using the templates\n";
    echo "  2. Open in browser and verify:\n";
    echo "     - Materialize CSS loads correctly\n";
    echo "     - Navigation menu works (desktop & mobile)\n";
    echo "     - Dark theme colors display properly\n";
    echo "     - Form elements render correctly\n";
    echo "     - Responsive layout on mobile devices\n";
    echo "  3. Test Materialize components:\n";
    echo "     - Dropdowns\n";
    echo "     - Modals\n";
    echo "     - Tooltips\n";
    echo "     - Select dropdowns\n";
    echo "     - Date pickers\n";
    echo "\n";
    echo "Next steps:\n";
    echo "  1. Convert existing pages to use new templates\n";
    echo "  2. Test all pages in browser\n";
    echo "  3. Commit changes to git\n";
    echo "  4. Proceed to Phase 4: Security Hardening\n";
    echo "\n";
    exit(0);
} else {
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  ✗ Phase 3 integration has ISSUES                         ║\n";
    echo "║  Please review errors above and fix before proceeding.    ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    echo "Troubleshooting:\n";
    echo "  1. Ensure all template files are created\n";
    echo "  2. Check file permissions\n";
    echo "  3. Verify file contents match specifications\n";
    echo "  4. Review IMPROVEMENT_STRATEGY.md Phase 3 section\n";
    echo "\n";
    exit(1);
}
