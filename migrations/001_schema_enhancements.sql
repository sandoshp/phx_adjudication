-- Migration 001: Schema Enhancements for CTCAE v6.0 Support
-- Run date: 2025-12-13
-- Rollback file: migrations/001_rollback.sql
-- Description: Adds CTCAE versioning, performance indexes, and audit enhancements

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
  ('enable_audit_logging', 'true', 'boolean', 'Enable comprehensive audit logging'),
  ('max_upload_size', '10485760', 'integer', 'Maximum file upload size in bytes (10MB)'),
  ('allowed_file_types', 'pdf,doc,docx,jpg,jpeg,png', 'string', 'Comma-separated list of allowed file extensions');

-- 6. Add version tracking to adjudications
ALTER TABLE adjudication
  ADD COLUMN version INT NOT NULL DEFAULT 1 AFTER id,
  ADD COLUMN is_current TINYINT(1) NOT NULL DEFAULT 1 AFTER version,
  ADD INDEX idx_version (case_event_id, adjudicator_id, is_current);

-- Update existing records
UPDATE adjudication SET is_current = 1, version = 1 WHERE version = 0 OR version IS NULL;

-- 7. Drop old unique constraint and add new one allowing versions
ALTER TABLE adjudication
  DROP INDEX uniq_adj,
  ADD UNIQUE KEY uniq_adj_version (case_event_id, adjudicator_id, version);

-- 8. Create a view for current adjudications only
CREATE OR REPLACE VIEW adjudication_current AS
  SELECT * FROM adjudication WHERE is_current = 1;

-- 9. Add missing table for adjudication_drug (if not exists from Phase 1 review)
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
