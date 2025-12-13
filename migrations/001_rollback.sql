-- Rollback Migration 001: Schema Enhancements
-- This script reverses all changes made in 001_schema_enhancements.sql

START TRANSACTION;

-- Drop adjudication_drug table if it was created
DROP TABLE IF EXISTS adjudication_drug;

-- Drop view
DROP VIEW IF EXISTS adjudication_current;

-- Restore adjudication table
ALTER TABLE adjudication
  DROP INDEX uniq_adj_version,
  DROP INDEX idx_version,
  DROP COLUMN is_current,
  DROP COLUMN version,
  ADD UNIQUE KEY uniq_adj (case_event_id, adjudicator_id);

-- Drop system_config table
DROP TABLE IF EXISTS system_config;

-- Restore audit_log table
ALTER TABLE audit_log
  DROP INDEX idx_created,
  DROP INDEX idx_user,
  DROP INDEX idx_entity,
  DROP COLUMN user_agent,
  DROP COLUMN ip_address,
  DROP COLUMN action_type;

-- Remove indexes from patient_concomitant_drug
ALTER TABLE patient_concomitant_drug
  DROP INDEX idx_drug,
  DROP INDEX idx_patient;

-- Remove indexes from patients
ALTER TABLE patients
  DROP INDEX idx_index_drug,
  DROP INDEX idx_randomisation_date;

-- Remove indexes from adjudication
ALTER TABLE adjudication
  DROP INDEX idx_submitted,
  DROP INDEX idx_adjudicator,
  DROP INDEX idx_case_event;

-- Remove indexes from case_event
ALTER TABLE case_event
  DROP INDEX idx_created_at,
  DROP INDEX idx_status,
  DROP INDEX idx_patient_status;

-- Restore dictionary_event table
ALTER TABLE dictionary_event
  DROP INDEX uniq_event_versioned,
  ADD UNIQUE KEY uniq_event (diagnosis, IFNULL(icd10,''), source),
  DROP COLUMN updated_at,
  DROP COLUMN active,
  DROP COLUMN ctcae_code,
  DROP COLUMN ctcae_version;

COMMIT;

SELECT 'Migration 001 rollback completed successfully' AS status;
