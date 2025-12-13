# PHOENIX Adjudication System - Step-wise Improvement Strategy

**Version:** 1.0
**Date:** 2025-12-13
**Tech Stack:** PHP 8.x, MySQL 8.x, Materialize CSS 1.0.0

---

## Overview

This document outlines a 12-phase improvement strategy for the PHOENIX Adjudication system. Each phase is independently testable and builds upon previous phases. The strategy maintains PHP/MySQL backend while migrating to Materialize CSS framework and adding CTCAE v6.0 support.

**Key Principles:**
- ✅ Each phase is fully tested before proceeding
- ✅ Backward compatibility maintained throughout
- ✅ Database migrations are reversible
- ✅ No breaking changes to existing functionality
- ✅ Progressive enhancement approach

---

## Pre-Phase: Environment Setup

### Objectives
- Establish testing framework
- Set up version control discipline
- Create development/staging/production environments

### Tasks

#### 1. Version Control Cleanup
```bash
# Remove duplicate files
git rm public/dashboard_org.php public/dashboard_V2.php
git rm public/patient_org.php public/patient_V2.php
git rm public/case_event_org.php
git rm api/adjudications_org.php api/adjudications_V2.php
git rm api/case_events_org.php api/case_events_v2.php
git rm api/patients_bak.php

# Keep only the latest versions, rename to canonical names
git mv public/dashboard_V3.php public/dashboard.php
git mv public/patient_V3.php public/patient.php
git mv public/case_event_V2.php public/case_event.php
```

#### 2. Configuration Management
Create `inc/config.template.php`:
```php
<?php
return [
    'db_host'      => getenv('DB_HOST') ?: 'localhost',
    'db_name'      => getenv('DB_NAME') ?: 'phoenix',
    'db_user'      => getenv('DB_USER') ?: 'phoenix_user',
    'db_pass'      => getenv('DB_PASS') ?: '',
    'db_charset'   => 'utf8mb4',
    'session_name' => 'phoenix_session',
    'debug'        => getenv('APP_ENV') !== 'production',
    'version'      => '1.0.0'
];
```

Create `.env.example`:
```
APP_ENV=development
DB_HOST=localhost
DB_NAME=phoenix
DB_USER=phoenix_user
DB_PASS=your_secure_password_here
```

#### 3. Testing Structure
```bash
mkdir -p tests/{unit,integration,fixtures}
mkdir -p docs/{api,user,technical}
mkdir -p backups/migrations
```

### Testing Checklist
- [ ] `.env` file created and not tracked in git
- [ ] `inc/config.php` loads from environment variables
- [ ] Application connects to database successfully
- [ ] All pages load without errors
- [ ] Login works with existing credentials

---

## PHASE 1: Database Schema Enhancement & CTCAE v6.0 Support

**Duration:** 2-3 days
**Risk Level:** Medium
**Rollback Strategy:** SQL rollback scripts provided

### Objectives
- Add CTCAE versioning support
- Add missing indexes for performance
- Add audit logging triggers
- Prepare for CTCAE v6.0 data

### Database Migration: `migrations/001_schema_enhancements.sql`

```sql
-- Migration 001: Schema Enhancements
-- Run date: 2025-12-13
-- Rollback file: migrations/001_rollback.sql

START TRANSACTION;

-- 1. Add CTCAE version tracking to dictionary_event
ALTER TABLE dictionary_event
  ADD COLUMN ctcae_version ENUM('v5', 'v6') NULL DEFAULT 'v5' AFTER ctcae_term,
  ADD COLUMN ctcae_code VARCHAR(32) NULL AFTER ctcae_version,
  ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER ctcae_code,
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- 2. Update unique constraint to include CTCAE version
ALTER TABLE dictionary_event
  DROP INDEX uniq_event,
  ADD UNIQUE KEY uniq_event_versioned (diagnosis, IFNULL(icd10,''), source, IFNULL(ctcae_version, 'v5'));

-- 3. Add performance indexes
ALTER TABLE case_event
  ADD INDEX idx_patient_status (patient_id, status),
  ADD INDEX idx_status (status),
  ADD INDEX idx_created_at (created_at);

ALTER TABLE adjudication
  ADD INDEX idx_case_event (case_event_id),
  ADD INDEX idx_adjudicator (adjudicator_id),
  ADD INDEX idx_submitted (submitted_at);

ALTER TABLE patients
  ADD INDEX idx_randomisation_date (randomisation_date),
  ADD INDEX idx_index_drug (index_drug_id);

ALTER TABLE patient_concomitant_drug
  ADD INDEX idx_patient (patient_id),
  ADD INDEX idx_drug (drug_id);

-- 4. Enhance audit_log table
ALTER TABLE audit_log
  ADD COLUMN action_type ENUM('CREATE', 'UPDATE', 'DELETE', 'VIEW') NOT NULL DEFAULT 'UPDATE' AFTER entity_id,
  ADD COLUMN ip_address VARCHAR(45) NULL AFTER user_id,
  ADD COLUMN user_agent TEXT NULL AFTER ip_address,
  ADD INDEX idx_entity (entity_type, entity_id),
  ADD INDEX idx_user (user_id, created_at),
  ADD INDEX idx_created (created_at);

-- 5. Add configuration table for system settings
CREATE TABLE IF NOT EXISTS system_config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  config_key VARCHAR(128) UNIQUE NOT NULL,
  config_value TEXT,
  config_type ENUM('string', 'integer', 'boolean', 'json') NOT NULL DEFAULT 'string',
  description TEXT,
  updated_by INT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default configurations
INSERT INTO system_config (config_key, config_value, config_type, description) VALUES
  ('default_ctcae_version', 'v5', 'string', 'Default CTCAE version for new events'),
  ('min_adjudications_required', '3', 'integer', 'Minimum adjudications before consensus'),
  ('followup_months', '3', 'integer', 'Default follow-up period in months'),
  ('enable_audit_logging', 'true', 'boolean', 'Enable comprehensive audit logging');

-- 6. Add version tracking to adjudications
ALTER TABLE adjudication
  ADD COLUMN version INT NOT NULL DEFAULT 1 AFTER id,
  ADD COLUMN is_current TINYINT(1) NOT NULL DEFAULT 1 AFTER version,
  ADD INDEX idx_version (case_event_id, adjudicator_id, is_current);

-- Update existing records
UPDATE adjudication SET is_current = 1;

-- 7. Drop old unique constraint and add new one allowing versions
ALTER TABLE adjudication
  DROP INDEX uniq_adj,
  ADD UNIQUE KEY uniq_adj_version (case_event_id, adjudicator_id, version);

-- Create a view for current adjudications only
CREATE OR REPLACE VIEW adjudication_current AS
  SELECT * FROM adjudication WHERE is_current = 1;

COMMIT;

-- Record migration
INSERT INTO system_config (config_key, config_value, config_type, description)
VALUES ('schema_version', '001', 'string', 'Current database schema version')
ON DUPLICATE KEY UPDATE config_value = '001', updated_at = CURRENT_TIMESTAMP;
```

