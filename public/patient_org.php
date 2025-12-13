<?php
require_once __DIR__ . '/../inc/auth.php'; require_once __DIR__ . '/../inc/db.php'; require_login(); $user=current_user();
$patient_id=(int)($_GET['id']??0);
$st=$pdo->prepare("SELECT p.*, d.name AS index_drug FROM patients p JOIN drugs d ON d.id=p.index_drug_id WHERE p.id=?"); $st->execute([$patient_id]); $p=$st->fetch();
if(!$p){ http_response_code(404); echo "Patient not found"; exit; }
?><!doctype html><html><head><meta charset="utf-8"><title>Patient <?= htmlspecialchars($p['patient_code']) ?></title><link rel="stylesheet" href="/assets/styles.css"><script src="assets/js/api.js" defer></script></head><body>
<header><div class="brand">PHOENIX Adjudication</div><nav><a href="dashboard.php">Back</a><span>Logged in as <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['role']) ?>)</span><a href="/logout.php">Logout</a></nav></header>
<main class="container">
<section class="card"><h2>Baseline</h2><p><strong>Patient ID:</strong> <?= htmlspecialchars($p['patient_code']) ?></p><p><strong>Date of Randomisation:</strong> <?= htmlspecialchars($p['randomisation_date']) ?></p><p><strong>End of Follow-up:</strong> <?= htmlspecialchars($p['followup_end_date']) ?></p><p><strong>Index drug:</strong> <?= htmlspecialchars($p['index_drug']) ?></p></section>
<section class="card"><h2>Case Events</h2><button id="generate-events">Generate from Dictionary</button><div id="events"></div></section>
</main>
<script>
const patientId=<?= json_encode($patient_id) ?>;
document.addEventListener('DOMContentLoaded', async () => {
  async function refreshEvents(){ const events=await api.getCaseEvents(patientId); const wrap=document.getElementById('events'); wrap.innerHTML=''; events.forEach(ev=>{ const el=document.createElement('div'); el.className='list-item'; const badge=ev.has_consensus?'✅':(ev.adjudications_count>=3?'⏳':'📝'); el.innerHTML=`${badge} ${ev.category||''} — <strong>${ev.diagnosis}</strong> (${ev.source}${ev.icd10?' / '+ev.icd10:''})<div><a href="../api/case_events.php?id=${ev.id}">Adjudicate</a></div>`; wrap.appendChild(el); }); }
  document.getElementById('generate-events').addEventListener('click', async ()=>{ const ok=await api.generateCaseEvents(patientId); if(ok) refreshEvents(); });
  refreshEvents();
});
</script></body></html>
