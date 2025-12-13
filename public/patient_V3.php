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

/* --- Build per-patient DRUGS list (index + concomitants) for grouping --- */
$patient_drugs = [];
$patient_drugs[] = [
  'id'   => (int)$p['index_drug_id'],
  'name' => $p['index_drug'],
  'type' => 'index'
];
foreach ($concomitants as $c) {
  $patient_drugs[] = ['id'=>(int)$c['id'], 'name'=>$c['name'], 'type'=>'concomitant'];
}
// ensure unique by id (in case of duplicates)
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
// List of this patient's drugs (index first), as [{id,name,type}]
const PATIENT_DRUGS  = <?= json_encode($patient_drugs, JSON_UNESCAPED_UNICODE) ?>;
// Map of dict_event_id → [drug_id,…] but only for this patient's drugs
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
    // server already generates for ALL patient drugs via drug_event_map
    if (window.api?.generateCaseEvents) return api.generateCaseEvents(patientId);
    const r = await fetch('../api/case_events.php', {
      method:'POST', credentials:'same-origin',
      headers:{ 'Accept':'application/json', 'Content-Type':'application/json' },
      body: JSON.stringify({ action:'generate', patient_id: patientId })
    });
    if (!r.ok) throw new Error(`POST ../api/case_events.php -> HTTP ${r.status}`);
    return r.json();
  }

  async function refreshEvents() {
    wrap.textContent = 'Loading events…';
    try {
      const events = await fetchEvents();
      wrap.innerHTML = '';
      if (!Array.isArray(events) || events.length === 0) { wrap.textContent = 'No events yet.'; return; }

      // We need dict_event_id from the API to group by drug.
      const missingDictId = events.some(ev => ev.dict_event_id == null);
      if (missingDictId) {
        console.warn('case_events.php should include ce.dict_event_id AS dict_event_id for grouping. Falling back to flat list.');
        renderFlat(events);
        return;
      }

      renderGroupedByDrug(events);
    } catch (e) {
      console.error('Failed to load events:', e);
      wrap.innerHTML = '<div class="error">Failed to load events.</div>';
    }
  }

  function renderFlat(events) {
    events.forEach(ev => {
      const el = document.createElement('div');
      el.className = 'list-item';
      const badge = Number(ev.has_consensus) === 1 ? '✅' : (Number(ev.adjudications_count) >= 3 ? '⏳' : '📝');
      el.innerHTML = `
        ${badge} ${escapeHtml(ev.category || '')}
        — <strong>${escapeHtml(ev.diagnosis || '')}</strong>
        (${escapeHtml(ev.source || '')}${ev.icd10 ? ' / ' + escapeHtml(ev.icd10) : ''})
        <div><a href="case_event.php?id=${encodeURIComponent(ev.id)}">Adjudicate</a></div>
      `;
      wrap.appendChild(el);
    });
  }

  function renderGroupedByDrug(events) {
    // Build map drug_id → events[]
    const eventsByDrug = new Map();
    PATIENT_DRUGS.forEach(d => eventsByDrug.set(d.id, []));
    for (const ev of events) {
      const deid = ev.dict_event_id;
      const drugIds = EVENT_DRUG_MAP[String(deid)] || [];
      drugIds.forEach(did => {
        if (eventsByDrug.has(did)) eventsByDrug.get(did).push(ev);
      });
    }

    // Order: index drug first, then concomitants alphabetically by name
    const orderedDrugs = [
      ...PATIENT_DRUGS.filter(d => d.type === 'index'),
      ...PATIENT_DRUGS.filter(d => d.type === 'concomitant').sort((a,b)=>a.name.localeCompare(b.name))
    ];

    orderedDrugs.forEach(d => {
      const group = eventsByDrug.get(d.id) || [];
      const section = document.createElement('div');
      section.className = 'group';
      const header = document.createElement('h3');
      header.textContent = d.type === 'index' ? `${d.name} (Index)` : d.name;
      section.appendChild(header);

      if (!group.length) {
        const em = document.createElement('div');
        em.className = 'muted';
        em.textContent = 'No endpoints for this drug.';
        section.appendChild(em);
      } else {
        group.forEach(ev => {
          const el = document.createElement('div');
          el.className = 'list-item';
          const badge = Number(ev.has_consensus) === 1 ? '✅' : (Number(ev.adjudications_count) >= 3 ? '⏳' : '📝');
          el.innerHTML = `
            ${badge} ${escapeHtml(ev.category || '')}
            — <strong>${escapeHtml(ev.diagnosis || '')}</strong>
            (${escapeHtml(ev.source || '')}${ev.icd10 ? ' / ' + escapeHtml(ev.icd10) : ''})
            <div><a href="case_event.php?id=${encodeURIComponent(ev.id)}">Adjudicate</a></div>
          `;
          section.appendChild(el);
        });
      }
      wrap.appendChild(section);
    });
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
    } finally {
      btn.disabled = false;
    }
  });

  refreshEvents();

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, m => (
      {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]
    ));
  }
});
</script>
</body>
</html>