### Rollback Script: `migrations/001_rollback.sql`

```sql
START TRANSACTION;

DROP VIEW IF EXISTS adjudication_current;

ALTER TABLE adjudication
  DROP INDEX uniq_adj_version,
  DROP COLUMN is_current,
  DROP COLUMN version,
  ADD UNIQUE KEY uniq_adj (case_event_id, adjudicator_id);

DROP TABLE IF EXISTS system_config;

ALTER TABLE audit_log
  DROP COLUMN user_agent,
  DROP COLUMN ip_address,
  DROP COLUMN action_type,
  DROP INDEX idx_created,
  DROP INDEX idx_user,
  DROP INDEX idx_entity;

ALTER TABLE patient_concomitant_drug
  DROP INDEX idx_drug,
  DROP INDEX idx_patient;

ALTER TABLE patients
  DROP INDEX idx_index_drug,
  DROP INDEX idx_randomisation_date;

ALTER TABLE adjudication
  DROP INDEX idx_submitted,
  DROP INDEX idx_adjudicator,
  DROP INDEX idx_case_event;

ALTER TABLE case_event
  DROP INDEX idx_created_at,
  DROP INDEX idx_status,
  DROP INDEX idx_patient_status;

ALTER TABLE dictionary_event
  DROP INDEX uniq_event_versioned,
  ADD UNIQUE KEY uniq_event (diagnosis, IFNULL(icd10,''), source),
  DROP COLUMN updated_at,
  DROP COLUMN active,
  DROP COLUMN ctcae_code,
  DROP COLUMN ctcae_version;

COMMIT;
```

### Testing Script: `tests/phase1_test.php`

```php
<?php
require_once __DIR__ . '/../inc/db.php';

echo "PHASE 1 TESTING: Database Schema Enhancement\n";
echo str_repeat("=", 60) . "\n\n";

$tests = [
    'Check CTCAE version column exists' =>
        "SELECT COUNT(*) as cnt FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = 'phoenix'
         AND TABLE_NAME = 'dictionary_event'
         AND COLUMN_NAME = 'ctcae_version'",

    'Check system_config table exists' =>
        "SELECT COUNT(*) as cnt FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = 'phoenix'
         AND TABLE_NAME = 'system_config'",

    'Verify indexes on case_event' =>
        "SELECT COUNT(*) as cnt FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = 'phoenix'
         AND TABLE_NAME = 'case_event'
         AND INDEX_NAME IN ('idx_patient_status', 'idx_status', 'idx_created_at')",

    'Check adjudication versioning' =>
        "SELECT COUNT(*) as cnt FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = 'phoenix'
         AND TABLE_NAME = 'adjudication'
         AND COLUMN_NAME IN ('version', 'is_current')",

    'Verify adjudication_current view' =>
        "SELECT COUNT(*) as cnt FROM information_schema.VIEWS
         WHERE TABLE_SCHEMA = 'phoenix'
         AND TABLE_NAME = 'adjudication_current'",

    'Check default configurations loaded' =>
        "SELECT COUNT(*) as cnt FROM system_config
         WHERE config_key IN ('default_ctcae_version', 'min_adjudications_required')"
];

$passed = 0;
$failed = 0;

foreach ($tests as $description => $sql) {
    try {
        $result = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        $expected = ($description === 'Verify indexes on case_event') ? 3 :
                   (($description === 'Check adjudication versioning') ? 2 :
                   (($description === 'Check default configurations loaded') ? 2 : 1));

        if ($result['cnt'] >= $expected) {
            echo "✓ PASS: $description\n";
            $passed++;
        } else {
            echo "✗ FAIL: $description (expected >= $expected, got {$result['cnt']})\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "✗ ERROR: $description - {$e->getMessage()}\n";
        $failed++;
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Results: $passed passed, $failed failed\n";
echo ($failed === 0) ? "✓ Phase 1 migration successful!\n" : "✗ Phase 1 has issues - review required\n";

exit($failed > 0 ? 1 : 0);
```

### Manual Testing Checklist

- [ ] Run migration: `mysql phoenix < migrations/001_schema_enhancements.sql`
- [ ] Run test script: `php tests/phase1_test.php`
- [ ] Verify all tests pass
- [ ] Check existing patients still load: `SELECT COUNT(*) FROM patients;`
- [ ] Check existing adjudications intact: `SELECT COUNT(*) FROM adjudication;`
- [ ] Test rollback: `mysql phoenix < migrations/001_rollback.sql`
- [ ] Re-apply migration for next phases
- [ ] Backup database: `mysqldump phoenix > backups/post_phase1_$(date +%Y%m%d).sql`

---

## PHASE 2: CTCAE v6.0 Data Import

