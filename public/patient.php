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

                <div style="margin-top: 20px; margin-bottom: 20px;">
                    <button id="generate-events" class="btn waves-effect waves-light blue">
                        <i class="material-icons left">autorenew</i>
                        Generate from Dictionary
                    </button>
                </div>

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

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  }

  async function refreshEvents() {
    wrap.innerHTML = '<div class="progress"><div class="indeterminate blue"></div></div><p class="center-align grey-text">Loading events...</p>';

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
            <th class="center-align">Mark Absent</th>
            <th>Status</th>
            <th class="center-align">Adjudications</th>
            <th class="center-align">Actions</th>
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

        // Determine action links based on status and adjudication count
        let actionHtml = '';
        if (status === 'consensus' && adjCount >= 3) {
          actionHtml = `
            <a href="case_event.php?id=${encodeURIComponent(eventId)}" class="btn-small waves-effect waves-light blue">Revise</a>
            <a href="consensus.php?case_event_id=${encodeURIComponent(eventId)}" class="btn-small waves-effect waves-light green">Consensus</a>
          `;
        } else if (adjCount > 0) {
          actionHtml = `
            <a href="case_event.php?id=${encodeURIComponent(eventId)}" class="btn-small waves-effect waves-light orange">Revise</a>
          `;
        } else {
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
            <a href="#" class="btn-small waves-effect waves-light red" onclick="markAbsent(${eventId}); return false;">
              <i class="material-icons tiny">cancel</i>
            </a>
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

  refreshEvents();
});

// Mark event as absent
function markAbsent(eventId) {
  if (!confirm('Mark this event as absent for this patient?')) return;

  // TODO: Implement mark absent functionality
  // This would call an API endpoint to mark the event as absent
  showToast('Mark absent functionality to be implemented', 'info');
}

function showToast(message, type = 'info') {
  const colors = { success: 'green', error: 'red', info: 'blue', warning: 'orange' };
  M.toast({ html: `<i class="material-icons left">info</i>${message}`, classes: colors[type] || 'blue' });
}
</script>

<?php require_once __DIR__ . '/../inc/templates/footer_light.php'; ?>
