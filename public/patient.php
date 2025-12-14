<?php
/**
 * PHOENIX Adjudication - Patient Detail Page (Materialize CSS)
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_login();
$user = current_user();

$patient_id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("
  SELECT p.*, d.name AS index_drug
  FROM patients p
  JOIN drugs d ON d.id = p.index_drug_id
  WHERE p.id = ?
");
$st->execute([$patient_id]);
$p = $st->fetch(PDO::FETCH_ASSOC);
if (!$p) { http_response_code(404); echo "Patient not found"; exit; }

/* --- Concomitant drugs for baseline --- */
$cons = $pdo->prepare("
  SELECT d.id, d.name,
         DATE_FORMAT(pcd.start_date, '%Y-%m-%d') AS start_date,
         DATE_FORMAT(pcd.stop_date,  '%Y-%m-%d') AS stop_date
  FROM patient_concomitant_drug pcd
  JOIN drugs d ON d.id = pcd.drug_id
  WHERE pcd.patient_id = ?
  ORDER BY d.name
");
$cons->execute([$patient_id]);
$concomitants = $cons->fetchAll(PDO::FETCH_ASSOC);

/* --- Build per-patient DRUGS list (index + concomitants) --- */
$patient_drugs = [];
$patient_drugs[] = [
  'id'   => (int)$p['index_drug_id'],
  'name' => $p['index_drug'],
  'type' => 'index'
];
foreach ($concomitants as $c) {
  $patient_drugs[] = ['id'=>(int)$c['id'], 'name'=>$c['name'], 'type'=>'concomitant'];
}
// de-dup
$seen = [];
$patient_drugs = array_values(array_filter($patient_drugs, function($d) use (&$seen){
  $k = (string)$d['id']; if(isset($seen[$k])) return false; $seen[$k]=1; return true;
}));

/* --- Map dict_event_id → [drug_id,…] only for THIS patient's drugs --- */
$ids_in = implode(',', array_fill(0, count($patient_drugs), '?'));
$map = [];
if ($ids_in) {
  $m = $pdo->prepare("
    SELECT dem.dict_event_id, dem.drug_id
    FROM drug_event_map dem
    WHERE dem.drug_id IN ($ids_in)
  ");
  $m->execute(array_column($patient_drugs, 'id'));
  foreach ($m->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $deid = (int)$row['dict_event_id']; $did = (int)$row['drug_id'];
    $map[$deid] = $map[$deid] ?? [];
    if (!in_array($did, $map[$deid], true)) $map[$deid][] = $did;
  }
}

$pageTitle = 'Patient: ' . htmlspecialchars($p['patient_code']);
require_once __DIR__ . '/../inc/templates/header_light.php';
?>

<!-- Page Header -->
<div class="row">
    <div class="col s12">
        <h4 class="blue-grey-text text-lighten-2">
            <i class="material-icons left">person</i>
            Patient: <?= htmlspecialchars($p['patient_code']) ?>
        </h4>
        <p class="grey-text">View baseline information and adjudicate case events</p>
    </div>
</div>

<!-- Baseline Information Card -->
<div class="row">
    <div class="col s12">
        <div class="card">
            <div class="card-content">
                <span class="card-title">
                    <i class="material-icons left blue-text">info</i>
                    Baseline Information
                </span>

                <table class="striped">
                    <tbody>
                        <tr>
                            <td><strong>Patient ID</strong></td>
                            <td><?= htmlspecialchars($p['patient_code']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Date of Randomisation</strong></td>
                            <td><?= htmlspecialchars($p['randomisation_date'] ?? '—') ?></td>
                        </tr>
                        <tr>
                            <td><strong>End of Follow-up</strong></td>
                            <td><?= htmlspecialchars($p['followup_end_date'] ?? '—') ?></td>
                        </tr>
                        <tr>
                            <td><strong>Index Drug</strong></td>
                            <td>
                                <span class="chip blue white-text">
                                    <i class="material-icons tiny">medication</i>
                                    <?= htmlspecialchars($p['index_drug']) ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;"><strong>Concomitant Drugs</strong></td>
                            <td>
                                <?php if (!$concomitants): ?>
                                    <span class="grey-text">None</span>
                                <?php else: ?>
                                    <?php foreach ($concomitants as $c): ?>
                                        <div class="chip grey lighten-1">
                                            <i class="material-icons tiny">medication</i>
                                            <?= htmlspecialchars($c['name']) ?>
                                            <?php if ($c['start_date'] || $c['stop_date']): ?>
                                                <span class="grey-text text-darken-2">
                                                    (<?= htmlspecialchars($c['start_date'] ?: '—') ?> → <?= htmlspecialchars($c['stop_date'] ?: '—') ?>)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <br>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Case Events Card -->
<div class="row">
    <div class="col s12">
        <div class="card">
            <div class="card-content">
                <span class="card-title">
                    <i class="material-icons left orange-text">assignment</i>
                    Case Events
                </span>
                <p class="grey-text">Generate events from dictionary and adjudicate outcomes</p>

                <div class="row" style="margin-top: 20px;">
                    <div class="col s12 m6">
                        <button id="generate-events" class="btn waves-effect waves-light blue">
                            <i class="material-icons left">autorenew</i>
                            Generate from Dictionary
                        </button>
                    </div>
                    <div class="input-field col s12 m6">
                        <i class="material-icons prefix">search</i>
                        <input type="text" id="search-events" placeholder="Search events...">
                        <label for="search-events">Search Events</label>
                    </div>
                </div>
                <p class="grey-text">
                    <i class="material-icons tiny">info</i>
                    <small>Click column headers to sort. Use search box to filter results.</small>
                </p>

                <div id="events">
                    <div class="progress">
                        <div class="indeterminate blue"></div>
                    </div>
                    <p class="center-align grey-text">Loading events...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Event Details Modal -->
<div id="event-details-modal" class="modal modal-fixed-footer">
    <div class="modal-content">
        <h4>
            <i class="material-icons left">edit</i>
            Update Event Details — <span id="modal-event-diagnosis"></span>
        </h4>
        <p>Patient: <strong><?= htmlspecialchars($p['patient_code']) ?></strong></p>

        <div id="event-details-form">
            <p class="center-align grey-text">Loading...</p>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn-flat" onclick="M.Modal.getInstance(document.getElementById('event-details-modal')).close()">Cancel</button>
        <button id="save-event-details" class="btn blue" onclick="saveEventDetails()">
            <i class="material-icons left">save</i>
            Save Details
        </button>
    </div>
</div>

<!-- Table Utilities Script -->
<script src="assets/js/table-utils.js"></script>

<script>
const patientId      = <?= json_encode($patient_id) ?>;
// This patient's drugs (index + concomitants)
const PATIENT_DRUGS  = <?= json_encode($patient_drugs, JSON_UNESCAPED_UNICODE) ?>;
// dict_event_id -> [drug_id,...] for this patient
const EVENT_DRUG_MAP = <?= json_encode($map, JSON_UNESCAPED_UNICODE) ?>;

document.addEventListener('DOMContentLoaded', () => {
  const wrap = document.getElementById('events');
  const btn  = document.getElementById('generate-events');

  async function fetchEventsDirect() {
    const urls = [
      `../api/case_events.php?patient_id=${encodeURIComponent(patientId)}`,
      `../api/case_events.php?id=${encodeURIComponent(patientId)}`
    ];
    let lastErr = null;
    for (const u of urls) {
      try {
        console.log('Fetching events from:', u);
        const r = await fetch(u, { credentials:'same-origin', headers:{ 'Accept':'application/json' }, cache:'no-store' });
        console.log('Response status:', r.status, 'Content-Type:', r.headers.get('content-type'));

        if (!r.ok) {
          const text = await r.text();
          console.error(`HTTP ${r.status} response:`, text);
          lastErr = new Error(`HTTP ${r.status}: ${text.substring(0, 100)}`);
          continue;
        }

        const ct = r.headers.get('content-type') || '';
        if (!ct.includes('application/json')) {
          const text = await r.text();
          console.error('Non-JSON response:', text);
          lastErr = new Error(`Non-JSON response (${ct}): ${text.substring(0, 100)}`);
          continue;
        }

        return r.json();
      } catch (e) {
        console.error('Fetch error:', e);
        lastErr = e;
      }
    }
    throw lastErr || new Error('Failed to fetch events');
  }

  async function fetchEvents() {
    if (window.api?.getCaseEvents) {
      try { return await api.getCaseEvents(patientId); }
      catch (e) { console.warn('api.getCaseEvents failed, falling back:', e); }
    }
    return fetchEventsDirect();
  }

  async function generateEvents() {
    if (window.api?.generateCaseEvents) return api.generateCaseEvents(patientId);

    const url = '../api/case_events.php';
    const payload = { action:'generate', patient_id: patientId };
    console.log('Generating events with payload:', payload);

    const r = await fetch(url, {
      method:'POST',
      credentials:'same-origin',
      headers:{ 'Accept':'application/json', 'Content-Type':'application/json' },
      body: JSON.stringify(payload)
    });

    console.log('Generate response status:', r.status);

    if (!r.ok) {
      const text = await r.text();
      console.error(`HTTP ${r.status} response:`, text);
      throw new Error(`HTTP ${r.status}: ${text.substring(0, 100)}`);
    }

    return r.json();
  }

  function drugNameById(id) {
    const f = PATIENT_DRUGS.find(d => Number(d.id) === Number(id));
    return f ? f.name : String(id);
  }

  // Make escapeHtml and refreshEvents globally accessible
  window.escapeHtml = function(s) {
    return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  };

  window.refreshEvents = async function() {
    wrap.innerHTML = '<div class="progress"><div class="indeterminate blue"></div></div><p class="center-align grey-text">Loading events...</p>';

    const escapeHtml = window.escapeHtml; // Local alias

    try {
      const events = await fetchEvents();
      console.log('Loaded events:', events);
      wrap.innerHTML = '';

      if (!Array.isArray(events)) {
        console.error('Events is not an array:', events);
        wrap.innerHTML = '<div class="card-panel orange darken-2 white-text"><i class="material-icons left">warning</i>Unexpected response format. Check console for details.</div>';
        return;
      }

      if (events.length === 0) {
        wrap.innerHTML = '<p class="center-align grey-text">No events yet. Click "Generate from Dictionary" to create events.</p>';
        return;
      }

      // Display all events with required columns
      events.sort((a,b)=> {
        const aName = a.diagnosis || a.category || '';
        const bName = b.diagnosis || b.category || '';
        return aName.localeCompare(bName);
      });

      // Render Materialize table with all required columns
      const table = document.createElement('table');
      table.className = 'striped highlight responsive-table';
      table.innerHTML = `
        <thead>
          <tr>
            <th>Outcome</th>
            <th>Category</th>
            <th>Source</th>
            <th class="center-align">Event Management</th>
            <th>Status</th>
            <th class="center-align">Adjudications</th>
            <th class="center-align">Adjudication Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      `;
      const tb = table.querySelector('tbody');

      events.forEach(ev => {
        const tr = document.createElement('tr');
        const status = ev.status || 'open';
        const adjCount = parseInt(ev.adjudications_count) || 0;
        const eventId = ev.id;
        const isAbsent = ev.is_absent === 1 || ev.is_absent === '1';

        // Event management column (Mark Absent / Undo Absent / Update Details)
        let eventMgmtHtml = '';
        if (isAbsent) {
          eventMgmtHtml = `
            <a href="#" class="btn-small waves-effect waves-light orange"
               onclick="undoAbsent(${eventId}); return false;"
               style="margin-bottom: 4px; display: block; font-size: 10px; padding: 0 8px;">
              <i class="material-icons tiny">undo</i> Undo Absent
            </a>
            <a href="#event-details-modal" class="btn-small waves-effect waves-light blue modal-trigger"
               onclick="openEventDetailsModal(${eventId}); return false;"
               style="font-size: 10px; padding: 0 8px;">
              <i class="material-icons tiny">edit</i> Update Details
            </a>
          `;
        } else {
          eventMgmtHtml = `
            <a href="#" class="btn-small waves-effect waves-light red"
               onclick="markAbsent(${eventId}); return false;"
               style="margin-bottom: 4px; display: block; font-size: 10px; padding: 0 8px;">
              <i class="material-icons tiny">cancel</i> Mark Absent
            </a>
            <a href="#event-details-modal" class="btn-small waves-effect waves-light blue modal-trigger"
               onclick="openEventDetailsModal(${eventId}); return false;"
               style="font-size: 10px; padding: 0 8px;">
              <i class="material-icons tiny">edit</i> Update Details
            </a>
          `;
        }

        // Determine adjudication action links based on adjudication count and absent status
        let actionHtml = '';
        if (isAbsent) {
          // Hide adjudication links if event is marked absent
          actionHtml = '<span class="grey-text">—</span>';
        } else if (adjCount >= 2) {
          // Show Consensus link when there are 2+ adjudications
          actionHtml = `
            <a href="case_event.php?id=${encodeURIComponent(eventId)}" class="btn-small waves-effect waves-light blue">Revise</a>
            <a href="consensus.php?case_event_id=${encodeURIComponent(eventId)}" class="btn-small waves-effect waves-light green">Consensus</a>
          `;
        } else if (adjCount > 0) {
          // Only 1 adjudication - show Revise only
          actionHtml = `
            <a href="case_event.php?id=${encodeURIComponent(eventId)}" class="btn-small waves-effect waves-light orange">Revise</a>
          `;
        } else {
          // No adjudications yet - show Adjudicate
          actionHtml = `
            <a href="case_event.php?id=${encodeURIComponent(eventId)}" class="btn-small waves-effect waves-light blue">Adjudicate</a>
          `;
        }

        // Status badge styling
        let statusBadge = '';
        if (status === 'consensus') {
          statusBadge = '<span class="chip green white-text">Consensus</span>';
        } else if (status === 'submitted') {
          statusBadge = '<span class="chip orange white-text">Submitted</span>';
        } else {
          statusBadge = '<span class="chip grey white-text">Open</span>';
        }

        tr.innerHTML = `
          <td><strong>${escapeHtml(ev.diagnosis || '—')}</strong></td>
          <td>${escapeHtml(ev.category || '—')}</td>
          <td>${escapeHtml(ev.source || '—')}</td>
          <td class="center-align">
            ${eventMgmtHtml}
          </td>
          <td>${statusBadge}</td>
          <td class="center-align"><span class="badge blue white-text">${adjCount}</span></td>
          <td class="center-align">
            ${actionHtml}
          </td>
        `;
        tb.appendChild(tr);
      });

      wrap.appendChild(table);

      // Initialize table sorting and searching
      setTimeout(() => {
        initializeTable('#events table', '#search-events');
      }, 100);
    } catch (e) {
      console.error('Failed to load events:', e);
      wrap.innerHTML = `<div class="card-panel red darken-2 white-text">
        <i class="material-icons left">error</i>
        Failed to load events: ${escapeHtml(e.message)}<br>
        <small>Check browser console (F12) for details.</small>
      </div>`;
    }
  }

  btn.addEventListener('click', async () => {
    const prev = btn.textContent;
    const prevHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="material-icons left">hourglass_empty</i>Generating...';

    try {
      console.log('Generating events for patient:', patientId);
      const out = await generateEvents();
      console.log('Generate response:', out);

      if (!out?.ok) {
        throw new Error(out?.error || 'Generation failed');
      }

      await refreshEvents();

      const count = out.inserted ?? 0;
      if (count === 0) {
        showToast('No new events to generate (all events already exist)', 'info');
      } else {
        showToast(`Generated ${count} new event${count === 1 ? '' : 's'}`, 'success');
      }

      btn.innerHTML = '<i class="material-icons left">check</i>Generated ' + count;
      setTimeout(() => { btn.innerHTML = prevHTML; }, 2000);
    } catch (e) {
      console.error('Generate error:', e);
      showToast('Failed to generate events: ' + e.message, 'error');
      btn.innerHTML = prevHTML;
    } finally {
      btn.disabled = false;
    }
  });

  // Initialize modal
  const modalElem = document.getElementById('event-details-modal');
  M.Modal.init(modalElem, {});

  window.refreshEvents();
});

// Global variables for modal state
let currentEventId = null;
let currentEventData = null;

// Mark event as absent
async function markAbsent(eventId) {
  if (!confirm('Mark this event as absent for this patient?\n\nThis will hide adjudication actions for this event.')) return;

  try {
    const res = await fetch('../api/case_event_details.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        action: 'mark_absent',
        case_event_id: eventId,
        is_absent: 1
      })
    });

    if (!res.ok) {
      const text = await res.text();
      throw new Error(`HTTP ${res.status}: ${text}`);
    }

    const result = await res.json();
    if (!result.ok) {
      throw new Error(result.error || 'Failed to mark absent');
    }

    showToast('Event marked as absent', 'success');
    await window.refreshEvents(); // Reload the events table
  } catch (err) {
    console.error('Mark absent error:', err);
    showToast('Failed to mark absent: ' + err.message, 'error');
  }
}