**Duration:** 2 days
**Risk Level:** Low
**Dependencies:** Phase 1

### Objectives
- Parse CTCAE v6.0 Excel file
- Import into dictionary_event with v6 designation
- Create mapping utilities

### Create CTCAE Parser: `scripts/import_ctcae_v6.php`

```php
<?php
require_once __DIR__ . '/../inc/db.php';

/**
 * CTCAE v6.0 Import Script
 *
 * Requires: composer require phpoffice/phpspreadsheet
 * File: CTCAE v6.0 Final Clean-Tracked-Mapping_w_OS_July2025.xlsx
 */

// Check if Composer autoloader exists
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    die("ERROR: Please run 'composer install' first\n");
}

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$xlsxFile = __DIR__ . '/../data/CTCAE v6.0 Final Clean-Tracked-Mapping_w_OS_July2025.xlsx';

if (!file_exists($xlsxFile)) {
    die("ERROR: CTCAE v6.0 file not found at: $xlsxFile\n");
}

echo "CTCAE v6.0 Import Starting...\n";
echo str_repeat("=", 60) . "\n";

try {
    $spreadsheet = IOFactory::load($xlsxFile);
    $worksheet = $spreadsheet->getActiveSheet();

    // Get header row to map columns
    $headerRow = 1;
    $headers = [];
    foreach ($worksheet->getRowIterator($headerRow, $headerRow) as $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        $col = 0;
        foreach ($cellIterator as $cell) {
            $headers[$col] = trim($cell->getValue());
            $col++;
        }
    }

    echo "Headers found: " . implode(", ", array_filter($headers)) . "\n\n";

    // Expected column mapping (adjust based on actual file structure)
    $colMap = [
        'CTCAE_TERM'     => array_search('CTCAE Term', $headers) ?:
                           array_search('MedDRA Term', $headers) ?: 0,
        'CTCAE_CODE'     => array_search('CTCAE Code', $headers) ?:
                           array_search('Code', $headers) ?: 1,
        'CATEGORY'       => array_search('Category', $headers) ?:
                           array_search('SOC', $headers) ?: 2,
        'GRADE_1'        => array_search('Grade 1', $headers) ?: 3,
        'GRADE_2'        => array_search('Grade 2', $headers) ?: 4,
        'GRADE_3'        => array_search('Grade 3', $headers) ?: 5,
        'GRADE_4'        => array_search('Grade 4', $headers) ?: 6,
        'GRADE_5'        => array_search('Grade 5', $headers) ?: 7,
    ];

    echo "Column mapping:\n";
    foreach ($colMap as $field => $col) {
        echo "  $field => Column " . ($col + 1) . " (" . ($headers[$col] ?? 'unknown') . ")\n";
    }
    echo "\n";

    $pdo->beginTransaction();

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

    $imported = 0;
    $skipped = 0;
    $errors = 0;

    foreach ($worksheet->getRowIterator($headerRow + 1) as $row) {
        $rowIndex = $row->getRowIndex();
        $cells = [];

        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        $col = 0;
        foreach ($cellIterator as $cell) {
            $cells[$col] = $cell->getValue();
            $col++;
        }

        $ctcaeTerm = trim($cells[$colMap['CTCAE_TERM']] ?? '');
        $ctcaeCode = trim($cells[$colMap['CTCAE_CODE']] ?? '');
        $category  = trim($cells[$colMap['CATEGORY']] ?? '');
        $grade1    = trim($cells[$colMap['GRADE_1']] ?? '');
        $grade2    = trim($cells[$colMap['GRADE_2']] ?? '');
        $grade3    = trim($cells[$colMap['GRADE_3']] ?? '');
        $grade4    = trim($cells[$colMap['GRADE_4']] ?? '');
        $grade5    = trim($cells[$colMap['GRADE_5']] ?? '');

        // Skip empty rows
        if (empty($ctcaeTerm)) {
            $skipped++;
            continue;
        }

        try {
            // Use CTCAE term as diagnosis
            // Store grading criteria in caveat/outcome fields
            $insertStmt->execute([
                $ctcaeTerm,           // diagnosis
                $category,            // category
                $ctcaeTerm,           // ctcae_term
                $ctcaeCode,           // ctcae_code
                "Grade 1: $grade1",   // caveat1
                "Mild",               // outcome1
                "Grade 2: $grade2",   // caveat2
                "Moderate",           // outcome2
                "Grade 3-5: $grade3 / $grade4 / $grade5",  // caveat3
                "Severe",             // outcome3
            ]);

            $imported++;
            if ($imported % 100 === 0) {
                echo "Processed $imported rows...\n";
            }
        } catch (PDOException $e) {
            $errors++;
            echo "ERROR on row $rowIndex: {$e->getMessage()}\n";
        }
    }

    $pdo->commit();

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Import Complete!\n";
    echo "  Imported: $imported\n";
    echo "  Skipped:  $skipped\n";
    echo "  Errors:   $errors\n";

    // Verify import
    $v6Count = $pdo->query("SELECT COUNT(*) FROM dictionary_event WHERE ctcae_version = 'v6'")->fetchColumn();
    echo "\nTotal CTCAE v6 entries in database: $v6Count\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("FATAL ERROR: {$e->getMessage()}\n");
}
```

### Composer Setup: `composer.json`

```json
{
    "name": "phoenix/adjudication",
    "description": "PHOENIX Pharmacogenomic Trial Adjudication System",
    "type": "project",
    "require": {
        "php": ">=8.0",
        "phpoffice/phpspreadsheet": "^1.29"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0"
    },
    "autoload": {
        "psr-4": {
            "Phoenix\\": "inc/classes/"
        }
    }
}
```

### Testing Script: `tests/phase2_test.php`

