# Patient.php Troubleshooting Guide

## Issues Fixed

✅ **Typo**: Fixed `encapeURIComponent` → `encodeURIComponent` (line 304)
✅ **Error Handling**: Added detailed console logging and better error messages
✅ **Debugging**: Now shows actual error details instead of generic messages

---

## How to Diagnose the Issues

### Step 1: Open Browser Console

1. Open patient.php in your browser
2. Press **F12** to open Developer Tools
3. Click the **Console** tab
4. Refresh the page

### Step 2: Check What You See

The console will now show detailed information:

```
Fetching events from: ../api/case_events.php?patient_id=X
Response status: 200 Content-Type: application/json
Loaded events: [...]
```

**Or if there's an error**:

```
HTTP 302 response: (redirect message)
Failed to load events: HTTP 302: (error details)
```

---

## Common Issues & Solutions

### Issue 1: "HTTP 302" or Redirect Error

**Symptom**: Console shows `HTTP 302` or redirects to login/index

**Cause**: Not logged in, or session expired

**Solution**:
1. Go to login page: `http://localhost/phx_adjudication/public/login.php`
2. Log in with valid credentials
3. Navigate back to patient page
4. Refresh

---

### Issue 2: "Failed to fetch events" Network Error

**Symptom**: Console shows network error or CORS error

**Cause**: API file doesn't exist or wrong path

**Solution**:

Check if API file exists:
```cmd
dir C:\xampp\htdocs\phx_adjudication\api\case_events.php
```

Should show:
```
case_events.php
```

If missing, the API file was not included in the repository.

---

### Issue 3: "No events yet" Message

**Symptom**: Page shows "No events yet. Click 'Generate from Dictionary' to create events."

**Cause**: This is **NORMAL** if:
- Patient has no events in database yet
- Events haven't been generated

**Solution**: Click **"Generate from Dictionary"** button

---

### Issue 4: Generate Button Does Nothing

**Symptom**: Click "Generate from Dictionary" but nothing happens

**Check Console For**:

```javascript
Generating events for patient: X
POST ../api/case_events.php
Generate response: {ok: true, inserted: 5}
```