// Undo absent status for an event
async function undoAbsent(eventId) {
  if (!confirm('Restore this event?\n\nThis will re-enable adjudication actions for this event.')) return;

  try {
    const res = await fetch('../api/case_event_details.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        action: 'mark_absent',
        case_event_id: eventId,
        is_absent: 0
      })
    });

    if (!res.ok) {
      const text = await res.text();
      throw new Error(`HTTP ${res.status}: ${text}`);
    }

    const result = await res.json();
    if (!result.ok) {
      throw new Error(result.error || 'Failed to undo absent');
    }

    showToast('Event restored successfully', 'success');
    await window.refreshEvents(); // Reload the events table
  } catch (err) {
    console.error('Undo absent error:', err);
    showToast('Failed to undo absent: ' + err.message, 'error');
  }
}

async function openEventDetailsModal(eventId) {
  currentEventId = eventId;
  const modal = M.Modal.getInstance(document.getElementById('event-details-modal'));
  const formContainer = document.getElementById('event-details-form');
  const escapeHtml = window.escapeHtml; // Local alias

  formContainer.innerHTML = '<div class="progress"><div class="indeterminate blue"></div></div><p class="center-align grey-text">Loading event details...</p>';

  try {
    const res = await fetch(`../api/case_event_details.php?case_event_id=${eventId}`, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    });

    if (!res.ok) {
      const text = await res.text();
      throw new Error(`HTTP ${res.status}: ${text}`);
    }

    const data = await res.json();
    if (!data.ok) {
      throw new Error(data.error || 'Failed to load details');
    }

    currentEventData = data;

    // Update modal title
    document.getElementById('modal-event-diagnosis').textContent = data.event.diagnosis || 'Unknown Event';

    // Build form based on source type
    let formHtml = '';

    if (data.event.source === 'LAB') {
      // Lab event form
      const evidence = data.evidence[0] || {};
      // Pre-populate test name with lcat1 if no evidence exists
      const defaultTestName = evidence.test || data.event.lcat1 || '';
      formHtml = `
        <div class="row">
          <div class="input-field col s12">
            <i class="material-icons prefix">science</i>
            <input type="text" id="test" value="${escapeHtml(defaultTestName)}" required>
            <label for="test" class="active">Test Name *</label>
            <span class="helper-text">Primary laboratory test name</span>
          </div>
        </div>
        <div class="row">
          <div class="input-field col s12 m4">
            <i class="material-icons prefix">trending_up</i>
            <input type="text" id="value" value="${escapeHtml(evidence.value || '')}">
            <label for="value" class="active">Value</label>
          </div>
          <div class="input-field col s12 m4">
            <input type="text" id="units" value="${escapeHtml(evidence.units || '')}">
            <label for="units" class="active">Units</label>
          </div>
          <div class="input-field col s12 m4">
            <label>
              <input type="checkbox" id="threshold_met" ${evidence.threshold_met ? 'checked' : ''} />
              <span>Threshold Met</span>
            </label>
          </div>
        </div>
        <div class="row">
          <div class="input-field col s12 m4">
            <i class="material-icons prefix">show_chart</i>
            <input type="text" id="ref_low" value="${escapeHtml(evidence.ref_low || '')}">
            <label for="ref_low" class="active">Reference Low</label>
          </div>
          <div class="input-field col s12 m4">
            <input type="text" id="ref_high" value="${escapeHtml(evidence.ref_high || '')}">
            <label for="ref_high" class="active">Reference High</label>
          </div>
          <div class="input-field col s12 m4">
            <i class="material-icons prefix">event</i>
            <input type="datetime-local" id="sample_datetime" value="${evidence.sample_datetime ? evidence.sample_datetime.replace(' ', 'T').substring(0, 16) : ''}">
            <label for="sample_datetime" class="active">Sample Date/Time</label>
          </div>
        </div>

        <div class="divider" style="margin: 20px 0;"></div>

        <div class="row">
          <div class="col s12">
            <h6 class="grey-text"><i class="material-icons tiny">info</i> Dictionary Event Information</h6>
            <table class="striped" style="font-size: 0.9em;">
              <tbody>
                <tr><td><strong>Diagnosis:</strong></td><td>${escapeHtml(data.event.diagnosis || '—')}</td></tr>
                <tr><td><strong>Category:</strong></td><td>${escapeHtml(data.event.category || '—')}</td></tr>
                <tr><td><strong>Source:</strong></td><td>${escapeHtml(data.event.source || '—')}</td></tr>
                ${data.event.lcat1 ? `<tr><td><strong>Lab Category 1:</strong></td><td>${escapeHtml(data.event.lcat1)}</td></tr>` : ''}
                ${data.event.lcat1_met1 ? `<tr><td style="padding-left: 20px;"><em>Met Criteria 1:</em></td><td>${escapeHtml(data.event.lcat1_met1)}</td></tr>` : ''}
                ${data.event.lcat1_met2 ? `<tr><td style="padding-left: 20px;"><em>Met Criteria 2:</em></td><td>${escapeHtml(data.event.lcat1_met2)}</td></tr>` : ''}
                ${data.event.lcat1_notmet ? `<tr><td style="padding-left: 20px;"><em>Not Met:</em></td><td>${escapeHtml(data.event.lcat1_notmet)}</td></tr>` : ''}
                ${data.event.lcat2 ? `<tr><td><strong>Lab Category 2:</strong></td><td>${escapeHtml(data.event.lcat2)}</td></tr>` : ''}
                ${data.event.lcat2_met1 ? `<tr><td style="padding-left: 20px;"><em>Met Criteria:</em></td><td>${escapeHtml(data.event.lcat2_met1)}</td></tr>` : ''}
                ${data.event.lcat2_notmet ? `<tr><td style="padding-left: 20px;"><em>Not Met:</em></td><td>${escapeHtml(data.event.lcat2_notmet)}</td></tr>` : ''}
              </tbody>
            </table>
          </div>
        </div>
      `;
    } else if (data.event.source === 'ICD') {
      // ICD event form
      const evidence = data.evidence[0] || {};
      formHtml = `
        <div class="row">
          <div class="input-field col s12 m6">
            <i class="material-icons prefix">local_hospital</i>
            <input type="text" id="icd_code" value="${escapeHtml(evidence.icd_code || data.event.icd10 || '')}" required>
            <label for="icd_code" class="active">ICD Code *</label>
          </div>
          <div class="input-field col s12 m6">
            <i class="material-icons prefix">event</i>
            <input type="date" id="event_date" value="${evidence.event_date || ''}">
            <label for="event_date" class="active">Event Date</label>
          </div>
        </div>
        <div class="row">
          <div class="input-field col s12">
            <i class="material-icons prefix">badge</i>
            <input type="text" id="encounter_id" value="${escapeHtml(evidence.encounter_id || '')}">
            <label for="encounter_id" class="active">Encounter ID</label>
            <span class="helper-text">Hospital/clinic encounter or visit identifier from medical records</span>
          </div>
        </div>
        <div class="row">
          <div class="input-field col s12">
            <i class="material-icons prefix">notes</i>
            <textarea id="details" class="materialize-textarea">${escapeHtml(evidence.details || '')}</textarea>
            <label for="details" class="active">Details</label>
          </div>
        </div>

        <div class="divider" style="margin: 20px 0;"></div>

        <div class="row">
          <div class="col s12">
            <h6 class="grey-text"><i class="material-icons tiny">info</i> Dictionary Event Information</h6>
            <table class="striped" style="font-size: 0.9em;">
              <tbody>
                <tr><td><strong>Diagnosis:</strong></td><td>${escapeHtml(data.event.diagnosis || '—')}</td></tr>
                <tr><td><strong>Category:</strong></td><td>${escapeHtml(data.event.category || '—')}</td></tr>
                <tr><td><strong>Source:</strong></td><td>${escapeHtml(data.event.source || '—')}</td></tr>
                ${data.event.icd10 ? `<tr><td><strong>ICD-10 Code:</strong></td><td>${escapeHtml(data.event.icd10)}</td></tr>` : ''}
                ${data.event.ctcae_term ? `<tr><td><strong>CTCAE Term:</strong></td><td>${escapeHtml(data.event.ctcae_term)}</td></tr>` : ''}
                ${data.event.admission_grade ? `<tr><td><strong>Admission Grade:</strong></td><td>${escapeHtml(data.event.admission_grade)}</td></tr>` : ''}
                ${data.event.caveat1 ? `<tr><td><strong>Caveat 1:</strong></td><td>${escapeHtml(data.event.caveat1)}</td></tr>` : ''}
                ${data.event.outcome1 ? `<tr><td style="padding-left: 20px;"><em>Outcome 1:</em></td><td>${escapeHtml(data.event.outcome1)}</td></tr>` : ''}
                ${data.event.caveat2 ? `<tr><td><strong>Caveat 2:</strong></td><td>${escapeHtml(data.event.caveat2)}</td></tr>` : ''}
                ${data.event.outcome2 ? `<tr><td style="padding-left: 20px;"><em>Outcome 2:</em></td><td>${escapeHtml(data.event.outcome2)}</td></tr>` : ''}
                ${data.event.caveat3 ? `<tr><td><strong>Caveat 3:</strong></td><td>${escapeHtml(data.event.caveat3)}</td></tr>` : ''}
                ${data.event.outcome3 ? `<tr><td style="padding-left: 20px;"><em>Outcome 3:</em></td><td>${escapeHtml(data.event.outcome3)}</td></tr>` : ''}
              </tbody>
            </table>
          </div>
        </div>
      `;
    } else {
      formHtml = '<p class="red-text">Unknown source type: ' + escapeHtml(data.event.source) + '</p>';
    }

    formContainer.innerHTML = formHtml;
    M.updateTextFields(); // Materialize form update

  } catch (err) {
    console.error('Load details error:', err);
    formContainer.innerHTML = `<p class="red-text center-align">Failed to load details: ${escapeHtml(err.message)}</p>`;
  }
}