```php
<?php
require_once __DIR__ . '/../inc/db.php';

echo "PHASE 2 TESTING: CTCAE v6.0 Data Import\n";
echo str_repeat("=", 60) . "\n\n";

$tests = [
    'CTCAE v6 entries exist' =>
        "SELECT COUNT(*) as cnt FROM dictionary_event WHERE ctcae_version = 'v6'",

    'CTCAE v5 entries preserved' =>
        "SELECT COUNT(*) as cnt FROM dictionary_event WHERE ctcae_version = 'v5' OR ctcae_version IS NULL",

    'No duplicate CTCAE v6 terms' =>
        "SELECT COUNT(*) - COUNT(DISTINCT ctcae_code) as cnt
         FROM dictionary_event
         WHERE ctcae_version = 'v6' AND ctcae_code IS NOT NULL",

    'Categories populated' =>
        "SELECT COUNT(*) as cnt FROM dictionary_event
         WHERE ctcae_version = 'v6' AND category IS NOT NULL AND category != ''",

    'Grading criteria stored' =>
        "SELECT COUNT(*) as cnt FROM dictionary_event
         WHERE ctcae_version = 'v6' AND (caveat1 IS NOT NULL OR caveat2 IS NOT NULL)"
];

$passed = 0;
$failed = 0;

foreach ($tests as $description => $sql) {
    try {
        $result = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        $count = (int)$result['cnt'];

        if ($description === 'CTCAE v6 entries exist' && $count > 0) {
            echo "✓ PASS: $description ($count entries)\n";
            $passed++;
        } elseif ($description === 'No duplicate CTCAE v6 terms' && $count === 0) {
            echo "✓ PASS: $description\n";
            $passed++;
        } elseif ($description === 'Categories populated' && $count > 0) {
            echo "✓ PASS: $description ($count with categories)\n";
            $passed++;
        } elseif ($description === 'Grading criteria stored' && $count > 0) {
            echo "✓ PASS: $description ($count with grading)\n";
            $passed++;
        } elseif ($description === 'CTCAE v5 entries preserved') {
            echo "✓ PASS: $description ($count preserved)\n";
            $passed++;
        } else {
            echo "✗ FAIL: $description (count: $count)\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "✗ ERROR: $description - {$e->getMessage()}\n";
        $failed++;
    }
}

// Sample data check
echo "\nSample CTCAE v6 entries:\n";
echo str_repeat("-", 60) . "\n";
$samples = $pdo->query("
    SELECT ctcae_code, ctcae_term, category
    FROM dictionary_event
    WHERE ctcae_version = 'v6'
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($samples as $sample) {
    echo "  [{$sample['ctcae_code']}] {$sample['ctcae_term']} - {$sample['category']}\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Results: $passed passed, $failed failed\n";
echo ($failed === 0) ? "✓ Phase 2 import successful!\n" : "✗ Phase 2 has issues - review required\n";

exit($failed > 0 ? 1 : 0);
```

### Manual Testing Checklist

- [ ] Create `data/` directory and place CTCAE v6.0 Excel file
- [ ] Install Composer dependencies: `composer install`
- [ ] Run import: `php scripts/import_ctcae_v6.php`
- [ ] Review import output for errors
- [ ] Run test script: `php tests/phase2_test.php`
- [ ] Verify sample entries in database: `SELECT * FROM dictionary_event WHERE ctcae_version='v6' LIMIT 10;`
- [ ] Check existing data unchanged: `SELECT COUNT(*) FROM dictionary_event WHERE ctcae_version='v5';`
- [ ] Backup database: `mysqldump phoenix > backups/post_phase2_$(date +%Y%m%d).sql`

---

## PHASE 3: Materialize CSS Integration

**Duration:** 3 days
**Risk Level:** Low
**Dependencies:** None (can run parallel to Phase 1-2)

### Objectives
- Replace custom CSS with Materialize CSS framework
- Maintain dark theme
- Ensure responsive design
- Create reusable component library

### Create Materialize Base Template: `inc/templates/header.php`

```php
<?php
if (!isset($pageTitle)) $pageTitle = 'PHOENIX Adjudication';
if (!isset($user)) $user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - PHOENIX</title>

    <!-- Materialize CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css" rel="stylesheet">

    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <!-- Custom Dark Theme -->
    <link rel="stylesheet" href="/assets/css/theme-dark.css">

    <!-- Page-specific CSS -->
    <?php if (isset($customCSS)): ?>
        <?php foreach ((array)$customCSS as $css): ?>
            <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <!-- Navigation -->
    <nav class="nav-extended blue-grey darken-4">
        <div class="nav-wrapper container">
            <a href="/dashboard.php" class="brand-logo">
                <i class="material-icons left">local_hospital</i>
                PHOENIX Adjudication
            </a>
            <a href="#" data-target="mobile-nav" class="sidenav-trigger">
                <i class="material-icons">menu</i>
            </a>
            <ul class="right hide-on-med-and-down">
                <?php if ($user): ?>
                    <li>
                        <a href="/dashboard.php">
                            <i class="material-icons left">dashboard</i>
                            Dashboard
                        </a>
                    </li>
                    <?php if (in_array($user['role'], ['admin', 'coordinator'])): ?>
                        <li>
                            <a href="/admin/import.php">
                                <i class="material-icons left">upload_file</i>
                                Import
                            </a>
                        </li>
                    <?php endif; ?>
                    <li>
                        <a class="dropdown-trigger" href="#!" data-target="user-dropdown">
                            <i class="material-icons left">account_circle</i>
                            <?= htmlspecialchars($user['name']) ?>
                            <i class="material-icons right">arrow_drop_down</i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- User Dropdown -->
    <?php if ($user): ?>
        <ul id="user-dropdown" class="dropdown-content">
            <li><a href="/profile.php"><i class="material-icons">person</i>Profile</a></li>
            <li><a href="/settings.php"><i class="material-icons">settings</i>Settings</a></li>
            <li class="divider"></li>
            <li><a href="/logout.php"><i class="material-icons">exit_to_app</i>Logout</a></li>
        </ul>
    <?php endif; ?>

    <!-- Mobile Sidenav -->
    <ul class="sidenav" id="mobile-nav">
        <?php if ($user): ?>
            <li>
                <div class="user-view">
                    <div class="background blue-grey darken-3"></div>
                    <a href="/profile.php">
                        <i class="material-icons white-text circle">account_circle</i>
                    </a>
                    <a href="/profile.php"><span class="white-text name"><?= htmlspecialchars($user['name']) ?></span></a>
                    <a href="/profile.php"><span class="white-text email"><?= htmlspecialchars($user['email']) ?></span></a>
                </div>
            </li>
            <li><a href="/dashboard.php"><i class="material-icons">dashboard</i>Dashboard</a></li>
            <?php if (in_array($user['role'], ['admin', 'coordinator'])): ?>
                <li><a href="/admin/import.php"><i class="material-icons">upload_file</i>Import Data</a></li>
            <?php endif; ?>
            <li><div class="divider"></div></li>
            <li><a href="/logout.php"><i class="material-icons">exit_to_app</i>Logout</a></li>
        <?php endif; ?>
    </ul>

    <!-- Main Content -->
    <main class="container" style="margin-top: 20px; margin-bottom: 40px;">
```

