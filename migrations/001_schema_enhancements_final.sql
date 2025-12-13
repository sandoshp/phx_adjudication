-- Migration 001: Schema Enhancements for CTCAE v6.0 Support (FINAL)
-- Run date: 2025-12-13
-- Rollback file: migrations/001_rollback_final.sql
-- Description: Adds CTCAE versioning, performance indexes, and audit enhancements
-- Note: Handles existing indexes gracefully

START TRANSACTION;

-- 1. Add CTCAE version tracking to dictionary_event
-- Check if columns already exist before adding
SET @dbname = DATABASE();
SET @tablename = 'dictionary_event';
SET @columnname = 'ctcae_version';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT "Column ctcae_version already exists" AS message',
  'ALTER TABLE dictionary_event
   ADD COLUMN ctcae_version ENUM(''v5'', ''v6'') NULL DEFAULT ''v5'' AFTER ctcae_term,
   ADD COLUMN ctcae_code VARCHAR(32) NULL AFTER ctcae_version,
   ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER ctcae_code,
   ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 2. Update unique constraint to include CTCAE version
-- Drop old index if exists
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'uniq_event') > 0,
  'ALTER TABLE dictionary_event DROP INDEX uniq_event',
  'SELECT "Index uniq_event does not exist" AS message'
));
PREPARE dropIfExists FROM @preparedStatement;
EXECUTE dropIfExists;
DEALLOCATE PREPARE dropIfExists;

-- Add new unique index if not exists
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'uniq_event_versioned') > 0,
  'SELECT "Index uniq_event_versioned already exists" AS message',
  'ALTER TABLE dictionary_event ADD UNIQUE KEY uniq_event_versioned (diagnosis, icd10, source, ctcae_version)'
));
PREPARE addIfNotExists FROM @preparedStatement;
EXECUTE addIfNotExists;
DEALLOCATE PREPARE addIfNotExists;

-- 3. Add performance indexes on case_event
SET @tablename = 'case_event';

-- idx_patient_status
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'idx_patient_status') > 0,
  'SELECT "Index idx_patient_status already exists" AS message',
  'ALTER TABLE case_event ADD INDEX idx_patient_status (patient_id, status)'
));
PREPARE addIfNotExists FROM @preparedStatement;
EXECUTE addIfNotExists;
DEALLOCATE PREPARE addIfNotExists;

-- idx_status
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'idx_status') > 0,
  'SELECT "Index idx_status already exists" AS message',
  'ALTER TABLE case_event ADD INDEX idx_status (status)'
));
PREPARE addIfNotExists FROM @preparedStatement;
EXECUTE addIfNotExists;
DEALLOCATE PREPARE addIfNotExists;

-- idx_created_at
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'idx_created_at') > 0,
  'SELECT "Index idx_created_at already exists" AS message',
  'ALTER TABLE case_event ADD INDEX idx_created_at (created_at)'
));
PREPARE addIfNotExists FROM @preparedStatement;
EXECUTE addIfNotExists;
DEALLOCATE PREPARE addIfNotExists;

-- 4. Add performance indexes on adjudication
SET @tablename = 'adjudication';

-- idx_case_event
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'idx_case_event') > 0,
  'SELECT "Index idx_case_event already exists" AS message',
  'ALTER TABLE adjudication ADD INDEX idx_case_event (case_event_id)'
));
PREPARE addIfNotExists FROM @preparedStatement;
EXECUTE addIfNotExists;
DEALLOCATE PREPARE addIfNotExists;

-- idx_adjudicator
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'idx_adjudicator') > 0,
  'SELECT "Index idx_adjudicator already exists" AS message',
  'ALTER TABLE adjudication ADD INDEX idx_adjudicator (adjudicator_id)'
));
PREPARE addIfNotExists FROM @preparedStatement;
EXECUTE addIfNotExists;
DEALLOCATE PREPARE addIfNotExists;

-- idx_submitted
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'idx_submitted') > 0,
  'SELECT "Index idx_submitted already exists" AS message',
  'ALTER TABLE adjudication ADD INDEX idx_submitted (submitted_at)'
));
PREPARE addIfNotExists FROM @preparedStatement;
EXECUTE addIfNotExists;
DEALLOCATE PREPARE addIfNotExists;

-- 5. Add performance indexes on patients
SET @tablename = 'patients';

-- idx_randomisation_date
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'idx_randomisation_date') > 0,
  'SELECT "Index idx_randomisation_date already exists" AS message',
  'ALTER TABLE patients ADD INDEX idx_randomisation_date (randomisation_date)'
));
PREPARE addIfNotExists FROM @preparedStatement;
EXECUTE addIfNotExists;
DEALLOCATE PREPARE addIfNotExists;

-- idx_index_drug
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'idx_index_drug') > 0,
  'SELECT "Index idx_index_drug already exists" AS message',
  'ALTER TABLE patients ADD INDEX idx_index_drug (index_drug_id)'
));
PREPARE addIfNotExists FROM @preparedStatement;
EXECUTE addIfNotExists;
DEALLOCATE PREPARE addIfNotExists;

-- 6. Add performance indexes on patient_concomitant_drug
SET @tablename = 'patient_concomitant_drug';

-- idx_patient
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'idx_patient') > 0,
  'SELECT "Index idx_patient already exists" AS message',
  'ALTER TABLE patient_concomitant_drug ADD INDEX idx_patient (patient_id)'
));
PREPARE addIfNotExists FROM @preparedStatement;
EXECUTE addIfNotExists;
DEALLOCATE PREPARE addIfNotExists;

