<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_login();

$case_event_id = (int)($_GET['id'] ?? 0);

$st = $pdo->prepare("SELECT ce.*,
  de.diagnosis, de.category, de.icd10, de.source,
  de.caveat1, de.caveat2, de.caveat3,
  de.outcome1, de.outcome2, de.outcome3,
  de.lcat1, de.lcat1_met1, de.lcat1_met2, de.lcat1_notmet,
  de.lcat2, de.lcat2_met1, de.lcat2_notmet,
  p.patient_code, p.index_drug_id
  FROM case_event ce
  JOIN dictionary_event de ON de.id=ce.dict_event_id
  JOIN patients p ON p.id=ce.patient_id
  WHERE ce.id=?");
$st->execute([$case_event_id]);
$ev = $st->fetch(PDO::FETCH_ASSOC);
if (!$ev) { http_response_code(404); echo "Case event not found"; exit; }

$cons = $pdo->prepare("SELECT d.id,d.name
  FROM patient_concomitant_drug pcd
  JOIN drugs d ON d.id=pcd.drug_id
  WHERE pcd.patient_id=? AND d.id<>?
  ORDER BY d.name");
$cons->execute([$ev['patient_id'], $ev['index_drug_id']]);
$concomitants = $cons->fetchAll(PDO::FETCH_ASSOC);

$show = fn($v) => isset($v) && trim((string)$v) !== '' && trim((string)$v) !== '-';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Adjudicate: <?= htmlspecialchars($ev['diagnosis']) ?> (<?= htmlspecialchars($ev['patient_code']) ?>)</title>
  <link rel="stylesheet" href="/assets/styles.css">
  <script src="assets/js/api.js" defer></script>
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('adj-form');
    const statusEl = document.getElementById('save-status');
    const caseEventId = <?= (int)$case_event_id ?>;

    // Prefill from existing adjudication if present
    (async function prefill(){
      try {
        const r = await fetch(`../api/adjudications.php?case_event_id=${encodeURIComponent(caseEventId)}`, {
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json' }
        });
        if (!r.ok) return;
        const data = await r.json();
        if (!data || !data.id) return;

        form.framework.value = data.framework || 'WHO-UMC';
        form.severity.value = data.severity || 'Mild';
        form.expectedness.value = data.expectedness || 'Expected';
        form.index_attribution.value = data.index_attribution || 'Yes';
        form.causality.value = data.causality || 'Definite';
        if (data.rationale) form.rationale.value = data.rationale;

        try {
          const sus = Array.isArray(data.suspected_concomitants)
            ? data.suspected_concomitants
            : JSON.parse(data.suspected_concomitants || '[]');
          sus.forEach(id => {
            const cb = form.querySelector(`input[name="suspected_concomitants[]"][value="${id}"]`);
            if (cb) cb.checked = true;
          });
        } catch {}

        try {
          const miss = Array.isArray(data.missing_info) ? data.missing_info : JSON.parse(data.missing_info || '[]');
          miss.forEach(v => {
            const cb = form.querySelector(`input[name="missing_info[]"][value="${v}"]`);
            if (cb) cb.checked = true;
          });
        } catch {}
      } catch (_) {}
    })();

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      statusEl.textContent = 'Saving…';

      const payload = {
        case_event_id: caseEventId,
        framework: form.framework.value,
        severity: form.severity.value,
        expectedness: form.expectedness.value,
        index_attribution: form.index_attribution.value,
        causality: form.causality.value,
        suspected_concomitants: Array.from(form.querySelectorAll('input[name="suspected_concomitants[]"]:checked')).map(x => Number(x.value)),
        rationale: form.rationale.value.trim(),
        missing_info: Array.from(form.querySelectorAll('input[name="missing_info[]"]:checked')).map(x => x.value),
        // If your page/JS builds detailed question responses or an auto score, set them here:
        responses: window.buildFrameworkResponses ? window.buildFrameworkResponses() : null,
        auto_score: window.currentAutoScore ?? null
      };

      try {
        const r = await fetch('../api/adjudications.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify(payload)
        });
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        const out = await r.json();
        if (!out.ok) throw new Error(out.error || 'Save failed');
        statusEl.textContent = 'Saved.';
        setTimeout(()=> statusEl.textContent = '', 1500);
      } catch (err) {
        console.error(err);
        statusEl.textContent = 'Save failed.';
      }
    });
  });
  </script>
</head>
<body>
<header>
  <div class="brand">PHOENIX Adjudication</div>
  <nav>
    <a href="patient.php?id=<?= (int)$ev['patient_id'] ?>">Back to Patient</a>
    <a href="logout.php">Logout</a>
  </nav>
</header>

<main class="container">
  <section class="card">
    <h2>Event</h2>
    <p><strong>Phenotype:</strong> <?= htmlspecialchars($ev['diagnosis']) ?> (<?= htmlspecialchars($ev['category']) ?>)</p>
    <p><strong>Source:</strong> <?= htmlspecialchars($ev['source']) ?> <?= $ev['icd10'] ? '(' . htmlspecialchars($ev['icd10']) . ')' : '' ?></p>

