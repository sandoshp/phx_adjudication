<?php
/**
 * PHOENIX Adjudication - Case Event Adjudication
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_login();

$user = current_user();

// Check permissions - allow adjudicators, reviewers, coordinators, chairs, and admins
if (!in_array($user['role'], ['admin', 'coordinator', 'chair', 'adjudicator', 'reviewer'])) {
    http_response_code(403);
    echo "You do not have permission to adjudicate cases.";
    exit;
}

/* Redirect ?case_event_id=... to the expected ?id=... */
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

// Fetch existing adjudication by this user for this case event (if revising)
$adjStmt = $pdo->prepare("
    SELECT * FROM adjudication
    WHERE case_event_id = ? AND adjudicator_id = ?
    ORDER BY adjudicated_at DESC
    LIMIT 1
");
$adjStmt->execute([$case_event_id, $user['id']]);
$existingAdj = $adjStmt->fetch(PDO::FETCH_ASSOC);

// If revising, get the suspected concomitants from adjudication_concomitant
$suspectedConcomitants = [];
if ($existingAdj) {
    $susStmt = $pdo->prepare("SELECT drug_id FROM adjudication_concomitant WHERE adjudication_id = ?");
    $susStmt->execute([$existingAdj['id']]);
    $suspectedConcomitants = array_column($susStmt->fetchAll(PDO::FETCH_ASSOC), 'drug_id');
}

// Helper function to check if value should be shown
$show = fn($v) => isset($v) && trim((string)$v) !== '' && trim((string)$v) !== '-';

// Helper function to safely get value
$val = fn($v) => $v ?? '';

$pageTitle = ($existingAdj ? 'Revise: ' : 'Adjudicate: ') . htmlspecialchars($ev['diagnosis']);
require_once __DIR__ . '/../inc/templates/header_light.php';
?>

<!-- Success Message -->
<?php if (!empty($_GET['saved'])): ?>
<div class="row">
    <div class="col s12">
        <div class="card-panel green white-text">
            <i class="material-icons left">check_circle</i>
            Adjudication saved successfully.
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="row">
    <div class="col s12">
        <h4>
            <i class="material-icons left">gavel</i>
            Adjudicate Event
        </h4>
        <p><strong>Patient:</strong> <?= htmlspecialchars($ev['patient_code']) ?></p>
        <a href="patient.php?id=<?= (int)$ev['patient_id'] ?>" class="btn-small waves-effect waves-light">
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
                    <i class="material-icons left">assignment</i>
                    Event Information
                </span>

                <table class="striped">
                    <tbody>
                        <tr>
                            <td style="width: 200px;"><strong>Phenotype</strong></td>
                            <td>
                                <?= htmlspecialchars($ev['diagnosis']) ?>
                                <span class="chip"><?= htmlspecialchars($ev['category']) ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Source</strong></td>
                            <td>
                                <?= htmlspecialchars($ev['source']) ?>
                                <?= $ev['icd10'] ? '<span class="chip">' . htmlspecialchars($ev['icd10']) . '</span>' : '' ?>
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
                                            $parts[] = '<span class="chip grey white-text"><i class="material-icons tiny">medication</i> ' . htmlspecialchars($c['name']) . '</span>';
                                        }
                                    }
                                    echo $parts ? implode(' ', $parts) : '<span class="grey-text">None</span>';
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Evidence Section - Dictionary Event Data -->
<?php
$val = function ($v) { $s = trim((string)($v ?? '')); return $s === '' || $s === '-' ? null : $s; };
$hasEvidence = $show($ev['outcome1']) || $show($ev['outcome2']) || $show($ev['outcome3']) ||
               $show($ev['caveat1']) || $show($ev['caveat2']) || $show($ev['caveat3']) ||
               $show($ev['lcat1']) || $show($ev['lcat2']);
?>