### Create Footer Template: `inc/templates/footer.php`

```php
    </main>

    <!-- Footer -->
    <footer class="page-footer blue-grey darken-4">
        <div class="container">
            <div class="row">
                <div class="col l6 s12">
                    <h5 class="white-text">PHOENIX Adjudication System</h5>
                    <p class="grey-text text-lighten-4">
                        Pharmacogenomic Trial Outcome Adjudication Platform
                    </p>
                </div>
                <div class="col l4 offset-l2 s12">
                    <h5 class="white-text">Support</h5>
                    <ul>
                        <li><a class="grey-text text-lighten-3" href="/docs/user-guide.php">User Guide</a></li>
                        <li><a class="grey-text text-lighten-3" href="/docs/api.php">API Documentation</a></li>
                        <li><a class="grey-text text-lighten-3" href="mailto:support@phoenix-trial.org">Contact Support</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="footer-copyright">
            <div class="container">
                © <?= date('Y') ?> PHOENIX Trial
                <span class="grey-text text-lighten-4 right">Version <?= $config['version'] ?? '1.0.0' ?></span>
            </div>
        </div>
    </footer>

    <!-- Materialize JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>

    <!-- Initialize Materialize components -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize sidenav
            var elems = document.querySelectorAll('.sidenav');
            M.Sidenav.init(elems);

            // Initialize dropdowns
            var dropdowns = document.querySelectorAll('.dropdown-trigger');
            M.Dropdown.init(dropdowns, {
                coverTrigger: false,
                constrainWidth: false
            });

            // Initialize modals
            var modals = document.querySelectorAll('.modal');
            M.Modal.init(modals);

            // Initialize select
            var selects = document.querySelectorAll('select');
            M.FormSelect.init(selects);

            // Initialize tooltips
            var tooltips = document.querySelectorAll('.tooltipped');
            M.Tooltip.init(tooltips);

            // Initialize collapsibles
            var collapsibles = document.querySelectorAll('.collapsible');
            M.Collapsible.init(collapsibles);

            // Initialize tabs
            var tabs = document.querySelectorAll('.tabs');
            M.Tabs.init(tabs);
        });
    </script>

    <!-- Custom JavaScript -->
    <script src="/assets/js/api.js"></script>

    <!-- Page-specific JavaScript -->
    <?php if (isset($customJS)): ?>
        <?php foreach ((array)$customJS as $js): ?>
            <script src="<?= htmlspecialchars($js) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
```

### Create Dark Theme CSS: `public/assets/css/theme-dark.css`

