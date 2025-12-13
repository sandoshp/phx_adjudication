<?php
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
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Patient <?= htmlspecialchars($p['patient_code']) ?></title>
  <link rel="stylesheet" href="/assets/styles.css">
  <script src="assets/js/api.js" defer></script>
  <style>
    table.outcomes { width:100%; border-collapse: collapse; }
    table.outcomes th, table.outcomes td { border:1px solid #ddd; padding:.5rem; vertical-align: top; }
    table.outcomes th { background:#fafafa; text-align:left; }
    ul.tight { margin:.25rem 0 0; padding-left:1.25rem; }
    .muted { color:#666; }
  </style>
</head>
<body>
<header>
  <div class="brand">PHOENIX Adjudication</div>
  <nav>
    <a href="dashboard.php">Back</a>
    <span>Logged in as <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['role']) ?>)</span>
    <a href="logout.php">Logout</a>
  </nav>
</header>

<main class="container">
  <section class="card">
    <h2>Baseline</h2>
    <p><strong>Patient ID:</strong> <?= htmlspecialchars($p['patient_code']) ?></p>
    <p><strong>Date of Randomisation:</strong> <?= htmlspecialchars($p['randomisation_date']) ?></p>
    <p><strong>End of Follow-up:</strong> <?= htmlspecialchars($p['followup_end_date']) ?></p>
    <p><strong>Index drug:</strong> <?= htmlspecialchars($p['index_drug']) ?></p>
    <p><strong>Concomitant drugs:</strong>
      <?php if (!$concomitants): ?>
        —
      <?php else: ?>
        </p>
        <ul class="tight">
          <?php foreach ($concomitants as $c): ?>
            <li>
              <?= htmlspecialchars($c['name']) ?>
              <?php if ($c['start_date'] || $c['stop_date']): ?>
                (<?= htmlspecialchars($c['start_date'] ?: '—') ?> → <?= htmlspecialchars($c['stop_date'] ?: '—') ?>)
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
  </section>

  <section class="card">
    <h2>Case Events</h2>
    <button id="generate-events">Generate from Dictionary</button>
    <div id="events" class="mt-2"></div>
  </section>
</main>

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
        const r = await fetch(u, { credentials:'same-origin', headers:{ 'Accept':'application/json' }, cache:'no-store' });
        if (!r.ok) { lastErr = new Error(`GET ${u} -> HTTP ${r.status}`); continue; }
        const ct = r.headers.get('content-type') || '';
        if (!ct.includes('application/json')) { lastErr = new Error(`GET ${u} -> non-JSON response`); continue; }
        return r.json();
      } catch (e) { lastErr = e; }
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
    const r = await fetch('../api/case_events.php', {
      method:'POST', credentials:'same-origin',
      headers:{ 'Accept':'application/json', 'Content-Type':'application/json' },
      body: JSON.stringify({ action:'generate', patient_id: patientId })
    });
    if (!r.ok) throw new Error(`POST ../api/case_events.php -> HTTP ${r.status}`);
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
    wrap.textContent = 'Loading events…';
    try {
      const events = await fetchEvents();
      wrap.innerHTML = '';

      if (!Array.isArray(events) || events.length === 0) {
        wrap.textContent = 'No events yet.';
        return;
      }

      // We need dict_event_id for grouping; make sure API includes "ce.dict_event_id AS dict_event_id"
      const missing = events.some(ev => ev.dict_event_id == null);
      if (missing) {
        console.warn('case_events.php should include ce.dict_event_id AS dict_event_id for proper grouping.');
      }

      // Unique by dict_event_id (keep first)
      const unique = new Map();
      for (const ev of events) {
        const key = ev.dict_event_id ?? `ce_${ev.id}`; // fallback
        if (!unique.has(key)) unique.set(key, ev);
      }

      // Build rows: outcome + index drug if mapped + list of concomitants if mapped
      const indexDrug = PATIENT_DRUGS.find(d => d.type === 'index');
      const rows = [];
      unique.forEach(ev => {
        const deid = ev.dict_event_id;
        const mappedDrugIds = EVENT_DRUG_MAP[String(deid)] || [];

        const indexShown = indexDrug && mappedDrugIds.includes(Number(indexDrug.id))
          ? indexDrug.name
          : '—';

        const concNames = PATIENT_DRUGS
          .filter(d => d.type === 'concomitant' && mappedDrugIds.includes(Number(d.id)))
          .map(d => d.name)
          .sort((a,b)=>a.localeCompare(b));

        rows.push({
          outcome: ev.diagnosis || ev.category || '(unnamed)',
          indexDrug: indexShown,
          concomitants: concNames,
          linkId: ev.id
        });
      });

      rows.sort((a,b)=> a.outcome.localeCompare(b.outcome));

      // Render table
      const table = document.createElement('table');
      table.className = 'outcomes';
      table.innerHTML = `
        <thead>
          <tr>
            <th>Outcome</th>
            <th>Index Drug</th>
            <th>Concomitant Drugs</th>
            <th>Adjudicate</th>
          </tr>
        </thead>
        <tbody></tbody>
      `;
      const tb = table.querySelector('tbody');

      rows.forEach(r => {
        const tr = document.createElement('tr');
        const conc = r.concomitants.length ? r.concomitants.join(', ') : '—';
        tr.innerHTML = `
          <td>${escapeHtml(r.outcome)}</td>
          <td>${escapeHtml(r.indexDrug)}</td>
          <td>${escapeHtml(conc)}</td>
          <td><a href="case_event.php?id=${encodeURIComponent(r.linkId)}">Adjudicate</a></td>
        `;
        tb.appendChild(tr);
      });

      wrap.appendChild(table);
    } catch (e) {
      console.error('Failed to load events:', e);
      wrap.innerHTML = '<div class="error">Failed to load events.</div>';
    }
  }

  btn.addEventListener('click', async () => {
    const prev = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Generating…';
    try {
      const out = await generateEvents();
      if (!out?.ok) throw new Error(out?.error || 'Generation failed');
      await refreshEvents();
      btn.textContent = `Generated ${out.inserted ?? 0}`;
      setTimeout(() => { btn.textContent = prev; }, 1500);
    } catch (e) {
      console.error('Generate error:', e);
      alert('Failed to generate events. See console for details.');
      btn.textContent = prev;
    } finally { btn.disabled = false; }
  });

  refreshEvents();
});
</script>
</body>
</html>
