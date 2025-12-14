-- Add is_absent field to case_event table
-- This field indicates whether the event is absent for the patient
--
-- To run this migration:
-- Via phpMyAdmin: Execute this SQL in the SQL tab for your database
-- Via command line: mysql -u root -p your_database < migrations/add_is_absent_to_case_event.sql

ALTER TABLE case_event ADD COLUMN is_absent TINYINT(1) NOT NULL DEFAULT 0 AFTER status;

-- Add index for faster querying
CREATE INDEX idx_case_event_is_absent ON case_event(is_absent);