```css
/**
 * PHOENIX Dark Theme for Materialize CSS
 * Based on Blue Grey color palette
 */

:root {
    --bg-primary: #0f172a;
    --bg-secondary: #1e293b;
    --bg-card: #1e293b;
    --text-primary: #e2e8f0;
    --text-secondary: #94a3b8;
    --accent: #3b82f6;
    --accent-hover: #60a5fa;
    --danger: #ef4444;
    --success: #10b981;
    --warning: #f59e0b;
}

/* Body */
body {
    background-color: var(--bg-primary);
    color: var(--text-primary);
}

/* Cards */
.card {
    background-color: var(--bg-card);
    border-radius: 8px;
}

.card .card-title {
    color: var(--text-primary);
}

.card .card-content {
    color: var(--text-secondary);
}

/* Navigation */
nav {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

/* Forms */
input:not([type]),
input[type=text]:not(.browser-default),
input[type=password]:not(.browser-default),
input[type=email]:not(.browser-default),
input[type=date]:not(.browser-default),
input[type=number]:not(.browser-default),
textarea.materialize-textarea,
.select-wrapper input.select-dropdown {
    background-color: var(--bg-secondary);
    border-bottom: 1px solid #475569;
    color: var(--text-primary);
    box-sizing: border-box;
    padding: 8px;
    border-radius: 4px;
}

input:not([type]):focus,
input[type=text]:not(.browser-default):focus,
input[type=password]:not(.browser-default):focus,
input[type=email]:not(.browser-default):focus,
input[type=date]:not(.browser-default):focus,
input[type=number]:not(.browser-default):focus,
textarea.materialize-textarea:focus {
    border-bottom: 2px solid var(--accent);
    box-shadow: 0 1px 0 0 var(--accent);
}

label {
    color: var(--text-secondary);
}

label.active {
    color: var(--accent);
}

/* Select Dropdown */
.dropdown-content {
    background-color: var(--bg-card);
}

.dropdown-content li > a,
.dropdown-content li > span {
    color: var(--text-primary);
}

.dropdown-content li:hover,
.dropdown-content li.active {
    background-color: var(--bg-secondary);
}

.select-dropdown li.disabled,
.select-dropdown li.disabled > span,
.select-dropdown li.optgroup {
    color: var(--text-secondary);
}

/* Buttons */
.btn,
.btn-large,
.btn-small {
    background-color: var(--accent);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.btn:hover,
.btn-large:hover,
.btn-small:hover {
    background-color: var(--accent-hover);
}

.btn-flat {
    color: var(--accent);
}

/* Tables */
table {
    background-color: var(--bg-card);
}

table.striped > tbody > tr:nth-child(odd) {
    background-color: rgba(255, 255, 255, 0.02);
}

table.striped > tbody > tr > td {
    border-radius: 0;
}

th {
    color: var(--text-primary);
    border-bottom: 1px solid #475569;
}

td {
    color: var(--text-secondary);
    border-bottom: 1px solid #334155;
}

/* Collections */
.collection {
    background-color: var(--bg-card);
    border: 1px solid #334155;
}

.collection .collection-item {
    background-color: var(--bg-card);
    border-bottom: 1px solid #334155;
    color: var(--text-primary);
}

.collection .collection-item:hover {
    background-color: var(--bg-secondary);
}

/* Modals */
.modal {
    background-color: var(--bg-card);
    color: var(--text-primary);
}

.modal .modal-footer {
    background-color: var(--bg-secondary);
}

/* Chips */
.chip {
    background-color: var(--bg-secondary);
    color: var(--text-primary);
}

/* Badges */
.badge {
    background-color: var(--accent);
    color: white;
}

.badge.new {
    font-weight: 500;
}

/* Preloader */
.preloader-wrapper.small,
.preloader-wrapper.medium,
.preloader-wrapper.big {
    width: 36px;
    height: 36px;
}

/* Custom status badges */
.status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.85em;
    font-weight: 500;
    display: inline-block;
}

.status-badge.open {
    background-color: rgba(59, 130, 246, 0.2);
    color: #60a5fa;
}

.status-badge.submitted {
    background-color: rgba(245, 158, 11, 0.2);
    color: #fbbf24;
}

.status-badge.consensus {
    background-color: rgba(16, 185, 129, 0.2);
    color: #34d399;
}

.status-badge.closed {
    background-color: rgba(107, 114, 128, 0.2);
    color: #9ca3af;
}

/* Severity indicators */
.severity-mild {
    color: #10b981;
}

.severity-moderate {
    color: #f59e0b;
}

.severity-severe {
    color: #ef4444;
}

/* Loading states */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(15, 23, 42, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

/* Toast customization */
#toast-container {
    top: auto !important;
    right: auto !important;
    bottom: 10%;
    left: 7%;
}

.toast {
    background-color: var(--bg-secondary);
    color: var(--text-primary);
}

/* Responsive adjustments */
@media only screen and (max-width: 992px) {
    .container {
        width: 95%;
    }
}
```

### Convert Dashboard to Materialize: `public/dashboard_materialize.php`

```php
<?php
require_once __DIR__ . '/../inc/auth.php';
require_login();
$user = current_user();

$pageTitle = 'Dashboard';
$customJS = ['/assets/js/dashboard.js'];

require_once __DIR__ . '/../inc/templates/header.php';
?>

<!-- Page Header -->
<div class="row">
    <div class="col s12">
        <h4 class="blue-grey-text text-lighten-2">
            <i class="material-icons left">dashboard</i>
            Patient Dashboard
        </h4>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row">
    <div class="col s12 m6 l3">
        <div class="card">
            <div class="card-content center-align">
                <i class="material-icons large blue-text">people</i>
                <h5 id="stat-patients">-</h5>
                <p class="grey-text">Total Patients</p>
            </div>
        </div>
    </div>
    <div class="col s12 m6 l3">
        <div class="card">
            <div class="card-content center-align">
                <i class="material-icons large orange-text">assignment</i>
                <h5 id="stat-pending">-</h5>
                <p class="grey-text">Pending Cases</p>
            </div>
        </div>
    </div>
    <div class="col s12 m6 l3">
        <div class="card">
            <div class="card-content center-align">
                <i class="material-icons large green-text">check_circle</i>
                <h5 id="stat-consensus">-</h5>
                <p class="grey-text">Consensus Reached</p>
            </div>
        </div>
    </div>
    <div class="col s12 m6 l3">
        <div class="card">
            <div class="card-content center-align">
                <i class="material-icons large purple-text">description</i>
                <h5 id="stat-my-adjudications">-</h5>
                <p class="grey-text">My Adjudications</p>
            </div>
        </div>
    </div>
</div>

<!-- Import Section (Admin/Coordinator only) -->
<?php if (in_array($user['role'], ['admin', 'coordinator'])): ?>
<div class="row">
    <div class="col s12">
        <div class="card">
            <div class="card-content">
                <span class="card-title">
                    <i class="material-icons left">upload_file</i>
                    Import Master Table
                </span>
                <form method="post" action="../api/import_master.php" enctype="multipart/form-data" id="import-form">
                    <div class="file-field input-field">
                        <div class="btn">
                            <span><i class="material-icons left">attach_file</i>Choose CSV</span>
                            <input type="file" name="csv" accept=".csv" required>
                        </div>
                        <div class="file-path-wrapper">
                            <input class="file-path validate" type="text" placeholder="PHOENIX_Master_LongTable.csv">
                        </div>
                    </div>
                    <button class="btn waves-effect waves-light" type="submit">
                        <i class="material-icons left">cloud_upload</i>
                        Upload & Import
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Add Patient Section -->
<div class="row">
    <div class="col s12">
        <div class="card">
            <div class="card-content">
                <span class="card-title">
                    <i class="material-icons left">person_add</i>
                    Add New Patient
                </span>
                <form id="add-patient-form">
                    <div class="row">
                        <div class="input-field col s12 m4">
                            <input id="patient_code" type="text" name="patient_code" required>
                            <label for="patient_code">Patient ID</label>
                        </div>
                        <div class="input-field col s12 m4">
                            <input id="randomisation_date" type="date" name="randomisation_date" required>
                            <label for="randomisation_date">Randomisation Date</label>
                        </div>
                        <div class="input-field col s12 m4">
                            <select id="index_drug_id" name="index_drug_id" required>
                                <option value="" disabled selected>Loading...</option>
                            </select>
                            <label>Index Drug</label>
                        </div>
                    </div>
                    <button class="btn waves-effect waves-light blue" type="submit">
                        <i class="material-icons left">add</i>
                        Add Patient
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Patients List -->
<div class="row">
    <div class="col s12">
        <div class="card">
            <div class="card-content">
                <span class="card-title">
                    <i class="material-icons left">list</i>
                    Patients
                </span>

                <!-- Search and Filter -->
                <div class="row">
                    <div class="input-field col s12 m6">
                        <i class="material-icons prefix">search</i>
                        <input type="text" id="search-patients" placeholder="Search by patient code...">
                    </div>
                    <div class="input-field col s12 m6">
                        <select id="filter-drug">
                            <option value="">All Index Drugs</option>
                        </select>
                        <label>Filter by Drug</label>
                    </div>
                </div>

                <div id="patients-list">
                    <div class="progress">
                        <div class="indeterminate"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/templates/footer.php'; ?>
```

