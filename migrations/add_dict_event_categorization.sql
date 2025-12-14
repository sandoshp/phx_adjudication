-- Add CTCAE v5 and v6 dictionary event categorization to evidence tables
-- This allows linking each case event evidence to specific CTCAE v5 and v6 dictionary events

-- Update evidence_icd table
ALTER TABLE evidence_icd
  ADD COLUMN dict_event_v5_id INT NULL AFTER case_event_id,
  ADD COLUMN dict_event_v6_id INT NULL AFTER dict_event_v5_id,
  ADD CONSTRAINT fk_eicd_dict_v5 FOREIGN KEY (dict_event_v5_id) REFERENCES dictionary_event(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_eicd_dict_v6 FOREIGN KEY (dict_event_v6_id) REFERENCES dictionary_event(id) ON DELETE SET NULL;

-- Update evidence_lab table
ALTER TABLE evidence_lab
  ADD COLUMN dict_event_v5_id INT NULL AFTER case_event_id,
  ADD COLUMN dict_event_v6_id INT NULL AFTER dict_event_v5_id,
  ADD CONSTRAINT fk_elab_dict_v5 FOREIGN KEY (dict_event_v5_id) REFERENCES dictionary_event(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_elab_dict_v6 FOREIGN KEY (dict_event_v6_id) REFERENCES dictionary_event(id) ON DELETE SET NULL;

-- Add indexes for faster lookups
CREATE INDEX idx_evidence_icd_dict_v5 ON evidence_icd(dict_event_v5_id);
CREATE INDEX idx_evidence_icd_dict_v6 ON evidence_icd(dict_event_v6_id);
CREATE INDEX idx_evidence_lab_dict_v5 ON evidence_lab(dict_event_v5_id);
CREATE INDEX idx_evidence_lab_dict_v6 ON evidence_lab(dict_event_v6_id);
