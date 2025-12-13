<?php
require_once __DIR__ . '/../inc/auth.php'; require_login(); $user=current_user();
?><!doctype html><html><head><meta charset="utf-8"><title>Dashboard</title><link rel="stylesheet" href="/assets/styles.css"><script src="/assets/js/api.js" defer></script></head><body>
<header><div class="brand">PHOENIX Adjudication</div><nav><span>Logged in as <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['role']) ?>)</span><a href="logout.php">Logout</a></nav></header>
<main class="container">
<section><h2>Patients</h2><div id="patients"></div></section>
<?php if(in_array($user['role'], ['admin','coordinator'])): ?>
<section><h2>Import Master Table (CSV)</h2><form method="post" action="../api/import_master.php" enctype="multipart/form-data"><input type="file" name="csv" accept=".csv" required><button type="submit">Upload & Import</button></form></section>
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

<!-- ensure this exists since refreshPatients() expects it -->
<section id="patients"></section>

<script>
document.addEventListener('DOMContentLoaded', () => {
  wireDrugLoader();        // your working loader
  wireAddPatientForm();    // new
  refreshPatients();       // show list on load
});

function wireDrugLoader() {
  const sel = document.getElementById('index_drug_id');
  if (!sel) return;
  sel.disabled = true;
  sel.innerHTML = '<option value="">— Loading… —</option>';

  fetch('../api/drugs.php', {
    method: 'GET',
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json' },
    cache: 'no-store'
  })
  .then(res => {
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    if (!(res.headers.get('content-type') || '').includes('application/json')) {
      throw new Error('Non-JSON response (login redirect?)');
    }
    return res.json();
  })
  .then(list => {
    if (!Array.isArray(list) || list.length === 0) {
      sel.innerHTML = '<option value="">No drugs found.</option>';
      return;
    }
    list.sort((a,b) => a.name.localeCompare(b.name));
    sel.innerHTML =
      '<option value="">— Select index drug —</option>' +
      list.map(d => `<option value="${String(d.id)}">${escapeHtml(d.name)}</option>`).join('');
    sel.disabled = false;
  })
  .catch(err => {
    console.error('Failed to load drugs:', err);
    sel.innerHTML = '<option value="">Failed to load index drugs.</option>';
    sel.disabled = true;
  });

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, m => (
      {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]
    ));
  }
}

function wireAddPatientForm() {
  const form = document.getElementById('add-patient');
  if (!form) return;

  const btn  = form.querySelector('button[type="submit"]');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    // gather payload
    const fd = new FormData(form);
    const payload = Object.fromEntries(fd.entries());
    if (payload.index_drug_id) payload.index_drug_id = Number(payload.index_drug_id);

    // simple client validation
    if (!payload.patient_code || !payload.randomisation_date || !payload.index_drug_id) {
      alert('Please complete all fields.');
      return;
    }

    try {
      btn && (btn.disabled = true);
      const res = await fetch('../api/patients.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      if (!res.ok) {
        const txt = await res.text();
        throw new Error(`Add failed: HTTP ${res.status} ${txt}`);
      }
      const out = await res.json();
      if (!out.ok) throw new Error(out.error || 'Add failed');

      // success
      form.reset();
      // keep placeholder at top for the select
      const sel = document.getElementById('index_drug_id');
      if (sel) sel.value = '';
      await refreshPatients();
    } catch (err) {
      console.error(err);
      alert('Failed to add patient. Check console for details.');
    } finally {
      btn && (btn.disabled = false);
    }
  });
}

async function refreshPatients() {
  const container = document.getElementById('patients');
  if (!container) return;

  container.textContent = 'Loading patients…';
  try {
    const res = await fetch('../api/patients.php', {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
      cache: 'no-store'
    });
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

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, m => (
      {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]
    ));
  }
}
</script>

