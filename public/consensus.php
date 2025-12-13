<?php
/**
 * PHOENIX Adjudication - Consensus Page (Materialize CSS)
 * For chair/coordinator to compute consensus on case events
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_login();
$u = current_user();

if (!in_array($u['role'], ['chair', 'coordinator', 'admin'], true)) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

$case_event_id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("
    SELECT ce.*, de.diagnosis, de.category
    FROM case_event ce
    JOIN dictionary_event de ON de.id=ce.dict_event_id
    WHERE ce.id=?
");
$st->execute([$case_event_id]);
$ev = $st->fetch();

if (!$ev) {
    http_response_code(404);
    echo "Case event not found";
    exit;
}

$pageTitle = 'Consensus: ' . htmlspecialchars($ev['diagnosis']);
require_once __DIR__ . '/../inc/templates/header_fixed.php';
?>

<!-- Page Header -->
<div class="row">
    <div class="col s12">
        <h4 class="blue-grey-text text-lighten-2">
            <i class="material-icons left">gavel</i>
            Consensus Review
        </h4>
        <p class="grey-text">Compute majority opinion and finalize adjudication</p>
    </div>
</div>

<!-- Event Information Card -->
<div class="row">
    <div class="col s12">
        <div class="card">
            <div class="card-content">
                <span class="card-title">
                    <i class="material-icons left orange-text">assignment</i>
                    Event: <?= htmlspecialchars($ev['diagnosis']) ?>
                </span>
                <p>
                    <strong>Category:</strong>
                    <span class="chip grey lighten-2"><?= htmlspecialchars($ev['category']) ?></span>
                </p>
                <p class="grey-text">
                    Review all submitted adjudications and compute majority consensus
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Consensus Form Card -->
<div class="row">
    <div class="col s12">
        <div class="card">
            <div class="card-content">
                <span class="card-title">
                    <i class="material-icons left green-text">fact_check</i>
                    Compute Consensus
                </span>

                <form method="post" action="../api/consensus.php">
                    <input type="hidden" name="case_event_id" value="<?= $case_event_id ?>">

                    <div class="row">
                        <div class="input-field col s12">
                            <textarea
                                name="rationale"
                                id="rationale"
                                class="materialize-textarea"
                                data-length="2000"
                                rows="6"></textarea>
                            <label for="rationale">Rationale</label>
                            <span class="helper-text">
                                Explain the consensus decision, majority opinion, and any dissenting views
                            </span>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col s12">
                            <button type="submit" class="btn-large waves-effect waves-light blue">
                                <i class="material-icons left">check_circle</i>
                                Compute Majority & Save
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Instructions Card -->
<div class="row">
    <div class="col s12">
        <div class="card blue-grey darken-3">
            <div class="card-content white-text">
                <span class="card-title">
                    <i class="material-icons left">info</i>
                    Instructions
                </span>
                <ul>
                    <li>• Review all adjudications submitted by the review team</li>
                    <li>• The system will compute the majority causality assessment</li>
                    <li>• Document the consensus process in the rationale field</li>
                    <li>• Note any dissenting opinions or areas of disagreement</li>
                    <li>• The final consensus will be saved and locked</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize Materialize components
document.addEventListener('DOMContentLoaded', function() {
    M.CharacterCounter.init(document.querySelectorAll('textarea[data-length]'));
    M.updateTextFields();
});
</script>

<?php require_once __DIR__ . '/../inc/templates/footer_fixed.php'; ?>