-- idx_drug
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'idx_drug') > 0,
  'SELECT "Index idx_drug already exists" AS message',
  'ALTER TABLE patient_concomitant_drug ADD INDEX idx_drug (drug_id)'
));
PREPARE addIfNotExists FROM @preparedStatement;
EXECUTE addIfNotExists;
DEALLOCATE PREPARE addIfNotExists;

-- 7. Enhance audit_log table
SET @tablename = 'audit_log';
SET @columnname = 'action_type';

-- Add columns if not exist
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT "Column action_type already exists" AS message',
  'ALTER TABLE audit_log
   ADD COLUMN action_type ENUM(''CREATE'', ''UPDATE'', ''DELETE'', ''VIEW'') NOT NULL DEFAULT ''UPDATE'' AFTER entity_id,
   ADD COLUMN ip_address VARCHAR(45) NULL AFTER user_id,
   ADD COLUMN user_agent TEXT NULL AFTER ip_address'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add indexes on audit_log
-- idx_entity
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'idx_entity') > 0,
  'SELECT "Index idx_entity already exists" AS message',
  'ALTER TABLE audit_log ADD INDEX idx_entity (entity_type, entity_id)'
));
PREPARE addIfNotExists FROM @preparedStatement;
EXECUTE addIfNotExists;
DEALLOCATE PREPARE addIfNotExists;

-- idx_user
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'idx_user') > 0,
  'SELECT "Index idx_user already exists" AS message',
  'ALTER TABLE audit_log ADD INDEX idx_user (user_id, created_at)'
));
PREPARE addIfNotExists FROM @preparedStatement;
EXECUTE addIfNotExists;
DEALLOCATE PREPARE addIfNotExists;

-- idx_created
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'idx_created') > 0,
  'SELECT "Index idx_created already exists" AS message',
  'ALTER TABLE audit_log ADD INDEX idx_created (created_at)'
));
PREPARE addIfNotExists FROM @preparedStatement;
EXECUTE addIfNotExists;
DEALLOCATE PREPARE addIfNotExists;

-- 8. Add configuration table for system settings
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

-- Insert default configurations (ignore if already exist)
INSERT IGNORE INTO system_config (config_key, config_value, config_type, description) VALUES
  ('default_ctcae_version', 'v5', 'string', 'Default CTCAE version for new events'),
  ('min_adjudications_required', '3', 'integer', 'Minimum adjudications before consensus'),
  ('followup_months', '3', 'integer', 'Default follow-up period in months'),
  ('enable_audit_logging', 'true', 'boolean', 'Enable comprehensive audit logging'),
  ('max_upload_size', '10485760', 'integer', 'Maximum file upload size in bytes (10MB)'),
  ('allowed_file_types', 'pdf,doc,docx,jpg,jpeg,png', 'string', 'Comma-separated list of allowed file extensions');

-- 9. Add version tracking to adjudications
SET @tablename = 'adjudication';
SET @columnname = 'version';

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT "Column version already exists" AS message',
  'ALTER TABLE adjudication
   ADD COLUMN version INT NOT NULL DEFAULT 1 AFTER id,
   ADD COLUMN is_current TINYINT(1) NOT NULL DEFAULT 1 AFTER version'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add index on version
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'idx_version') > 0,
  'SELECT "Index idx_version already exists" AS message',
  'ALTER TABLE adjudication ADD INDEX idx_version (case_event_id, adjudicator_id, is_current)'
));
PREPARE addIfNotExists FROM @preparedStatement;
EXECUTE addIfNotExists;
DEALLOCATE PREPARE addIfNotExists;

-- Update existing records
UPDATE adjudication SET is_current = 1, version = 1 WHERE version = 0 OR is_current = 0;

-- 10. Update unique constraint on adjudication
-- Drop old unique constraint if exists
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'uniq_adj') > 0,
  'ALTER TABLE adjudication DROP INDEX uniq_adj',
  'SELECT "Index uniq_adj does not exist" AS message'
));
PREPARE dropIfExists FROM @preparedStatement;
EXECUTE dropIfExists;
DEALLOCATE PREPARE dropIfExists;

-- Add new unique constraint if not exists
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'uniq_adj_version') > 0,
  'SELECT "Index uniq_adj_version already exists" AS message',
  'ALTER TABLE adjudication ADD UNIQUE KEY uniq_adj_version (case_event_id, adjudicator_id, version)'
));
PREPARE addIfNotExists FROM @preparedStatement;
EXECUTE addIfNotExists;
DEALLOCATE PREPARE addIfNotExists;

-- 11. Create view for current adjudications only
DROP VIEW IF EXISTS adjudication_current;
CREATE VIEW adjudication_current AS
  SELECT * FROM adjudication WHERE is_current = 1;

-- 12. Add table for adjudication_drug
CREATE TABLE IF NOT EXISTS adjudication_drug (
  id INT AUTO_INCREMENT PRIMARY KEY,
  adjudication_id INT NOT NULL,
  drug_id INT NOT NULL,
  role ENUM('index', 'concomitant') NOT NULL,
  attribution ENUM('Yes', 'No', 'Indeterminate') NOT NULL DEFAULT 'Yes',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_adj_drug (adjudication_id, drug_id, role),
  FOREIGN KEY (adjudication_id) REFERENCES adjudication(id) ON DELETE CASCADE,
  FOREIGN KEY (drug_id) REFERENCES drugs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

-- Record migration in system_config
INSERT INTO system_config (config_key, config_value, config_type, description)
VALUES ('schema_version', '001', 'string', 'Current database schema version')
ON DUPLICATE KEY UPDATE config_value = '001', updated_at = CURRENT_TIMESTAMP;

SELECT 'Migration 001 completed successfully' AS status;
SELECT 'You can now run: php tests/phase1_test.php' AS next_step;