<?php if ($hasEvidence): ?>
<div class="row">
    <div class="col s12">
        <div class="card blue lighten-5">
            <div class="card-content">
                <span class="card-title">
                    <i class="material-icons left blue-text">info</i>
                    Evidence & Dictionary Guidance
                </span>
                <p class="grey-text">Review this information to guide your adjudication decision</p>

                <?php if ($show($ev['outcome1']) || $show($ev['outcome2']) || $show($ev['outcome3'])): ?>
                <div style="margin-top: 20px;">
                    <h6><strong>Outcomes:</strong></h6>
                    <?php if ($show($ev['outcome1'])): ?>
                        <p><strong>Outcome 1:</strong> <?= htmlspecialchars($val($ev['outcome1'])) ?></p>
                    <?php endif; ?>
                    <?php if ($show($ev['outcome2'])): ?>
                        <p><strong>Outcome 2:</strong> <?= htmlspecialchars($val($ev['outcome2'])) ?></p>
                    <?php endif; ?>
                    <?php if ($show($ev['outcome3'])): ?>
                        <p><strong>Outcome 3:</strong> <?= htmlspecialchars($val($ev['outcome3'])) ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($show($ev['caveat1']) || $show($ev['caveat2']) || $show($ev['caveat3'])): ?>
                <div style="margin-top: 20px;">
                    <h6><strong>Caveats:</strong></h6>
                    <?php if ($show($ev['caveat1'])): ?>
                        <p><strong>Caveat 1:</strong> <?= htmlspecialchars($val($ev['caveat1'])) ?></p>
                    <?php endif; ?>
                    <?php if ($show($ev['caveat2'])): ?>
                        <p><strong>Caveat 2:</strong> <?= htmlspecialchars($val($ev['caveat2'])) ?></p>
                    <?php endif; ?>
                    <?php if ($show($ev['caveat3'])): ?>
                        <p><strong>Caveat 3:</strong> <?= htmlspecialchars($val($ev['caveat3'])) ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($show($ev['lcat1'])): ?>
                <div style="margin-top: 20px;">
                    <h6><strong>LCAT 1:</strong> <?= htmlspecialchars($val($ev['lcat1'])) ?></h6>
                    <ul>
                        <?php if ($show($ev['lcat1_met1'])): ?>
                            <li><strong>Met 1:</strong> <?= htmlspecialchars($val($ev['lcat1_met1'])) ?></li>
                        <?php endif; ?>
                        <?php if ($show($ev['lcat1_met2'])): ?>
                            <li><strong>Met 2:</strong> <?= htmlspecialchars($val($ev['lcat1_met2'])) ?></li>
                        <?php endif; ?>
                        <?php if ($show($ev['lcat1_notmet'])): ?>
                            <li><strong>Not Met:</strong> <?= htmlspecialchars($val($ev['lcat1_notmet'])) ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if ($show($ev['lcat2'])): ?>
                <div style="margin-top: 20px;">
                    <h6><strong>LCAT 2:</strong> <?= htmlspecialchars($val($ev['lcat2'])) ?></h6>
                    <ul>
                        <?php if ($show($ev['lcat2_met1'])): ?>
                            <li><strong>Met 1:</strong> <?= htmlspecialchars($val($ev['lcat2_met1'])) ?></li>
                        <?php endif; ?>
                        <?php if ($show($ev['lcat2_notmet'])): ?>
                            <li><strong>Not Met:</strong> <?= htmlspecialchars($val($ev['lcat2_notmet'])) ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Adjudication Form Card -->