### Testing Script: `tests/phase3_test.php`

```php
<?php
echo "PHASE 3 TESTING: Materialize CSS Integration\n";
echo str_repeat("=", 60) . "\n\n";

$files = [
    'Header template' => __DIR__ . '/../inc/templates/header.php',
    'Footer template' => __DIR__ . '/../inc/templates/footer.php',
    'Dark theme CSS' => __DIR__ . '/../public/assets/css/theme-dark.css',
    'Dashboard with Materialize' => __DIR__ . '/../public/dashboard_materialize.php',
];

$passed = 0;
$failed = 0;

foreach ($files as $description => $file) {
    if (file_exists($file)) {
        $size = filesize($file);
        if ($size > 100) { // Basic sanity check
            echo "✓ PASS: $description exists ($size bytes)\n";
            $passed++;
        } else {
            echo "✗ FAIL: $description is too small ($size bytes)\n";
            $failed++;
        }
    } else {
        echo "✗ FAIL: $description not found\n";
        $failed++;
    }
}

// Check for Materialize CSS references
echo "\nChecking Materialize CSS integration:\n";
echo str_repeat("-", 60) . "\n";

$headerContent = file_get_contents($files['Header template']);
if (strpos($headerContent, 'materialize') !== false) {
    echo "✓ PASS: Materialize CSS linked in header\n";
    $passed++;
} else {
    echo "✗ FAIL: Materialize CSS not found in header\n";
    $failed++;
}

if (strpos($headerContent, 'Material+Icons') !== false) {
    echo "✓ PASS: Material Icons linked in header\n";
    $passed++;
} else {
    echo "✗ FAIL: Material Icons not found in header\n";
    $failed++;
}

$footerContent = file_get_contents($files['Footer template']);
if (strpos($footerContent, 'M.Sidenav.init') !== false) {
    echo "✓ PASS: Materialize JS initialization present\n";
    $passed++;
} else {
    echo "✗ FAIL: Materialize JS initialization missing\n";
    $failed++;
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Results: $passed passed, $failed failed\n";
echo ($failed === 0) ? "✓ Phase 3 integration successful!\n" : "✗ Phase 3 has issues - review required\n";
echo "\nManual testing required:\n";
echo "  1. Open dashboard_materialize.php in browser\n";
echo "  2. Verify Materialize components render correctly\n";
echo "  3. Test mobile responsiveness\n";
echo "  4. Check dark theme colors\n";

exit($failed > 0 ? 1 : 0);
```

### Manual Testing Checklist

- [ ] Create template directories: `mkdir -p inc/templates public/assets/css`
- [ ] Create all template files (header.php, footer.php, theme-dark.css)
- [ ] Create dashboard_materialize.php
- [ ] Run test script: `php tests/phase3_test.php`
- [ ] Open http://localhost/dashboard_materialize.php in browser
- [ ] Test navigation menu (desktop and mobile)
- [ ] Test dropdowns and modals
- [ ] Verify form inputs render correctly
- [ ] Test responsive layout on mobile device/emulator
- [ ] Verify dark theme colors match design
- [ ] Check Material Icons display correctly
- [ ] Test all interactive components (selects, tooltips, etc.)

---

## PHASE 4-12 Overview

Due to length constraints, here's a summary of the remaining phases. Each will follow the same structure (objectives, code, testing, checklist):

### PHASE 4: Security Hardening (3 days)
- Move credentials to `.env`
- Implement CSRF protection
- Add input validation library
- Enable prepared statements everywhere
- Add rate limiting for auth

**Key Deliverables:**
- `inc/security.php` - CSRF token generation/validation
- `inc/validator.php` - Input validation class
- `.htaccess` security headers
- `tests/phase4_security_test.php`

### PHASE 5: API Standardization (2 days)
- Consistent JSON response format
- Proper HTTP status codes
- Error handling middleware
- API documentation

**Key Deliverables:**
- `inc/api_response.php` - Standardized response class
- `inc/error_handler.php` - Global error handler
- `docs/API.md` - API documentation
- `tests/phase5_api_test.php`

### PHASE 6: Evidence Management System (4 days)
- File upload for clinical notes
- ICD code entry interface
- Lab value recording
- Timeline visualization

**Key Deliverables:**
- `public/evidence_upload.php`
- `api/evidence.php`
- `public/assets/js/timeline.js`
- `migrations/006_evidence_tables.sql`
- `tests/phase6_evidence_test.php`