**If you see "inserted: 0"**:
- Events already exist (idempotent - won't create duplicates)
- Patient has no drugs mapped in dictionary
- Database has no drug_event_map entries

**If you see error**:
- Check the error message for details
- Common: "patient_id required" or "Patient not found"

---

### Issue 5: "Patient not found" Error

**Symptom**: API returns 404 or "Patient not found"

**Cause**: Invalid patient ID in URL

**Check**:
```sql
SELECT id, patient_code FROM patients;
```

Make sure the ID in the URL matches an existing patient.

---

### Issue 6: Database Connection Error

**Symptom**: Console shows "Server error" or "Failed to load"

**Check MySQL is running**:
1. Open XAMPP Control Panel
2. MySQL should show **green** "Running"
3. If not, click "Start"

**Check database exists**:
```cmd
cd C:\xampp\mysql\bin
mysql -u root -p
```

```sql
SHOW DATABASES LIKE 'phoenix';
USE phoenix;
SHOW TABLES;
```

Should show tables: `patients`, `case_event`, `dictionary_event`, `drug_event_map`, etc.

---

### Issue 7: No Data in drug_event_map Table

**Symptom**: Generate button returns "inserted: 0" every time, no events ever created

**Cause**: Empty `drug_event_map` table (no drugs mapped to events)

**Check**:
```sql
SELECT COUNT(*) FROM drug_event_map;
```

If returns `0`, the drug-event mapping data is missing.

**Solution**: You need to import the PHOENIX Master Table CSV that maps drugs to outcomes:
1. Go to dashboard
2. Find "Import Master Table" section
3. Upload `PHOENIX_Master_LongTable.csv`
4. This populates `drug_event_map`

---

### Issue 8: Patient Has No Drugs

**Symptom**: Generate returns "inserted: 0"

**Cause**: Patient has no index drug or concomitant drugs

**Check**:
```sql
SELECT p.id, p.patient_code, p.index_drug_id, d.name as index_drug
FROM patients p
LEFT JOIN drugs d ON d.id = p.index_drug_id
WHERE p.id = X;

SELECT pcd.drug_id, d.name
FROM patient_concomitant_drug pcd
JOIN drugs d ON d.id = pcd.drug_id
WHERE pcd.patient_id = X;
```

**Solution**: Patient needs at least one drug (index or concomitant) to generate events.

---

## Complete Diagnostic Checklist

Run through this checklist and report what you find:

### Database Checks

```sql
-- 1. Patient exists?
SELECT id, patient_code, index_drug_id FROM patients WHERE id = YOUR_PATIENT_ID;

-- 2. Patient has index drug?
SELECT d.id, d.name FROM drugs d
JOIN patients p ON p.index_drug_id = d.id
WHERE p.id = YOUR_PATIENT_ID;

-- 3. Patient has concomitant drugs?
SELECT d.id, d.name FROM patient_concomitant_drug pcd
JOIN drugs d ON d.id = pcd.drug_id
WHERE pcd.patient_id = YOUR_PATIENT_ID;

-- 4. Drug-event map populated?
SELECT COUNT(*) as total_mappings FROM drug_event_map;

-- 5. Events mappable for this patient's drugs?
SELECT DISTINCT de.diagnosis, de.category
FROM drug_event_map dem
JOIN dictionary_event de ON de.id = dem.dict_event_id
WHERE dem.drug_id IN (
  SELECT index_drug_id FROM patients WHERE id = YOUR_PATIENT_ID
  UNION
  SELECT drug_id FROM patient_concomitant_drug WHERE patient_id = YOUR_PATIENT_ID
)
LIMIT 10;

-- 6. Existing case events for this patient?
SELECT ce.id, de.diagnosis, ce.status
FROM case_event ce
JOIN dictionary_event de ON de.id = ce.dict_event_id
WHERE ce.patient_id = YOUR_PATIENT_ID;
```

### Browser Checks

- [ ] Open patient.php in browser
- [ ] Press F12, open Console tab
- [ ] See "Fetching events from..." message
- [ ] See "Response status: 200" or error
- [ ] See "Loaded events: [...]" or error message
- [ ] Click "Generate from Dictionary"
- [ ] See "Generating events for patient: X"
- [ ] See "Generate response: {ok: true, inserted: Y}"
- [ ] See toast notification (success or error)
- [ ] Events table appears or error message shows

### API File Checks

- [ ] File exists: `C:\xampp\htdocs\phx_adjudication\api\case_events.php`
- [ ] XAMPP Apache running (green)
- [ ] XAMPP MySQL running (green)
- [ ] Can access directly: `http://localhost/phx_adjudication/api/case_events.php?patient_id=1`

---

## Expected Behavior

### When Loading Page

**Console Output**:
```
Fetching events from: ../api/case_events.php?patient_id=1
Response status: 200 Content-Type: application/json
Loaded events: Array(5) [...]
```

**Page Shows**:
- Table with events (if any exist)
- OR "No events yet" message (if none exist)

### When Clicking Generate

**Console Output**:
```
Generating events for patient: 1
Generate response: {ok: true, inserted: 3}
Fetching events from: ../api/case_events.php?patient_id=1
Response status: 200 Content-Type: application/json
Loaded events: Array(3) [...]
```

**Page Shows**:
- Green toast: "Generated 3 new events"
- Events table appears with 3 rows
- Each row has "Adjudicate" button

### When Clicking Generate (Already Generated)

**Console Output**:
```
Generating events for patient: 1
Generate response: {ok: true, inserted: 0}
```

**Page Shows**:
- Blue toast: "No new events to generate (all events already exist)"
- Existing events table remains

---

## Quick Test

**Create a test patient with events**:

```sql
-- 1. Ensure you have at least one drug
INSERT INTO drugs (name, generic_name, active) VALUES ('Test Drug A', 'test-a', 1);
SET @drug_id = LAST_INSERT_ID();

-- 2. Create test patient
INSERT INTO patients (patient_code, randomisation_date, index_drug_id, active)
VALUES ('TEST001', '2024-01-01', @drug_id, 1);
SET @patient_id = LAST_INSERT_ID();

-- 3. Ensure dictionary has events
SELECT COUNT(*) FROM dictionary_event;  -- Should be > 0

-- 4. Map drug to some events
INSERT INTO drug_event_map (drug_id, dict_event_id)
SELECT @drug_id, id FROM dictionary_event LIMIT 5;

-- 5. Get the patient ID
SELECT @patient_id;
```

Now visit:
```
http://localhost/phx_adjudication/public/patient.php?id=YOUR_PATIENT_ID
```

Click "Generate from Dictionary" → Should create 5 events

---

## What to Report Back

Please provide:

1. **Browser Console Output** (copy/paste from F12 Console)
2. **What you see on the page** (screenshot or description)
3. **Results of database checks** (run the SQL queries above)
4. **Any error messages** (from page or console)

This will help me identify exactly what's wrong!

---

**Git Commit**: `ce85981`
**Status**: Debugging improvements added
**Next**: Test and report findings
