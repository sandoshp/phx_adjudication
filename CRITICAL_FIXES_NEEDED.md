# Critical Fixes Needed - Summary

## 1. Theme Change (DONE)
✅ Created `assets/css/theme-light.css` - clean, readable light theme
✅ Created `inc/templates/header_light.php` - light theme header

## 2. Dashboard - Patients List

**Issue**: Dashboard doesn't show patients in a table format like the screenshot

**Required Changes to `dashboard_materialize.php`**:
- Replace card-based patient display with proper DataTable
- Show columns: Patient ID, Index Drug, Randomisation, Follow-up End, Concomitant Drugs, Actions
- Actions column should have "OPEN" link and "ADD CONCOMITANT DRUGS" link
- Use Materialize table with striped highlighting

**Code Structure Needed**:
```javascript
// Build table with proper columns
<table class="striped highlight responsive-table">
  <thead>
    <tr>
      <th>Patient ID</th>
      <th>Index Drug</th>
      <th>Randomisation</th>
      <th>Follow-up End</th>
      <th>Concomitant Drugs</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody id="patients-tbody">
    // JavaScript populated
  </tbody>
</table>
```

## 3. Patient.php - Case Events Table

**Issue**: Case events section doesn't match the screenshot structure

**Required Changes**:
- Show proper table with columns: Outcome, Category, Source, Mark Absent, Status, Adjudications, Actions
- Status should show: "open", "consensus", etc.
- Adjudications column should show count (0, 1, 2, etc.)
- Actions column logic:
  - If status="consensus" AND adjudications >= 3: Show "Revise | Consensus"
  - If status="open" AND adjudications > 0: Show "Revise"
  - If status="open" AND adjudications == 0: Show "Adjudicate"
- Mark Absent should be a link to mark the event as absent

**Code Logic Needed**:
```javascript
function getActionLinks(event) {
  const status = event.status || 'open';
  const count = event.adjudications_count || 0;

  if (status === 'consensus' && count >= 3) {
    return `<a href="case_event.php?id=${event.id}">Revise</a> | <a href="consensus.php?id=${event.id}">Consensus</a>`;
  } else if (count > 0) {
    return `<a href="case_event.php?id=${event.id}">Revise</a>`;
  } else {
    return `<a href="case_event.php?id=${event.id}">Adjudicate</a>`;
  }
}
```

## 4. case_event.php - Save Not Working

**Issue**: Form submissions not saving to database

**Root Cause Analysis**:
- Form method is POST to `../api/adjudications.php`
- API expects JSON or form-encoded data
- Current form is standard HTML form (should work with form-encoded)
- API checks for required fields: case_event_id, causality, severity, expectedness, index_attribution

**Fixes Needed**:
1. Ensure form has correct field names matching API expectations
2. Add AJAX submission handler OR ensure form posts correctly
3. Add success/error feedback

**Evidence Section - NEW REQUIREMENT**:
- Add a section showing dictionary_event data to help with adjudication
- Load from dictionary_event table for the specific dict_event_id
- Show: outcome1, outcome2, outcome3, caveat1, caveat2, caveat3
- These should populate into evidence fields for the adjudicator to review

**Database Logic**:
```sql
-- Get dictionary event data
SELECT outcome1, outcome2, outcome3, caveat1, caveat2, caveat3,
       lcat1, lcat1_met1, lcat1_met2, lcat1_notmet,
       lcat2, lcat2_met1, lcat2_notmet
FROM dictionary_event
WHERE id = (SELECT dict_event_id FROM case_event WHERE id = ?)
```

These values should be displayed prominently to guide adjudication decisions.

## 5. consensus.php - Not Working

**Issue**: Form doesn't submit to API correctly

**Root Cause**:
- Current form posts to `../api/consensus.php`
- API is minified/condensed code that expects:
  - case_event_id (from POST)
  - rationale (from POST)
  - Checks for 3+ adjudications
  - Computes majority causality, severity, expectedness

**Fixes Needed**:
1. Ensure form has case_event_id in hidden field
2. Add feedback after submission
3. Show current adjudications count
4. Show what the majority will be (preview)
5. Redirect back with success message