### PHASE 7: Blind Adjudication (3 days)
- Hide previous adjudications until submitted
- Lock mechanism
- Adjudicator assignment workflow
- Workload balancing

**Key Deliverables:**
- `migrations/007_adjudication_locking.sql`
- Updated `api/adjudications.php`
- `public/case_event_blind.php`
- `tests/phase7_blind_test.php`

### PHASE 8: Enhanced Consensus Workflow (3 days)
- Side-by-side adjudication comparison
- Conflict resolution UI
- Weighted voting option
- Meeting notes integration

**Key Deliverables:**
- `public/consensus_review.php`
- Updated `api/consensus.php`
- `public/assets/js/consensus.js`
- `tests/phase8_consensus_test.php`

### PHASE 9: CTCAE Version Selector (2 days)
- UI to select CTCAE v5 vs v6
- Version filtering in dropdowns
- Migration path for existing events
- Reporting by version

**Key Deliverables:**
- Updated `public/case_event.php` with version selector
- `api/ctcae_versions.php`
- `scripts/migrate_ctcae_version.php`
- `tests/phase9_ctcae_selector_test.php`

### PHASE 10: Audit Trail & Compliance (3 days)
- Populate audit_log automatically
- Electronic signature workflow
- Change history viewer
- Regulatory export (CIOMS, E2B)

**Key Deliverables:**
- `inc/audit.php` - Audit logging class
- `public/audit_viewer.php`
- `api/export_regulatory.php`
- `migrations/010_audit_triggers.sql`
- `tests/phase10_audit_test.php`

### PHASE 11: Performance Optimization (2 days)
- Add pagination to all lists
- Implement caching strategy
- Optimize queries with indexes
- Asset minification

**Key Deliverables:**
- `inc/pagination.php`
- `inc/cache.php`
- Updated API endpoints with pagination
- `scripts/optimize_database.sql`
- `tests/phase11_performance_test.php`

### PHASE 12: User Experience Polish (3 days)
- Loading states for all async operations
- Form validation with feedback
- Keyboard shortcuts
- Dashboard metrics
- Search/filter functionality

**Key Deliverables:**
- `public/assets/js/ux_enhancements.js`
- Updated all forms with validation
- `public/assets/js/keyboard_shortcuts.js`
- `public/analytics_dashboard.php`
- `tests/phase12_ux_test.php`

---

## Testing Strategy

### Automated Testing
Each phase includes:
1. **Unit tests** - Test individual functions/methods
2. **Integration tests** - Test API endpoints
3. **Database tests** - Verify schema changes

### Manual Testing
Each phase includes:
1. **Functional checklist** - Feature-by-feature verification
2. **Regression testing** - Ensure previous features still work
3. **Browser testing** - Chrome, Firefox, Safari, Edge

### Continuous Testing
```bash
# Run all phase tests
for i in {1..12}; do
    echo "Testing Phase $i..."
    php tests/phase${i}_test.php
    if [ $? -ne 0 ]; then
        echo "Phase $i failed!"
        exit 1
    fi
done
echo "All phases passed!"
```

---

## Deployment Strategy

### Development → Staging → Production

**Development:**
- Feature branch for each phase
- Local testing with test database
- Peer review before merge

**Staging:**
- Mirror of production
- Run full test suite
- User acceptance testing
- Performance testing

**Production:**
- Scheduled maintenance window
- Database backup before deployment
- Run migrations
- Smoke tests post-deployment
- Rollback plan ready

### Rollback Procedure
```bash
# 1. Restore database
mysql phoenix < backups/pre_phase${N}_YYYYMMDD.sql

# 2. Restore code
git revert <commit-hash>

# 3. Clear caches
rm -rf cache/*

# 4. Verify system
php tests/smoke_test.php
```

---

## Success Criteria

Each phase must meet:
- ✅ All automated tests pass
- ✅ Manual testing checklist complete
- ✅ No regression bugs
- ✅ Performance within acceptable limits
- ✅ Documentation updated
- ✅ Peer review approved
- ✅ Database backup created

---

## Timeline Summary

| Phase | Duration | Total Days |
|-------|----------|------------|
| Pre-Phase: Setup | 1 day | 1 |
| Phase 1: Database Enhancement | 3 days | 4 |
| Phase 2: CTCAE v6 Import | 2 days | 6 |
| Phase 3: Materialize CSS | 3 days | 9 |
| Phase 4: Security Hardening | 3 days | 12 |
| Phase 5: API Standardization | 2 days | 14 |
| Phase 6: Evidence Management | 4 days | 18 |
| Phase 7: Blind Adjudication | 3 days | 21 |
| Phase 8: Enhanced Consensus | 3 days | 24 |
| Phase 9: CTCAE Selector | 2 days | 26 |
| Phase 10: Audit & Compliance | 3 days | 29 |
| Phase 11: Performance | 2 days | 31 |
| Phase 12: UX Polish | 3 days | 34 |
| **Total** | **34 days** | **(~7 weeks)** |

Add 20% buffer for testing/fixes: **~42 days (8-9 weeks)**

---

## Risk Mitigation

### High-Risk Areas
1. **Database migrations** - Always have rollback scripts ready
2. **Authentication changes** - Test thoroughly to avoid lockouts
3. **CTCAE import** - Validate data integrity before committing

### Mitigation Strategies
- Maintain development/staging/production separation
- Never skip backups before migrations
- Test rollback procedures
- Document all changes
- Keep changelog updated

---

## Conclusion

This step-wise strategy allows for:
- ✅ Incremental progress with testing at each stage
- ✅ Early detection of issues
- ✅ Ability to roll back individual phases
- ✅ Maintained system stability
- ✅ Clear success criteria
- ✅ Manageable scope per phase

Begin with Pre-Phase setup, then proceed through phases 1-12 sequentially, ensuring each phase passes all tests before moving to the next.