async function saveEventDetails() {
  if (!currentEventId || !currentEventData) {
    showToast('No event data loaded', 'error');
    return;
  }

  const saveBtn = document.getElementById('save-event-details');
  saveBtn.disabled = true;
  saveBtn.innerHTML = '<i class="material-icons left">hourglass_empty</i>Saving...';

  try {
    let payload = {
      action: 'update_details',
      case_event_id: currentEventId
    };

    if (currentEventData.event.source === 'LAB') {
      payload.test = document.getElementById('test')?.value || '';
      payload.value = document.getElementById('value')?.value || '';
      payload.units = document.getElementById('units')?.value || '';
      payload.ref_low = document.getElementById('ref_low')?.value || '';
      payload.ref_high = document.getElementById('ref_high')?.value || '';
      payload.threshold_met = document.getElementById('threshold_met')?.checked ? 1 : 0;
      payload.sample_datetime = document.getElementById('sample_datetime')?.value || '';
    } else if (currentEventData.event.source === 'ICD') {
      payload.icd_code = document.getElementById('icd_code')?.value || '';
      payload.event_date = document.getElementById('event_date')?.value || '';
      payload.encounter_id = document.getElementById('encounter_id')?.value || '';
      payload.details = document.getElementById('details')?.value || '';
    }

    const res = await fetch('../api/case_event_details.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    });

    if (!res.ok) {
      const text = await res.text();
      throw new Error(`HTTP ${res.status}: ${text}`);
    }

    const result = await res.json();
    if (!result.ok) {
      throw new Error(result.error || 'Failed to save details');
    }

    showToast('Event details saved successfully', 'success');
    M.Modal.getInstance(document.getElementById('event-details-modal')).close();
    await window.refreshEvents(); // Reload the events table

  } catch (err) {
    console.error('Save details error:', err);
    showToast('Failed to save details: ' + err.message, 'error');
  } finally {
    saveBtn.disabled = false;
    saveBtn.innerHTML = '<i class="material-icons left">save</i>Save Details';
  }
}

function showToast(message, type = 'info') {
  const colors = { success: 'green', error: 'red', info: 'blue', warning: 'orange' };
  M.toast({ html: `<i class="material-icons left">info</i>${message}`, classes: colors[type] || 'blue', displayLength: 4000 });
}
</script>

<?php require_once __DIR__ . '/../inc/templates/footer_light.php'; ?>
