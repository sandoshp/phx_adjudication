<?php
require_once __DIR__ . '/../inc/auth.php'; require_login(); $user=current_user();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Dashboard</title>
  <link rel="stylesheet" href="/assets/styles.css">
  <script src="/assets/js/api.js" defer></script>
</head>
<body>
<header>
  <div class="brand">PHOENIX Adjudication</div>
  <nav>
    <span>Logged in as <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['role']) ?>)</span>
    <a href="logout.php">Logout</a>
  </nav>
</header>

<main class="container">
  <section>
    <h2>Patients</h2>
    <div id="patients"></div>
  </section>

  <?php if(in_array($user['role'], ['admin','coordinator'])): ?>
  <section>
    <h2>Import Master Table (CSV)</h2>
    <form method="post" action="../api/import_master.php" enctype="multipart/form-data">
      <input type="file" name="csv" accept=".csv" required>
      <button type="submit">Upload & Import</button>
    </form>
  </section>
  <?php endif; ?>

  <section>
    <h2>Add Patient</h2>
    <form id="add-patient">
      <label>Patient ID
        <input type="text" name="patient_code" required>
      </label>
      <label>Randomisation Date
        <input type="date" name="randomisation_date" required>
      </label>
      <label>Index drug
        <select name="index_drug_id" id="index_drug_id" required>
          <option value="">— Loading… —</option>
        </select>
      </label>
      <button type="submit">Add</button>
    </form>
  </section>

  <!-- NEW: Concomitant drugs -->
  <section class="card">
    <h2>Add Concomitant Drugs</h2>
    <form id="add-concomitants">
      <label>Patient
        <select id="con-patient" name="patient_id" required>
          <option value="">— Loading patients… —</option>
        </select>
      </label>

      <label>Concomitant drugs
        <select id="con-drugs" name="drug_ids[]" multiple size="8" required>
          <option value="">— Loading drugs… —</option>
        </select>
      </label>

      <div class="row">
        <label>Start date (optional)
          <input type="date" name="start_date" id="con-start">
        </label>
        <label>Stop date (optional)
          <input type="date" name="stop_date" id="con-stop">
        </label>
      </div>

      <button type="submit">Add Concomitants</button>
    </form>

    <div id="con-existing" class="mt-2"></div>
  </section>
</main>

<script>
let DRUGS_CACHE = [];
let PATIENTS_CACHE = [];

document.addEventListener('DOMContentLoaded', () => {
  loadDrugs();
  loadPatients();
  wireAddPatientForm();
  wireConcomitantForm();
  refreshPatients(); // cards list
});

/* ---------- Shared loaders ---------- */
function loadDrugs() {
  const indexSel = document.getElementById('index_drug_id');
  const conSel   = document.getElementById('con-drugs');
  if (indexSel) indexSel.disabled = true;
  if (conSel)   conSel.disabled   = true;
  fetch('../api/drugs.php', { credentials:'same-origin', headers:{'Accept':'application/json'}, cache:'no-store' })
    .then(r => { if(!r.ok) throw new Error('drugs HTTP '+r.status); return r.json(); })
    .then(list => {
      DRUGS_CACHE = (Array.isArray(list)?list:[]).sort((a,b)=>a.name.localeCompare(b.name));
      if (indexSel) {
        indexSel.innerHTML = '<option value="">— Select index drug —</option>' +
          DRUGS_CACHE.map(d=>`<option value="${d.id}">${escapeHtml(d.name)}</option>`).join('');
        indexSel.disabled = false;
      }
      if (conSel) {
        // options actually rendered when a patient is chosen (exclude index drug)
        conSel.innerHTML = '<option value="">— Select a patient first —</option>';
      }
    })
    .catch(e=>{
      console.error(e);
      if (indexSel) indexSel.innerHTML = '<option value="">Failed to load</option>';
      if (conSel)   conSel.innerHTML   = '<option value="">Failed to load</option>';
    });
}

function loadPatients() {
  const patientSel = document.getElementById('con-patient');
  fetch('../api/patients.php', { credentials:'same-origin', headers:{'Accept':'application/json'}, cache:'no-store' })
    .then(r => { if(!r.ok) throw new Error('patients HTTP '+r.status); return r.json(); })
    .then(list => {
      PATIENTS_CACHE = Array.isArray(list)?list:[];
      if (!patientSel) return;
      patientSel.innerHTML = '<option value="">— Select patient —</option>' +
        PATIENTS_CACHE.map(p => {
          const idx = p.index_drug_id ?? '';
          const label = `${escapeHtml(p.patient_code)} — Index: ${escapeHtml(p.index_drug_name ?? '')}`;
          return `<option value="${p.id}" data-index="${idx}">${label}</option>`;
        }).join('');
    })
    .catch(e=>{
      console.error(e);
      if (patientSel) patientSel.innerHTML = '<option value="">Failed to load patients</option>';
    });
}