**Required Display**:
Before computing consensus, show:
- Number of adjudications submitted (must be >= 3)
- Preview of majority for each field
- List of adjudicators who submitted
- Their individual causality decisions

## 6. Permissions

**Current Permission Issues**:
- consensus.php requires: chair, coordinator, admin (CORRECT)
- adjudications require: any logged-in user (NEEDS FIX)

**Required Roles**:
- **admin**: Full access to everything
- **coordinator**: Can import, view all, compute consensus
- **chair**: Can compute consensus, view all adjudications
- **adjudicator** / **reviewer**: Can submit adjudications only
- **viewer**: Read-only access (if exists)

**Fixes Needed**:
Add role check to case_event.php:
```php
// Allow adjudicators, reviewers, admins, coordinators
if (!in_array($user['role'], ['admin', 'coordinator', 'adjudicator', 'reviewer', 'chair'])) {
    http_response_code(403);
    echo "You do not have permission to adjudicate cases";
    exit;
}
```

## 7. Template Updates

All pages must use: `require_once __DIR__ . '/../inc/templates/header_light.php';`

Pages to update:
- dashboard_materialize.php
- patient.php
- case_event.php
- consensus.php
- login.php (can keep its own styling)

## Implementation Priority

### HIGH PRIORITY (Do These First):
1. ✅ Create light theme CSS
2. ✅ Create light theme header
3. **Update all pages to use header_light.php instead of header_fixed.php**
4. **Fix patient.php case events table to show all columns correctly**
5. **Fix case_event.php form submission to actually save**
6. **Add evidence section to case_event.php**

### MEDIUM PRIORITY:
7. Fix dashboard patients table layout
8. Fix consensus.php to work properly
9. Add proper permissions checks

### LOW PRIORITY:
10. Polish UI elements
11. Add loading states
12. Add better error messages

## Files to Create/Update

### To Create:
- [x] `assets/css/theme-light.css`
- [x] `inc/templates/header_light.php`
- [ ] `public/patient_v2.php` (updated version with correct table)
- [ ] `public/case_event_v2.php` (updated with evidence section and working save)
- [ ] `public/dashboard_v2.php` (updated with proper patients table)
- [ ] `public/consensus_v2.php` (updated with working submission)

### To Update:
- [ ] All existing pages to use `header_light.php`

## Testing Checklist

After fixes:
- [ ] Login works
- [ ] Dashboard shows all patients in table
- [ ] Click "OPEN" on patient goes to patient.php
- [ ] patient.php shows all case events with correct columns
- [ ] Click "Adjudicate" goes to case_event.php
- [ ] case_event.php shows evidence section
- [ ] Fill out adjudication form and submit - saves to database
- [ ] After 3 adjudications, "Consensus" link appears
- [ ] consensus.php shows adjudications and computes majority
- [ ] After consensus, status changes to "consensus"
- [ ] "Revise" link allows editing adjudication

## SQL to Check Database State

```sql
-- Check if adjudications are saving
SELECT * FROM adjudication ORDER BY submitted_at DESC LIMIT 10;

-- Check case_event statuses
SELECT ce.id, de.diagnosis, ce.status, COUNT(a.id) as adj_count
FROM case_event ce
JOIN dictionary_event de ON de.id = ce.dict_event_id
LEFT JOIN adjudication a ON a.case_event_id = ce.id
WHERE ce.patient_id = 1
GROUP BY ce.id;

-- Check consensus records
SELECT * FROM consensus ORDER BY decided_at DESC LIMIT 10;
```

## Next Steps

Given the complexity, I recommend:

1. **First**: Update all pages to use light theme (quick win)
2. **Second**: Fix case_event.php saving (critical functionality)
3. **Third**: Fix patient.php table display (user-facing issue)
4. **Fourth**: Fix consensus.php (completes workflow)
5. **Fifth**: Update dashboard table (polish)

Would you like me to:
A) Fix all critical issues in new v2 files you can test?
B) Update existing files in place?
C) Create one working example (e.g., patient.php fully fixed) then you decide?

Let me know your preference and I'll proceed systematically.
