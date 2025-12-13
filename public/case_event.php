<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_login();


/* Redirect ?case_event_id=... to the expected ?id=... to avoid "Case event not found" on accidental GET */
if (isset($_GET['case_event_id']) && !isset($_GET['id'])) {
  $cid = (int)$_GET['case_event_id'];
  header("Location: case_event.php?id={$cid}", true, 302);
  exit;
}

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

/* Is index drug relevant to this outcome? */
$idxStmt = $pdo->prepare("SELECT 1 FROM drug_event_map WHERE dict_event_id = ? AND drug_id = ?");
$idxStmt->execute([$ev['dict_event_id'], $ev['index_drug_id']]);
$indexRelevant = (bool)$idxStmt->fetchColumn();

/* Concomitants relevant to this outcome only */
$cons = $pdo->prepare("
  SELECT d.id, d.name
  FROM patient_concomitant_drug pcd
  JOIN drug_event_map dem ON dem.drug_id = pcd.drug_id
                          AND dem.dict_event_id = ?
  JOIN drugs d ON d.id = pcd.drug_id
  WHERE pcd.patient_id = ?
  ORDER BY d.name
");
$cons->execute([$ev['dict_event_id'], $ev['patient_id']]);
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
  // (submit handler unchanged)
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

<?php if (!empty($_GET['saved'])): ?>
  <div class="notice success" style="margin-bottom:1rem;padding:.5rem .75rem;border:1px solid #c7e7c7;background:#eaf8ea;color:#245c24;border-radius:6px;">
    Adjudication saved.
  </div>
<?php endif; ?>


  <section class="card">
    <h2>Event</h2>
    <p><strong>Phenotype:</strong> <?= htmlspecialchars($ev['diagnosis']) ?> (<?= htmlspecialchars($ev['category']) ?>)</p>
    <p><strong>Source:</strong> <?= htmlspecialchars($ev['source']) ?> <?= $ev['icd10'] ? '(' . htmlspecialchars($ev['icd10']) . ')' : '' ?></p>

    <p><strong>Relevant drugs for this outcome:</strong>
      <?php
        $parts = [];
        if ($indexRelevant) $parts[] = htmlspecialchars('Index: ' . $pdo->query("SELECT name FROM drugs WHERE id=".(int)$ev['index_drug_id'])->fetchColumn());
        if ($concomitants)  $parts[] = 'Concomitants: ' . htmlspecialchars(implode(', ', array_column($concomitants, 'name')));
        echo $parts ? implode(' | ', $parts) : 'None';
      ?>
    </p>

    <?php
      $val = function ($v) { $s = trim((string)($v ?? '')); return $s === '' || $s === '-' ? '-' : $s; };
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
    <form id="adj-form"  method="post" action="../api/adjudications.php" novalidate>
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
		  <legend>Suspected Concomitants (relevant only)</legend>
		  <?php if (!$concomitants): ?>
			<em class="muted">None</em>
		  <?php else: ?>
			<?php foreach($concomitants as $c): ?>
			  <label>
				<input type="checkbox" name="suspected_concomitants[]" value="<?= (int)$c['id'] ?>" checked>
				<?= htmlspecialchars($c['name']) ?>
			  </label>
			<?php endforeach; ?>
		  <?php endif; ?>
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
