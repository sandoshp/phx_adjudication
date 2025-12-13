
-- PHOENIX Adjudication Platform - FIXED SCHEMA (no functional index)
-- Works on MySQL 5.7+ and MySQL 8.x
SET NAMES utf8mb4;
SET time_zone = '+00:00';

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS consensus;
DROP TABLE IF EXISTS adjudication;
DROP TABLE IF EXISTS evidence_note;
DROP TABLE IF EXISTS evidence_lab;
DROP TABLE IF EXISTS evidence_icd;
DROP TABLE IF EXISTS case_event;
DROP TABLE IF EXISTS patient_concomitant_drug;
DROP TABLE IF EXISTS patients;
DROP TABLE IF EXISTS drug_event_map;
DROP TABLE IF EXISTS dictionary_event;
DROP TABLE IF EXISTS drugs;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  role ENUM('adjudicator','coordinator','chair','admin') NOT NULL DEFAULT 'adjudicator',
  password_hash VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE drugs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL UNIQUE,
  class VARCHAR(255) NULL,
  genes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NOTE: Avoid functional index by adding a generated stored column icd10_norm = COALESCE(icd10,'')
CREATE TABLE dictionary_event (
  id INT AUTO_INCREMENT PRIMARY KEY,
  diagnosis VARCHAR(512) NOT NULL,
  category VARCHAR(255) NULL,
  ctcae_term VARCHAR(255) NULL,
  admission_grade VARCHAR(64) NULL,
  icd10 VARCHAR(255) NULL,
  icd10_norm VARCHAR(255) GENERATED ALWAYS AS (COALESCE(icd10, '')) STORED,
  source ENUM('ICD','LAB') NOT NULL,
  caveat1 TEXT NULL,
  outcome1 TEXT NULL,
  caveat2 TEXT NULL,
  outcome2 TEXT NULL,
  caveat3 TEXT NULL,
  outcome3 TEXT NULL,
  lcat1 TEXT NULL,
  lcat1_met1 TEXT NULL,
  lcat1_met2 TEXT NULL,
  lcat1_notmet TEXT NULL,
  lcat2 TEXT NULL,
  lcat2_met1 TEXT NULL,
  lcat2_notmet TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_event (diagnosis, icd10_norm, source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE drug_event_map (
  drug_id INT NOT NULL,
  dict_event_id INT NOT NULL,
  expected_flag TINYINT(1) NULL,
  PRIMARY KEY (drug_id, dict_event_id),
  CONSTRAINT fk_dem_drug FOREIGN KEY (drug_id) REFERENCES drugs(id) ON DELETE CASCADE,
  CONSTRAINT fk_dem_event FOREIGN KEY (dict_event_id) REFERENCES dictionary_event(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE patients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_code VARCHAR(64) NOT NULL UNIQUE,
  randomisation_date DATE NOT NULL,
  followup_end_date DATE NOT NULL,
  index_drug_id INT NOT NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_patients_drug FOREIGN KEY (index_drug_id) REFERENCES drugs(id),
  CONSTRAINT fk_patients_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE patient_concomitant_drug (
  patient_id INT NOT NULL,
  drug_id INT NOT NULL,
  start_date DATE NULL,
  stop_date DATE NULL,
  PRIMARY KEY (patient_id, drug_id),
  CONSTRAINT fk_pcd_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  CONSTRAINT fk_pcd_drug FOREIGN KEY (drug_id) REFERENCES drugs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE case_event (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  dict_event_id INT NOT NULL,
  onset_datetime DATETIME NULL,
  status ENUM('open','info_requested','submitted','consensus','closed') NOT NULL DEFAULT 'open',
  phenotype_override VARCHAR(512) NULL,
  is_lab_primary TINYINT(1) NULL,
  notes TEXT NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_caseevent_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  CONSTRAINT fk_caseevent_event FOREIGN KEY (dict_event_id) REFERENCES dictionary_event(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE evidence_icd (
  id INT AUTO_INCREMENT PRIMARY KEY,
  case_event_id INT NOT NULL,
  icd_code VARCHAR(64) NOT NULL,
  event_date DATE NULL,
  encounter_id VARCHAR(64) NULL,
  details TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_eicd_case FOREIGN KEY (case_event_id) REFERENCES case_event(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE evidence_lab (
  id INT AUTO_INCREMENT PRIMARY KEY,
  case_event_id INT NOT NULL,
  test VARCHAR(128) NOT NULL,
  value VARCHAR(64) NULL,
  units VARCHAR(32) NULL,
  ref_low VARCHAR(32) NULL,
  ref_high VARCHAR(32) NULL,
  threshold_met TINYINT(1) NULL,
  sample_datetime DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_elab_case FOREIGN KEY (case_event_id) REFERENCES case_event(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE evidence_note (
  id INT AUTO_INCREMENT PRIMARY KEY,
  case_event_id INT NOT NULL,
  note_type VARCHAR(64) NULL,
  filename VARCHAR(255) NULL,
  url VARCHAR(512) NULL,
  version INT NULL,
  uploaded_by INT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_enote_case FOREIGN KEY (case_event_id) REFERENCES case_event(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE adjudication (
  id INT AUTO_INCREMENT PRIMARY KEY,
  case_event_id INT NOT NULL,
  adjudicator_id INT NOT NULL,
  framework ENUM('WHO-UMC','Naranjo') NOT NULL,
  responses JSON NULL,
  auto_score INT NULL,
  causality ENUM('Definite','Probable','Possible','Unrelated','Unable') NOT NULL,
  severity ENUM('Mild','Moderate','Severe') NOT NULL,
  expectedness ENUM('Expected','Unexpected','Not_Assessable') NOT NULL,
  index_attribution ENUM('Yes','No','Indeterminate') NOT NULL,
  suspected_concomitants JSON NULL,
  rationale TEXT NULL,
  missing_info JSON NULL,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_adj (case_event_id, adjudicator_id),
  CONSTRAINT fk_adj_case FOREIGN KEY (case_event_id) REFERENCES case_event(id) ON DELETE CASCADE,
  CONSTRAINT fk_adj_user FOREIGN KEY (adjudicator_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE consensus (
  id INT AUTO_INCREMENT PRIMARY KEY,
  case_event_id INT NOT NULL UNIQUE,
  method ENUM('majority','meeting','arbitration') NOT NULL,
  decided_by INT NULL,
  causality ENUM('Definite','Probable','Possible','Unrelated','Unable') NOT NULL,
  severity ENUM('Mild','Moderate','Severe') NOT NULL,
  expectedness ENUM('Expected','Unexpected','Not_Assessable') NOT NULL,
  suspected_drugs JSON NULL,
  rationale TEXT NULL,
  decided_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cons_case FOREIGN KEY (case_event_id) REFERENCES case_event(id) ON DELETE CASCADE,
  CONSTRAINT fk_cons_user FOREIGN KEY (decided_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_log (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  entity_type VARCHAR(64) NOT NULL,
  entity_id BIGINT NOT NULL,
  field VARCHAR(128) NOT NULL,
  old_value TEXT NULL,
  new_value TEXT NULL,
  user_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