/* ---------- Add patient (existing) ---------- */
function wireAddPatientForm() {
  const form = document.getElementById('add-patient');
  if (!form) return;
  const btn = form.querySelector('button[type="submit"]');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    const payload = Object.fromEntries(fd.entries());
    if (payload.index_drug_id) payload.index_drug_id = Number(payload.index_drug_id);
    if (!payload.patient_code || !payload.randomisation_date || !payload.index_drug_id) {
      alert('Please complete all fields.'); return;
    }
    try {
      btn.disabled = true;
      const res = await fetch('../api/patients.php', {
        method:'POST', credentials:'same-origin',
        headers:{'Accept':'application/json','Content-Type':'application/json'},
        body: JSON.stringify(payload)
      });
      if (!res.ok) throw new Error('HTTP '+res.status);
      const out = await res.json();
      if (!out.ok) throw new Error(out.error || 'Add failed');
      form.reset();
      document.getElementById('index_drug_id').value = '';
      await Promise.all([loadPatients(), refreshPatients()]);
    } catch (err) {
      console.error(err); alert('Failed to add patient.');
    } finally { btn.disabled = false; }
  });
}

/* ---------- Concomitant form (NEW) ---------- */
function wireConcomitantForm() {
  const form       = document.getElementById('add-concomitants');
  const patientSel = document.getElementById('con-patient');
  const drugSel    = document.getElementById('con-drugs');
  const existing   = document.getElementById('con-existing');

  if (!form || !patientSel || !drugSel) return;

  patientSel.addEventListener('change', async () => {
    renderConDrugOptions();
    existing.innerHTML = '';
    const pid = Number(patientSel.value || 0);
    if (pid > 0) await loadExistingConcomitants(pid);
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const pid = Number(patientSel.value || 0);
    if (!pid) { alert('Select a patient.'); return; }
    const selected = Array.from(drugSel.selectedOptions).map(o => Number(o.value)).filter(v=>v>0);
    if (!selected.length) { alert('Select at least one drug.'); return; }
    const start = document.getElementById('con-start').value || null;
    const stop  = document.getElementById('con-stop').value  || null;

    try {
      const res = await fetch('../api/concomitants.php', {
        method:'POST', credentials:'same-origin',
        headers:{'Accept':'application/json','Content-Type':'application/json'},
        body: JSON.stringify({ patient_id: pid, drug_ids: selected, start_date: start, stop_date: stop })
      });
      const out = await res.json().catch(()=>({ok:false,error:'Bad JSON'}));
      if (!res.ok || !out.ok) throw new Error(out.error || ('HTTP '+res.status));
      await loadExistingConcomitants(pid);
      // keep selection but clear dates
      document.getElementById('con-start').value = '';
      document.getElementById('con-stop').value  = '';
    } catch (err) {
      console.error(err);
      alert('Failed to add concomitants.');
    }
  });

  function renderConDrugOptions() {
    const idx = Number(patientSel.selectedOptions[0]?.dataset.index || 0);
    if (!DRUGS_CACHE.length) {
      drugSel.innerHTML = '<option value="">— Load drugs first —</option>'; return;
    }
    drugSel.innerHTML = DRUGS_CACHE.map(d => {
      const disabled = (idx && d.id === idx) ? ' disabled' : '';
      return `<option value="${d.id}"${disabled}>${escapeHtml(d.name)}</option>`;
    }).join('');
    drugSel.disabled = false;
  }

  async function loadExistingConcomitants(pid) {
    existing.textContent = 'Loading existing concomitants…';
    try {
      const r = await fetch(`../api/concomitants.php?patient_id=${encodeURIComponent(pid)}`, {
        credentials:'same-origin', headers:{'Accept':'application/json'}, cache:'no-store'
      });
      if (!r.ok) throw new Error('HTTP '+r.status);
      const rows = await r.json();
      if (!Array.isArray(rows) || rows.length === 0) {
        existing.textContent = 'No concomitants recorded.'; return;
      }
      const list = document.createElement('div');
      rows.forEach(x => {
        const el = document.createElement('div');
        el.className = 'list-item';
        el.textContent = `${x.name} ${x.start_date ? '('+x.start_date : ''}${x.stop_date ? ' → '+x.stop_date : ''}${(x.start_date||x.stop_date)?')':''}`;
        list.appendChild(el);
      });
      existing.innerHTML = '';
      existing.appendChild(list);
    } catch (e) {
      console.error(e);
      existing.textContent = 'Failed to load existing concomitants.';
    }
  }
}

/* ---------- Patients list (existing) ---------- */
async function refreshPatients() {
  const container = document.getElementById('patients');
  if (!container) return;
  container.textContent = 'Loading patients…';
  try {
    const res = await fetch('../api/patients.php', { credentials:'same-origin', headers:{'Accept':'application/json'}, cache:'no-store' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const list = await res.json();

    container.innerHTML = '';
    (list || []).forEach(p => {
      const el = document.createElement('div');
      el.className = 'card';
      const indexLabel = p.index_drug_name ?? p.index_drug_id ?? '—';
      el.innerHTML = `
        <strong>${escapeHtml(p.patient_code ?? '')}</strong>
        — Index: ${escapeHtml(String(indexLabel))}
        — Randomised: ${escapeHtml(p.randomisation_date ?? '')}
        — FU End: ${escapeHtml(p.followup_end_date ?? '')}
        <div><a href="patient.php?id=${encodeURIComponent(p.id)}">Open</a></div>
      `;
      container.appendChild(el);
    });
    if (!list || list.length === 0) container.textContent = 'No patients yet.';
  } catch (e) {
    console.error('Failed to load patients', e);
    container.textContent = 'Failed to load patients.';
  }
}

function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}
</script>
</body>
</html>