<div class="row">
    <div class="col s12">
        <div class="card">
            <div class="card-content">
                <span class="card-title">
                    <i class="material-icons left">fact_check</i>
                    Adjudication Form
                </span>

                <form id="adj-form" method="post" action="../api/adjudications.php">
                    <input type="hidden" name="case_event_id" value="<?= (int)$case_event_id ?>">

                    <!-- Framework Selection -->
                    <div class="row">
                        <div class="input-field col s12 m6">
                            <select name="framework" id="framework">
                                <option value="WHO-UMC" <?= ($existingAdj && $existingAdj['framework'] === 'WHO-UMC') || !$existingAdj ? 'selected' : '' ?>>WHO-UMC</option>
                                <option value="Naranjo" <?= $existingAdj && $existingAdj['framework'] === 'Naranjo' ? 'selected' : '' ?>>Naranjo</option>
                            </select>
                            <label>Framework</label>
                        </div>
                    </div>

                    <!-- Framework Questions (will be populated by JS if needed) -->
                    <div id="framework-questions"></div>

                    <!-- Severity, Expectedness, Attribution -->
                    <div class="row">
                        <div class="input-field col s12 m6 l4">
                            <select name="severity" required>
                                <option value="" disabled <?= !$existingAdj ? 'selected' : '' ?>>Choose severity</option>
                                <option value="Mild" <?= $existingAdj && $existingAdj['severity'] === 'Mild' ? 'selected' : '' ?>>Mild</option>
                                <option value="Moderate" <?= $existingAdj && $existingAdj['severity'] === 'Moderate' ? 'selected' : '' ?>>Moderate</option>
                                <option value="Severe" <?= $existingAdj && $existingAdj['severity'] === 'Severe' ? 'selected' : '' ?>>Severe</option>
                            </select>
                            <label>Severity *</label>
                        </div>

                        <div class="input-field col s12 m6 l4">
                            <select name="expectedness" required>
                                <option value="" disabled <?= !$existingAdj ? 'selected' : '' ?>>Choose expectedness</option>
                                <option value="Expected" <?= $existingAdj && $existingAdj['expectedness'] === 'Expected' ? 'selected' : '' ?>>Expected</option>
                                <option value="Unexpected" <?= $existingAdj && $existingAdj['expectedness'] === 'Unexpected' ? 'selected' : '' ?>>Unexpected</option>
                                <option value="Not_Assessable" <?= $existingAdj && $existingAdj['expectedness'] === 'Not_Assessable' ? 'selected' : '' ?>>Not Assessable</option>
                            </select>
                            <label>Expectedness *</label>
                        </div>

                        <div class="input-field col s12 l4">
                            <select name="index_attribution" required>
                                <option value="" disabled <?= !$existingAdj ? 'selected' : '' ?>>Choose attribution</option>
                                <option value="Yes" <?= $existingAdj && $existingAdj['index_attribution'] === 'Yes' ? 'selected' : '' ?>>Yes</option>
                                <option value="No" <?= $existingAdj && $existingAdj['index_attribution'] === 'No' ? 'selected' : '' ?>>No</option>
                                <option value="Indeterminate" <?= $existingAdj && $existingAdj['index_attribution'] === 'Indeterminate' ? 'selected' : '' ?>>Indeterminate</option>
                            </select>
                            <label>Attribution to Index Drug *</label>
                        </div>
                    </div>

                    <!-- Suspected Concomitants -->
                    <?php if ($concomitants): ?>
                    <div class="row">
                        <div class="col s12">
                            <p><strong>Suspected Concomitants</strong></p>
                            <?php foreach($concomitants as $c): ?>
                                <?php
                                    // If revising, only check if it was in the previous adjudication
                                    // Otherwise, check all by default
                                    $isChecked = $existingAdj
                                        ? in_array($c['id'], $suspectedConcomitants)
                                        : true;
                                ?>
                                <p>
                                    <label>
                                        <input type="checkbox" name="suspected_concomitants[]" value="<?= (int)$c['id'] ?>" <?= $isChecked ? 'checked' : '' ?> />
                                        <span><?= htmlspecialchars($c['name']) ?></span>
                                    </label>
                                </p>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Rationale -->
                    <div class="row">
                        <div class="input-field col s12">
                            <textarea name="rationale" id="rationale" class="materialize-textarea" data-length="1000"><?= $existingAdj ? htmlspecialchars($existingAdj['rationale'] ?? '') : '' ?></textarea>
                            <label for="rationale">Rationale</label>
                            <span class="helper-text">Explain reasoning & evidence</span>
                        </div>
                    </div>

                    <!-- Missing Info -->
                    <?php
                        // Parse existing missing info if present
                        $missingInfo = [];
                        if ($existingAdj && !empty($existingAdj['missing_info'])) {
                            $decoded = json_decode($existingAdj['missing_info'], true);
                            $missingInfo = is_array($decoded) ? $decoded : [];
                        }
                    ?>
                    <div class="row">
                        <div class="col s12">
                            <p><strong>Missing Information</strong></p>
                            <p><label><input type="checkbox" name="missing_info[]" value="Timing" <?= in_array('Timing', $missingInfo) ? 'checked' : '' ?> /><span>Timing of exposure/onset</span></label></p>
                            <p><label><input type="checkbox" name="missing_info[]" value="Dechallenge/Rechallenge" <?= in_array('Dechallenge/Rechallenge', $missingInfo) ? 'checked' : '' ?> /><span>Dechallenge/Rechallenge</span></label></p>
                            <p><label><input type="checkbox" name="missing_info[]" value="Labs" <?= in_array('Labs', $missingInfo) ? 'checked' : '' ?> /><span>Relevant labs</span></label></p>
                            <p><label><input type="checkbox" name="missing_info[]" value="Alternative causes" <?= in_array('Alternative causes', $missingInfo) ? 'checked' : '' ?> /><span>Alternative causes</span></label></p>
                        </div>
                    </div>

                    <!-- Causality -->
                    <div class="row">
                        <div class="input-field col s12 m6">
                            <select name="causality" id="causality" required>
                                <option value="" disabled <?= !$existingAdj ? 'selected' : '' ?>>Choose causality</option>
                                <option value="Definite" <?= $existingAdj && $existingAdj['causality'] === 'Definite' ? 'selected' : '' ?>>Definite</option>
                                <option value="Probable" <?= $existingAdj && $existingAdj['causality'] === 'Probable' ? 'selected' : '' ?>>Probable</option>
                                <option value="Possible" <?= $existingAdj && $existingAdj['causality'] === 'Possible' ? 'selected' : '' ?>>Possible</option>
                                <option value="Unrelated" <?= $existingAdj && $existingAdj['causality'] === 'Unrelated' ? 'selected' : '' ?>>Unrelated</option>
                                <option value="Unable" <?= $existingAdj && $existingAdj['causality'] === 'Unable' ? 'selected' : '' ?>>Unable</option>
                            </select>
                            <label>Causality Class *</label>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="row">
                        <div class="col s12">
                            <button type="submit" class="btn-large waves-effect waves-light blue">
                                <i class="material-icons left"><?= $existingAdj ? 'edit' : 'send' ?></i>
                                <?= $existingAdj ? 'Update Adjudication' : 'Submit Adjudication' ?>
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
    M.updateTextFields();

    // Handle form submission
    const form = document.getElementById('adj-form');
    const submitBtn = form.querySelector('button[type="submit"]');

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(form);
        const payload = {};

        // Convert FormData to object
        for (const [key, value] of formData.entries()) {
            if (key === 'suspected_concomitants[]' || key === 'missing_info[]') {
                const cleanKey = key.replace('[]', '');
                if (!payload[cleanKey]) payload[cleanKey] = [];
                payload[cleanKey].push(value);
            } else {
                payload[key] = value;
            }
        }

        // Ensure arrays exist even if empty
        if (!payload.suspected_concomitants) payload.suspected_concomitants = [];
        if (!payload.missing_info) payload.missing_info = [];

        // Validate required fields
        if (!payload.severity || !payload.expectedness || !payload.index_attribution || !payload.causality) {
            M.toast({ html: '<i class="material-icons left">error</i>Please fill in all required fields', classes: 'red' });
            return;
        }

        // Disable button and show loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="material-icons left">hourglass_empty</i>Saving...';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                const text = await response.text();
                console.error('Response error:', text);
                throw new Error(`HTTP ${response.status}: ${text.substring(0, 200)}`);
            }

            const result = await response.json();

            if (result.ok) {
                M.toast({ html: '<i class="material-icons left">check_circle</i>Adjudication saved successfully!', classes: 'green' });
                setTimeout(() => {
                    window.location.href = 'case_event.php?id=<?= $case_event_id ?>&saved=1';
                }, 1000);
            } else {
                throw new Error(result.error || 'Failed to save adjudication');
            }
        } catch (error) {
            console.error('Save error:', error);
            M.toast({ html: '<i class="material-icons left">error</i>Failed to save: ' + error.message, classes: 'red' });
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<?= $existingAdj ? '<i class="material-icons left">edit</i>Update Adjudication' : '<i class="material-icons left">send</i>Submit Adjudication' ?>';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../inc/templates/footer_light.php'; ?>
