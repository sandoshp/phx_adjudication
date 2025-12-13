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
if (!$p) {
  http_response_code(404);
  echo "Patient not found";
  exit;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Patient <?= htmlspecialchars($p['patient_code']) ?></title>
  <link rel="stylesheet" href="/assets/styles.css">
  <!-- Use relative path so it also works if the app is in a subfolder -->
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
  </section>

  <section class="card">
    <h2>Case Events</h2>
    <button id="generate-events">Generate from Dictionary</button>
    <div id="events" class="mt-2"></div>
  </section>
</main>

<script>
const patientId = <?= json_encode($patient_id) ?>;

document.addEventListener('DOMContentLoaded', () => {
  const wrap = document.getElementById('events');
  const btn  = document.getElementById('generate-events');

  async function fetchEventsDirect() {
    // Try preferred param first, then legacy (?id=) as a fallback
    const urls = [
      `../api/case_events.php?patient_id=${encodeURIComponent(patientId)}`,
      `../api/case_events.php?id=${encodeURIComponent(patientId)}`
    ];
    let lastErr = null;
    for (const u of urls) {
      try {
        const r = await fetch(u, {
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json' },
          cache: 'no-store'
        });
        if (!r.ok) {
          lastErr = new Error(`GET ${u} -> HTTP ${r.status}`);
          continue;
        }
        const ct = r.headers.get('content-type') || '';
        if (!ct.includes('application/json')) {
          lastErr = new Error(`GET ${u} -> non-JSON response (possible login redirect)`);
          continue;
        }
        return r.json();
      } catch (e) {
        lastErr = e;
      }
    }
    throw lastErr || new Error('Failed to fetch events');
  }

  async function fetchEvents() {
    // Prefer api.js if present; fall back to direct fetch
    if (window.api?.getCaseEvents) {
      try { return await api.getCaseEvents(patientId); }
      catch (e) { console.warn('api.getCaseEvents failed, falling back:', e); }
    }
    return fetchEventsDirect();
  }

  async function generateEvents() {
    if (window.api?.generateCaseEvents) {
      return api.generateCaseEvents(patientId);
    }
    // Fallback POST
    const r = await fetch('../api/case_events.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'generate', patient_id: patientId })
    });
    if (!r.ok) throw new Error(`POST ../api/case_events.php -> HTTP ${r.status}`);
    return r.json(); // { ok:true, inserted:N }
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
      events.forEach(ev => {
        const el = document.createElement('div');
        el.className = 'list-item';
        const badge = ev.has_consensus===1 ? '✅' : (Number(ev.adjudications_count) >= 3 ? '⏳' : '📝');
        el.innerHTML = `
          ${badge} ${escapeHtml(ev.category || '')}
          — <strong>${escapeHtml(ev.diagnosis || '')}</strong>
          (${escapeHtml(ev.source || '')}${ev.icd10 ? ' / ' + escapeHtml(ev.icd10) : ''})
          <div><a href="case_event.php?id=${encodeURIComponent(ev.id)}">Adjudicate</a></div>
        `;
        wrap.appendChild(el);
      });
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
