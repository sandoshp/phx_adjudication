<?php
require_once __DIR__ . '/../inc/auth.php'; require_login(); $user=current_user();
?><!doctype html><html><head><meta charset="utf-8"><title>Dashboard</title><link rel="stylesheet" href="/assets/styles.css"><script src="/assets/js/api.js" defer></script></head><body>
<header><div class="brand">PHOENIX Adjudication</div><nav><span>Logged in as <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['role']) ?>)</span><a href="logout.php">Logout</a></nav></header>
<main class="container">
<section><h2>Patients</h2><div id="patients"></div></section>
<?php if(in_array($user['role'], ['admin','coordinator'])): ?>
<section><h2>Import Master Table (CSV)</h2><form method="post" action="../api/import_master.php" enctype="multipart/form-data"><input type="file" name="csv" accept=".csv" required><button type="submit">Upload & Import</button></form></section>
<?php endif; ?>
<section><h2>Add Patient</h2><form id="add-patient"><label>Patient ID <input type="text" name="patient_code" required></label><label>Randomisation Date <input type="date" name="randomisation_date" required></label><label>Index drug <select name="index_drug_id" id="index_drug_id"></select></label><button type="submit">Add</button></form></section>
</main>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const drugs = await api.getDrugs(); const sel = document.getElementById('index_drug_id');
  drugs.forEach(d => { const opt=document.createElement('option'); opt.value=d.id; opt.textContent=d.name; sel.appendChild(opt); });
  async function refreshPatients(){ const list=await api.getPatients(); const div=document.getElementById('patients'); div.innerHTML=''; list.forEach(p=>{ const el=document.createElement('div'); el.className='card'; el.innerHTML=`<strong>${p.patient_code}</strong> — Index: ${p.index_drug} — Randomised: ${p.randomisation_date} — FU End: ${p.followup_end_date}<div><a href="/patient.php?id=${p.id}">Open</a></div>`; div.appendChild(el); }); }
  document.getElementById('add-patient').addEventListener('submit', async (e)=>{ e.preventDefault(); const fd=new FormData(e.target); const ok=await api.addPatient(Object.fromEntries(fd.entries())); if(ok){ e.target.reset(); refreshPatients(); } });
  refreshPatients();
});
</script></body></html>
