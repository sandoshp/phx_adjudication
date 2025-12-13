<?php
/**
 * PHOENIX Adjudication - Case Event Adjudication (Materialize CSS)
 */
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

$pageTitle = 'Adjudicate: ' . htmlspecialchars($ev['diagnosis']);
require_once __DIR__ . '/../inc/templates/header_fixed.php';
?>

<!-- Success Message -->
<?php if (!empty($_GET['saved'])): ?>
<div class="row">
    <div class="col s12">
        <div class="card-panel green darken-1 white-text">
            <i class="material-icons left">check_circle</i>
            Adjudication saved successfully.
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="row">
    <div class="col s12">
        <h4 class="blue-grey-text text-lighten-2">
            <i class="material-icons left">gavel</i>
            Adjudicate Event
        </h4>
        <p class="grey-text">Patient: <?= htmlspecialchars($ev['patient_code']) ?></p>
        <a href="patient.php?id=<?= (int)$ev['patient_id'] ?>" class="btn-small blue waves-effect waves-light">
            <i class="material-icons left">arrow_back</i>
            Back to Patient
        </a>
    </div>
</div>

<!-- Event Information Card -->
<div class="row">
    <div class="col s12">
        <div class="card">
            <div class="card-content">
                <span class="card-title">
                    <i class="material-icons left orange-text">assignment</i>
                    Event Information
                </span>

                <table class="striped">
                    <tbody>
                        <tr>
                            <td style="width: 200px;"><strong>Phenotype</strong></td>
                            <td>
                                <?= htmlspecialchars($ev['diagnosis']) ?>
                                <span class="chip grey lighten-2"><?= htmlspecialchars($ev['category']) ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Source</strong></td>
                            <td>
                                <?= htmlspecialchars($ev['source']) ?>
                                <?= $ev['icd10'] ? '<span class="chip grey lighten-2">' . htmlspecialchars($ev['icd10']) . '</span>' : '' ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;"><strong>Relevant Drugs</strong></td>
                            <td>
                                <?php
                                    $parts = [];
                                    if ($indexRelevant) {
                                        $drugName = $pdo->query("SELECT name FROM drugs WHERE id=".(int)$ev['index_drug_id'])->fetchColumn();
                                        $parts[] = '<span class="chip blue white-text"><i class="material-icons tiny">medication</i> Index: ' . htmlspecialchars($drugName) . '</span>';
                                    }
                                    if ($concomitants) {
                                        foreach ($concomitants as $c) {
                                            $parts[] = '<span class="chip grey lighten-1"><i class="material-icons tiny">medication</i> ' . htmlspecialchars($c['name']) . '</span>';
                                        }
                                    }
                                    echo $parts ? implode(' ', $parts) : '<span class="grey-text">None</span>';
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php
                    $val = function ($v) { $s = trim((string)($v ?? '')); return $s === '' || $s === '-' ? '-' : $s; };
                    $hasNotes = $show($ev['caveat1']) || $show($ev['caveat2']) || $show($ev['caveat3']) ||
                                $show($ev['outcome1']) || $show($ev['outcome2']) || $show($ev['outcome3']) ||
                                $show($ev['lcat1']) || $show($ev['lcat2']);
                ?>

                <?php if ($hasNotes): ?>
                <div style="margin-top: 20px;">
                    <ul class="collapsible">
                        <li>
                            <div class="collapsible-header">
                                <i class="material-icons">info_outline</i>
                                Dictionary Notes
                            </div>
                            <div class="collapsible-body">
                                <?php if ($show($ev['caveat1']) || $show($ev['outcome1'])): ?>
                                    <p><strong>Caveat 1:</strong> <?= htmlspecialchars($val($ev['caveat1'])) ?></p>
                                    <p><strong>Outcome 1:</strong> <?= htmlspecialchars($val($ev['outcome1'])) ?></p>
                                <?php endif; ?>
                                <?php if ($show($ev['caveat2']) || $show($ev['outcome2'])): ?>
                                    <p><strong>Caveat 2:</strong> <?= htmlspecialchars($val($ev['caveat2'])) ?></p>
                                    <p><strong>Outcome 2:</strong> <?= htmlspecialchars($val($ev['outcome2'])) ?></p>
                                <?php endif; ?>
                                <?php if ($show($ev['caveat3']) || $show($ev['outcome3'])): ?>
                                    <p><strong>Caveat 3:</strong> <?= htmlspecialchars($val($ev['caveat3'])) ?></p>
                                    <p><strong>Outcome 3:</strong> <?= htmlspecialchars($val($ev['outcome3'])) ?></p>
                                <?php endif; ?>

                                <?php if ($show($ev['lcat1'])): ?>
                                    <p><strong>LCAT 1:</strong> <?= htmlspecialchars($val($ev['lcat1'])) ?></p>
                                    <ul>
                                        <li>Met 1: <?= htmlspecialchars($val($ev['lcat1_met1'])) ?></li>
                                        <li>Met 2: <?= htmlspecialchars($val($ev['lcat1_met2'])) ?></li>
                                        <li>Not Met: <?= htmlspecialchars($val($ev['lcat1_notmet'])) ?></li>
                                    </ul>
                                <?php endif; ?>

                                <?php if ($show($ev['lcat2'])): ?>
                                    <p><strong>LCAT 2:</strong> <?= htmlspecialchars($val($ev['lcat2'])) ?></p>
                                    <ul>
                                        <li>Met 1: <?= htmlspecialchars($val($ev['lcat2_met1'])) ?></li>
                                        <li>Not Met: <?= htmlspecialchars($val($ev['lcat2_notmet'])) ?></li>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Adjudication Form Card -->