<?php
// helper: show "-" if value is empty or just "-"
$val = function ($v) {
  $s = trim((string)($v ?? ''));
  return $s === '' || $s === '-' ? '-' : $s;
};
?>
	<details>
	  <summary>Dictionary notes</summary>

	  <ul>
		<li><strong>Caveat 1:</strong> <?= htmlspecialchars($val($ev['caveat1']), ENT_QUOTES) ?></li>
		<li><strong>Outcome 1:</strong> <?= htmlspecialchars($val($ev['outcome1']), ENT_QUOTES) ?></li>
		<li><strong>Caveat 2:</strong> <?= htmlspecialchars($val($ev['caveat2']), ENT_QUOTES) ?></li>
		<li><strong>Outcome 2:</strong> <?= htmlspecialchars($val($ev['outcome2']), ENT_QUOTES) ?></li>
		<li><strong>Caveat 3:</strong> <?= htmlspecialchars($val($ev['caveat3']), ENT_QUOTES) ?></li>
		<li><strong>Outcome 3:</strong> <?= htmlspecialchars($val($ev['outcome3']), ENT_QUOTES) ?></li>
	  </ul>

	  <p><strong>LCAT 1:</strong> <?= htmlspecialchars($val($ev['lcat1']), ENT_QUOTES) ?></p>
	  <ul>
		<li>Met 1: <?= htmlspecialchars($val($ev['lcat1_met1']), ENT_QUOTES) ?></li>
		<li>Met 2: <?= htmlspecialchars($val($ev['lcat1_met2']), ENT_QUOTES) ?></li>
		<li>Not Met: <?= htmlspecialchars($val($ev['lcat1_notmet']), ENT_QUOTES) ?></li>
	  </ul>

	  <p><strong>LCAT 2:</strong> <?= htmlspecialchars($val($ev['lcat2']), ENT_QUOTES) ?></p>
	  <ul>
		<li>Met 1: <?= htmlspecialchars($val($ev['lcat2_met1']), ENT_QUOTES) ?></li>
		<li>Not Met: <?= htmlspecialchars($val($ev['lcat2_notmet']), ENT_QUOTES) ?></li>
	  </ul>
	</details>
  </section>

  <section class="card">
    <h2>Adjudication Wizard</h2>
    <div id="save-status" class="muted"></div>
    <form id="adj-form">
      <input type="hidden" name="case_event_id" value="<?= (int)$case_event_id ?>">

      <label>Framework
        <select name="framework" id="framework">
          <option value="WHO-UMC">WHO-UMC</option>
          <option value="Naranjo">Naranjo</option>
        </select>
      </label>

      <div id="framework-questions"></div>

      <label>Severity
        <select name="severity">
          <option value="Mild">Mild</option>
          <option value="Moderate">Moderate</option>
          <option value="Severe">Severe</option>
        </select>
      </label>

      <label>Expectedness
        <select name="expectedness">
          <option value="Expected">Expected</option>
          <option value="Unexpected">Unexpected</option>
          <option value="Not_Assessable">Not_Assessable</option>
        </select>
      </label>

      <label>Attribution to Index Drug
        <select name="index_attribution">
          <option value="Yes">Yes</option>
          <option value="No">No</option>
          <option value="Indeterminate">Indeterminate</option>
        </select>
      </label>

      <fieldset>
        <legend>Suspected Concomitants</legend>
        <?php foreach($concomitants as $c): ?>
          <label><input type="checkbox" name="suspected_concomitants[]" value="<?= (int)$c['id'] ?>"> <?= htmlspecialchars($c['name']) ?></label>
        <?php endforeach; ?>
      </fieldset>

      <label>Rationale
        <textarea name="rationale" rows="4" placeholder="Explain reasoning &amp; evidence..."></textarea>
      </label>

      <label>Missing info</label>
      <div class="checkboxes">
        <label><input type="checkbox" name="missing_info[]" value="Timing"> Timing of exposure/onset</label>
        <label><input type="checkbox" name="missing_info[]" value="Dechallenge/Rechallenge"> Dechallenge/Rechallenge</label>
        <label><input type="checkbox" name="missing_info[]" value="Labs"> Relevant labs</label>
        <label><input type="checkbox" name="missing_info[]" value="Alternative causes"> Alternative causes</label>
      </div>

      <div class="actions">
        <button type="button" id="calc-score">Auto-score</button>
        <span id="auto-score"></span>
        <label>Causality class
          <select name="causality" id="causality">
            <option value="Definite">Definite</option>
            <option value="Probable">Probable</option>
            <option value="Possible">Possible</option>
            <option value="Unrelated">Unrelated</option>
            <option value="Unable">Unable</option>
          </select>
        </label>
      </div>

      <button type="submit">Submit Adjudication</button>
    </form>
  </section>
</main>
</body>
</html>
