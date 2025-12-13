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
  const sel = document.getElementById('index_drug_id');
  if (!sel) return;

  sel.disabled = true;
  sel.innerHTML = '<option value="">— Loading… —</option>';

  // Use a relative path so it works under subfolders (e.g., /phoenix/)
  fetch('../api/drugs.php', {
    method: 'GET',
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json' },
    cache: 'no-store'
  })
  .then(res => {
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const ct = res.headers.get('content-type') || '';
    if (!ct.includes('application/json')) throw new Error('Non-JSON response (likely login redirect).');
    return res.json();
  })
  .then(list => {
    if (!Array.isArray(list) || list.length === 0) {
      sel.innerHTML = '<option value="">No drugs found.</option>';
      return; // keep disabled
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
  });

  function escapeHtml(s) {
    // ✅ fixed: properly closed parentheses
    return String(s).replace(/[&<>"']/g, m => (
      {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]
    ));
  }
});

</script>