<div class="row">
    <div class="col s12">
        <div class="card">
            <div class="card-content">
                <span class="card-title">
                    <i class="material-icons left green-text">fact_check</i>
                    Adjudication Wizard
                </span>
                <div id="save-status"></div>

                <form id="adj-form" method="post" action="../api/adjudications.php" novalidate>
                    <input type="hidden" name="case_event_id" value="<?= (int)$case_event_id ?>">

                    <!-- Framework Selection -->
                    <div class="row">
                        <div class="input-field col s12 m6">
                            <select name="framework" id="framework">
                                <option value="WHO-UMC">WHO-UMC</option>
                                <option value="Naranjo">Naranjo</option>
                            </select>
                            <label>Framework</label>
                        </div>
                    </div>

                    <!-- Framework Questions (will be populated by JS) -->
                    <div id="framework-questions"></div>

                    <!-- Severity -->
                    <div class="row">
                        <div class="input-field col s12 m6 l4">
                            <select name="severity">
                                <option value="Mild">Mild</option>
                                <option value="Moderate">Moderate</option>
                                <option value="Severe">Severe</option>
                            </select>
                            <label>Severity</label>
                        </div>

                        <!-- Expectedness -->
                        <div class="input-field col s12 m6 l4">
                            <select name="expectedness">
                                <option value="Expected">Expected</option>
                                <option value="Unexpected">Unexpected</option>
                                <option value="Not_Assessable">Not Assessable</option>
                            </select>
                            <label>Expectedness</label>
                        </div>

                        <!-- Attribution to Index Drug -->
                        <div class="input-field col s12 l4">
                            <select name="index_attribution">
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                                <option value="Indeterminate">Indeterminate</option>
                            </select>
                            <label>Attribution to Index Drug</label>
                        </div>
                    </div>

                    <!-- Suspected Concomitants -->
                    <div class="row">
                        <div class="col s12">
                            <p><strong>Suspected Concomitants (relevant only)</strong></p>
                            <?php if (!$concomitants): ?>
                                <p class="grey-text"><em>None</em></p>
                            <?php else: ?>
                                <?php foreach($concomitants as $c): ?>
                                    <p>
                                        <label>
                                            <input type="checkbox" name="suspected_concomitants[]" value="<?= (int)$c['id'] ?>" checked />
                                            <span><?= htmlspecialchars($c['name']) ?></span>
                                        </label>
                                    </p>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Rationale -->
                    <div class="row">
                        <div class="input-field col s12">
                            <textarea name="rationale" id="rationale" class="materialize-textarea" data-length="1000"></textarea>
                            <label for="rationale">Rationale</label>
                            <span class="helper-text">Explain reasoning & evidence</span>
                        </div>
                    </div>

                    <!-- Missing Info -->
                    <div class="row">
                        <div class="col s12">
                            <p><strong>Missing Information</strong></p>
                            <p>
                                <label>
                                    <input type="checkbox" name="missing_info[]" value="Timing" />
                                    <span>Timing of exposure/onset</span>
                                </label>
                            </p>
                            <p>
                                <label>
                                    <input type="checkbox" name="missing_info[]" value="Dechallenge/Rechallenge" />
                                    <span>Dechallenge/Rechallenge</span>
                                </label>
                            </p>
                            <p>
                                <label>
                                    <input type="checkbox" name="missing_info[]" value="Labs" />
                                    <span>Relevant labs</span>
                                </label>
                            </p>
                            <p>
                                <label>
                                    <input type="checkbox" name="missing_info[]" value="Alternative causes" />
                                    <span>Alternative causes</span>
                                </label>
                            </p>
                        </div>
                    </div>

                    <!-- Auto-score and Causality -->
                    <div class="row">
                        <div class="col s12 m6">
                            <button type="button" id="calc-score" class="btn waves-effect waves-light orange">
                                <i class="material-icons left">calculate</i>
                                Auto-score
                            </button>
                            <span id="auto-score" style="margin-left: 15px; font-weight: bold;"></span>
                        </div>

                        <div class="input-field col s12 m6">
                            <select name="causality" id="causality">
                                <option value="Definite">Definite</option>
                                <option value="Probable">Probable</option>
                                <option value="Possible">Possible</option>
                                <option value="Unrelated">Unrelated</option>
                                <option value="Unable">Unable</option>
                            </select>
                            <label>Causality Class</label>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="row">
                        <div class="col s12">
                            <button type="submit" class="btn-large waves-effect waves-light blue">
                                <i class="material-icons left">send</i>
                                Submit Adjudication
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize Materialize components
document.addEventListener('DOMContentLoaded', function() {
    M.FormSelect.init(document.querySelectorAll('select'));
    M.CharacterCounter.init(document.querySelectorAll('textarea[data-length]'));
    M.Collapsible.init(document.querySelectorAll('.collapsible'));
});
</script>

<?php require_once __DIR__ . '/../inc/templates/footer_fixed.php'; ?>
