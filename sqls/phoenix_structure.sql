-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 06, 2025 at 03:54 PM
-- Server version: 8.0.43
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `phoenix`
--

-- --------------------------------------------------------

--
-- Table structure for table `adjudication`
--

CREATE TABLE `adjudication` (
  `id` int NOT NULL,
  `case_event_id` int NOT NULL,
  `adjudicator_id` int NOT NULL,
  `framework` enum('WHO-UMC','Naranjo') NOT NULL,
  `responses` json DEFAULT NULL,
  `auto_score` int DEFAULT NULL,
  `causality` enum('Definite','Probable','Possible','Unrelated','Unable') NOT NULL,
  `severity` enum('Mild','Moderate','Severe') NOT NULL,
  `expectedness` enum('Expected','Unexpected','Not_Assessable') NOT NULL,
  `index_attribution` enum('Yes','No','Indeterminate') NOT NULL,
  `suspected_concomitants` json DEFAULT NULL,
  `rationale` text,
  `missing_info` json DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `adjudication_drug`
--

CREATE TABLE `adjudication_drug` (
  `adjudication_id` int NOT NULL,
  `drug_id` int NOT NULL,
  `role` enum('index','concomitant') NOT NULL,
  `attribution` enum('Yes','No','Indeterminate') NOT NULL DEFAULT 'Yes',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` bigint NOT NULL,
  `entity_type` varchar(64) NOT NULL,
  `entity_id` bigint NOT NULL,
  `field` varchar(128) NOT NULL,
  `old_value` text,
  `new_value` text,
  `user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `case_event`
--

CREATE TABLE `case_event` (
  `id` int NOT NULL,
  `patient_id` int NOT NULL,
  `dict_event_id` int NOT NULL,
  `onset_datetime` datetime DEFAULT NULL,
  `status` enum('open','info_requested','submitted','consensus','closed') NOT NULL DEFAULT 'open',
  `phenotype_override` varchar(512) DEFAULT NULL,
  `is_lab_primary` tinyint(1) DEFAULT NULL,
  `notes` text,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consensus`
--

CREATE TABLE `consensus` (
  `id` int NOT NULL,
  `case_event_id` int NOT NULL,
  `method` enum('majority','meeting','arbitration') NOT NULL,
  `decided_by` int DEFAULT NULL,
  `causality` enum('Definite','Probable','Possible','Unrelated','Unable') NOT NULL,
  `severity` enum('Mild','Moderate','Severe') NOT NULL,
  `expectedness` enum('Expected','Unexpected','Not_Assessable') NOT NULL,
  `suspected_drugs` json DEFAULT NULL,
  `rationale` text,
  `decided_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dictionary_event`
--

CREATE TABLE `dictionary_event` (
  `id` int NOT NULL,
  `diagnosis` varchar(512) NOT NULL,
  `category` varchar(255) DEFAULT NULL,
  `ctcae_term` varchar(255) DEFAULT NULL,
  `admission_grade` varchar(64) DEFAULT NULL,
  `icd10` varchar(255) DEFAULT NULL,
  `icd10_norm` varchar(255) GENERATED ALWAYS AS (coalesce(`icd10`,_utf8mb4'')) STORED,
  `source` enum('ICD','LAB') NOT NULL,
  `caveat1` text,
  `outcome1` text,
  `caveat2` text,
  `outcome2` text,
  `caveat3` text,
  `outcome3` text,
  `lcat1` text,
  `lcat1_met1` text,
  `lcat1_met2` text,
  `lcat1_notmet` text,
  `lcat2` text,
  `lcat2_met1` text,
  `lcat2_notmet` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `drugs`
--

CREATE TABLE `drugs` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `class` varchar(255) DEFAULT NULL,
  `genes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `drug_event_map`
--

CREATE TABLE `drug_event_map` (
  `drug_id` int NOT NULL,
  `dict_event_id` int NOT NULL,
  `expected_flag` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evidence_icd`
--

CREATE TABLE `evidence_icd` (
  `id` int NOT NULL,
  `case_event_id` int NOT NULL,
  `icd_code` varchar(64) NOT NULL,
  `event_date` date DEFAULT NULL,
  `encounter_id` varchar(64) DEFAULT NULL,
  `details` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evidence_lab`
--

CREATE TABLE `evidence_lab` (
  `id` int NOT NULL,
  `case_event_id` int NOT NULL,
  `test` varchar(128) NOT NULL,
  `value` varchar(64) DEFAULT NULL,
  `units` varchar(32) DEFAULT NULL,
  `ref_low` varchar(32) DEFAULT NULL,
  `ref_high` varchar(32) DEFAULT NULL,
  `threshold_met` tinyint(1) DEFAULT NULL,
  `sample_datetime` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evidence_note`
--

CREATE TABLE `evidence_note` (
  `id` int NOT NULL,
  `case_event_id` int NOT NULL,
  `note_type` varchar(64) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `url` varchar(512) DEFAULT NULL,
  `version` int DEFAULT NULL,
  `uploaded_by` int DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int NOT NULL,
  `patient_code` varchar(64) NOT NULL,
  `randomisation_date` date NOT NULL,
  `followup_end_date` date NOT NULL,
  `index_drug_id` int NOT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_concomitant_drug`
--

CREATE TABLE `patient_concomitant_drug` (
  `patient_id` int NOT NULL,
  `drug_id` int NOT NULL,
  `start_date` date DEFAULT NULL,
  `stop_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `email` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `role` enum('adjudicator','coordinator','chair','admin') NOT NULL DEFAULT 'adjudicator',
  `password_hash` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `adjudication`
--
ALTER TABLE `adjudication`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_adj` (`case_event_id`,`adjudicator_id`),
  ADD KEY `fk_adj_user` (`adjudicator_id`);

--
-- Indexes for table `adjudication_drug`
--
ALTER TABLE `adjudication_drug`
  ADD PRIMARY KEY (`adjudication_id`,`drug_id`),
  ADD KEY `fk_adjdrug_drug` (`drug_id`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `case_event`
--
ALTER TABLE `case_event`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_caseevent_patient` (`patient_id`),
  ADD KEY `fk_caseevent_event` (`dict_event_id`);

--
-- Indexes for table `consensus`
--
ALTER TABLE `consensus`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `case_event_id` (`case_event_id`),
  ADD KEY `fk_cons_user` (`decided_by`);

--
-- Indexes for table `dictionary_event`
--
ALTER TABLE `dictionary_event`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_event` (`diagnosis`,`icd10_norm`,`source`);

--
-- Indexes for table `drugs`
--
ALTER TABLE `drugs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `drug_event_map`
--
ALTER TABLE `drug_event_map`
  ADD PRIMARY KEY (`drug_id`,`dict_event_id`),
  ADD KEY `fk_dem_event` (`dict_event_id`);

--
-- Indexes for table `evidence_icd`
--
ALTER TABLE `evidence_icd`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_eicd_case` (`case_event_id`);

--
-- Indexes for table `evidence_lab`
--
ALTER TABLE `evidence_lab`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_elab_case` (`case_event_id`);

--
-- Indexes for table `evidence_note`
--
ALTER TABLE `evidence_note`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_enote_case` (`case_event_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `patient_code` (`patient_code`),
  ADD KEY `fk_patients_drug` (`index_drug_id`),
  ADD KEY `fk_patients_user` (`created_by`);

--
-- Indexes for table `patient_concomitant_drug`
--
ALTER TABLE `patient_concomitant_drug`
  ADD PRIMARY KEY (`patient_id`,`drug_id`),
  ADD KEY `fk_pcd_drug` (`drug_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `adjudication`
--
ALTER TABLE `adjudication`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `case_event`
--
ALTER TABLE `case_event`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `consensus`
--
ALTER TABLE `consensus`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dictionary_event`
--
ALTER TABLE `dictionary_event`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `drugs`
--
ALTER TABLE `drugs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `evidence_icd`
--
ALTER TABLE `evidence_icd`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `evidence_lab`
--
ALTER TABLE `evidence_lab`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `evidence_note`
--
ALTER TABLE `evidence_note`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `adjudication`
--
ALTER TABLE `adjudication`
  ADD CONSTRAINT `fk_adj_case` FOREIGN KEY (`case_event_id`) REFERENCES `case_event` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_adj_user` FOREIGN KEY (`adjudicator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `adjudication_drug`
--
ALTER TABLE `adjudication_drug`
  ADD CONSTRAINT `fk_adjdrug_adj` FOREIGN KEY (`adjudication_id`) REFERENCES `adjudication` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_adjdrug_drug` FOREIGN KEY (`drug_id`) REFERENCES `drugs` (`id`);

--
-- Constraints for table `case_event`
--
ALTER TABLE `case_event`
  ADD CONSTRAINT `fk_caseevent_event` FOREIGN KEY (`dict_event_id`) REFERENCES `dictionary_event` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_caseevent_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `consensus`
--
ALTER TABLE `consensus`
  ADD CONSTRAINT `fk_cons_case` FOREIGN KEY (`case_event_id`) REFERENCES `case_event` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cons_user` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `drug_event_map`
--
ALTER TABLE `drug_event_map`
  ADD CONSTRAINT `fk_dem_drug` FOREIGN KEY (`drug_id`) REFERENCES `drugs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dem_event` FOREIGN KEY (`dict_event_id`) REFERENCES `dictionary_event` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `evidence_icd`
--
ALTER TABLE `evidence_icd`
  ADD CONSTRAINT `fk_eicd_case` FOREIGN KEY (`case_event_id`) REFERENCES `case_event` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `evidence_lab`
--
ALTER TABLE `evidence_lab`
  ADD CONSTRAINT `fk_elab_case` FOREIGN KEY (`case_event_id`) REFERENCES `case_event` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `evidence_note`
--
ALTER TABLE `evidence_note`
  ADD CONSTRAINT `fk_enote_case` FOREIGN KEY (`case_event_id`) REFERENCES `case_event` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `fk_patients_drug` FOREIGN KEY (`index_drug_id`) REFERENCES `drugs` (`id`),
  ADD CONSTRAINT `fk_patients_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `patient_concomitant_drug`
--
ALTER TABLE `patient_concomitant_drug`
  ADD CONSTRAINT `fk_pcd_drug` FOREIGN KEY (`drug_id`) REFERENCES `drugs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pcd_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
