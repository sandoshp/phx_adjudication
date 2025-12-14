<?php
/**
 * PHOENIX Adjudication - Consensus Page
 * For chair/coordinator to review adjudications and compute consensus
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_login();
$u = current_user();

// Check permissions - only chair, coordinator, and admin can access
if (!in_array($u['role'], ['chair', 'coordinator', 'admin'], true)) {
    http_response_code(403);
    echo "You do not have permission to access consensus review.";
    exit;
}

// Accept both ?id= and ?case_event_id= parameters
$case_event_id = (int)($_GET['case_event_id'] ?? $_GET['id'] ?? 0);

if (!$case_event_id) {
    http_response_code(400);
    echo "Missing case_event_id parameter";
    exit;
}

// Fetch case event details
$st = $pdo->prepare("
    SELECT ce.*, de.diagnosis, de.category, de.source, p.patient_code
    FROM case_event ce
    JOIN dictionary_event de ON de.id = ce.dict_event_id
    JOIN patients p ON p.id = ce.patient_id
    WHERE ce.id = ?
");
$st->execute([$case_event_id]);
$ev = $st->fetch(PDO::FETCH_ASSOC);

if (!$ev) {
    http_response_code(404);
    echo "Case event not found";
    exit;
}

// Fetch all adjudications for this case event
$adjStmt = $pdo->prepare("
    SELECT a.*, u.name AS adjudicator_name, u.email AS adjudicator_email
    FROM adjudication a
    JOIN users u ON u.id = a.adjudicator_id
    WHERE a.case_event_id = ?
    ORDER BY a.submitted_at DESC
");
$adjStmt->execute([$case_event_id]);
$adjudications = $adjStmt->fetchAll(PDO::FETCH_ASSOC);

// Check if consensus already exists
$consStmt = $pdo->prepare("SELECT * FROM consensus WHERE case_event_id = ?");
$consStmt->execute([$case_event_id]);
$existingConsensus = $consStmt->fetch(PDO::FETCH_ASSOC);

// Compute majority for preview
$maj = function($arr) {
    if (empty($arr)) return null;
    $c = array_count_values($arr);
    arsort($c);
    $k = array_keys($c);
    if (!count($k)) return null;
    if (count($k) >= 2 && $c[$k[0]] == $c[$k[1]]) return null; // Tie
    return $k[0];
};

$causalities = array_column($adjudications, 'causality');
$severities = array_column($adjudications, 'severity');
$expectednesses = array_column($adjudications, 'expectedness');

$majCausality = $maj($causalities);
$majSeverity = $maj($severities);
$majExpectedness = $maj($expectednesses);

$pageTitle = 'Consensus: ' . htmlspecialchars($ev['diagnosis']);
require_once __DIR__ . '/../inc/templates/header_light.php';
?>

<!-- Success Message -->
<?php if (!empty($_GET['saved'])): ?>
<div class="row">
    <div class="col s12">
        <div class="card-panel green white-text">
            <i class="material-icons left">check_circle</i>
            Consensus saved successfully.
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="row">
    <div class="col s12">
        <h4>
            <i class="material-icons left">gavel</i>
            Consensus Review
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
                    <i class="material-icons left orange-text">assignment</i>
                    Event Information
                </span>

                <table class="striped">
                    <tbody>
                        <tr>
                            <td style="width: 200px;"><strong>Outcome</strong></td>
                            <td><?= htmlspecialchars($ev['diagnosis']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Category</strong></td>
                            <td><span class="chip"><?= htmlspecialchars($ev['category']) ?></span></td>
                        </tr>
                        <tr>
                            <td><strong>Source</strong></td>
                            <td><?= htmlspecialchars($ev['source'] ?? '—') ?></td>
                        </tr>
                        <tr>
                            <td><strong>Status</strong></td>
                            <td>
                                <?php if ($ev['status'] === 'consensus'): ?>
                                    <span class="chip green white-text">Consensus</span>
                                <?php else: ?>
                                    <span class="chip orange white-text"><?= htmlspecialchars(ucfirst($ev['status'])) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Adjudications Review Card -->
<div class="row">
    <div class="col s12">
        <div class="card">
            <div class="card-content">
                <span class="card-title">
                    <i class="material-icons left blue-text">people</i>
                    Submitted Adjudications (<?= count($adjudications) ?>)
                </span>

                <?php if (count($adjudications) < 3): ?>
                    <div class="card-panel orange white-text" style="margin-top: 20px;">
                        <i class="material-icons left">warning</i>
                        <strong>Warning:</strong> Only <?= count($adjudications) ?> adjudication(s) submitted.
                        Consensus typically requires at least 3 independent adjudications.
                    </div>
                <?php endif; ?>

                <?php if (empty($adjudications)): ?>
                    <p class="grey-text center-align">No adjudications submitted yet.</p>
                <?php else: ?>
                    <div class="input-field" style="margin-top: 20px;">
                        <i class="material-icons prefix">search</i>
                        <input type="text" id="search-adjudications" placeholder="Search adjudications...">
                        <label for="search-adjudications">Search</label>
                    </div>
                    <p class="grey-text">
                        <i class="material-icons tiny">info</i>
                        <small>Click column headers to sort. Use search box to filter results.</small>
                    </p>
                    <table class="striped highlight responsive-table" style="margin-top: 20px;">
                        <thead>
                            <tr>
                                <th>Adjudicator</th>
                                <th>Causality</th>
                                <th>Severity</th>
                                <th>Expectedness</th>
                                <th>Framework</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($adjudications as $adj): ?>
                                <tr>
                                    <td><?= htmlspecialchars($adj['adjudicator_name']) ?></td>
                                    <td>
                                        <span class="chip
                                            <?= $adj['causality'] === 'Definite' || $adj['causality'] === 'Probable' ? 'red white-text' : '' ?>
                                            <?= $adj['causality'] === 'Possible' ? 'orange white-text' : '' ?>
                                            <?= $adj['causality'] === 'Unrelated' ? 'grey white-text' : '' ?>
                                        ">
                                            <?= htmlspecialchars($adj['causality']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($adj['severity']) ?></td>
                                    <td><?= htmlspecialchars($adj['expectedness']) ?></td>
                                    <td><?= htmlspecialchars($adj['framework']) ?></td>
                                    <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($adj['submitted_at']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Majority Consensus Preview -->
<?php if (count($adjudications) >= 1): ?>
<div class="row">
    <div class="col s12">
        <div class="card blue lighten-5">
            <div class="card-content">
                <span class="card-title">
                    <i class="material-icons left blue-text">analytics</i>
                    Computed Majority Consensus
                </span>

                <?php if ($majCausality && $majSeverity && $majExpectedness): ?>
                    <p class="green-text"><i class="material-icons tiny">check_circle</i> Majority consensus can be computed</p>
                <?php else: ?>
                    <p class="orange-text"><i class="material-icons tiny">warning</i> No clear majority - arbitration required</p>
                <?php endif; ?>

                <table class="striped" style="margin-top: 20px;">
                    <tbody>
                        <tr>
                            <td style="width: 200px;"><strong>Causality</strong></td>
                            <td>
                                <?php if ($majCausality): ?>
                                    <span class="chip green white-text"><?= htmlspecialchars($majCausality) ?></span>
                                <?php else: ?>
                                    <span class="chip orange white-text">No Majority - Arbitration Needed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Severity</strong></td>
                            <td>
                                <?php if ($majSeverity): ?>
                                    <span class="chip blue white-text"><?= htmlspecialchars($majSeverity) ?></span>
                                <?php else: ?>
                                    <span class="chip orange white-text">No Majority</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Expectedness</strong></td>
                            <td>
                                <?php if ($majExpectedness): ?>
                                    <span class="chip blue white-text"><?= htmlspecialchars($majExpectedness) ?></span>
                                <?php else: ?>
                                    <span class="chip orange white-text">No Majority</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Existing Consensus (if any) -->
<?php if ($existingConsensus): ?>
<div class="row">
    <div class="col s12">
        <div class="card green lighten-5">
            <div class="card-content">
                <span class="card-title">
                    <i class="material-icons left green-text">check_circle</i>
                    Existing Consensus Decision
                </span>

                <table class="striped">
                    <tbody>
                        <tr>
                            <td style="width: 200px;"><strong>Method</strong></td>
                            <td><span class="chip"><?= htmlspecialchars(ucfirst($existingConsensus['method'])) ?></span></td>
                        </tr>
                        <tr>
                            <td><strong>Causality</strong></td>
                            <td><span class="chip green white-text"><?= htmlspecialchars($existingConsensus['causality']) ?></span></td>
                        </tr>
                        <tr>
                            <td><strong>Severity</strong></td>
                            <td><?= htmlspecialchars($existingConsensus['severity']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Expectedness</strong></td>
                            <td><?= htmlspecialchars($existingConsensus['expectedness']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Decided At</strong></td>
                            <td><?= htmlspecialchars($existingConsensus['decided_at']) ?></td>
                        </tr>
                        <?php if ($existingConsensus['rationale']): ?>
                        <tr>
                            <td style="vertical-align: top;"><strong>Rationale</strong></td>
                            <td><?= nl2br(htmlspecialchars($existingConsensus['rationale'])) ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Consensus Form Card -->
<div class="row">
    <div class="col s12">
        <div class="card">
            <div class="card-content">
                <span class="card-title">
                    <i class="material-icons left green-text">fact_check</i>
                    <?= $existingConsensus ? 'Update Consensus' : 'Finalize Consensus' ?>
                </span>

                <form id="consensus-form" method="post" action="../api/consensus.php">
                    <input type="hidden" name="case_event_id" value="<?= $case_event_id ?>">

                    <div class="row">
                        <div class="input-field col s12">
                            <textarea
                                name="rationale"
                                id="rationale"
                                class="materialize-textarea"
                                data-length="2000"
                                rows="6"><?= $existingConsensus ? htmlspecialchars($existingConsensus['rationale']) : '' ?></textarea>
                            <label for="rationale">Rationale (Optional)</label>
                            <span class="helper-text">
                                Document the consensus process, explain any arbitration decisions, note dissenting views
                            </span>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col s12">
                            <button type="submit" class="btn-large waves-effect waves-light green">
                                <i class="material-icons left">check_circle</i>
                                <?= $existingConsensus ? 'Update Consensus' : 'Compute & Save Consensus' ?>
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
        <div class="card">
            <div class="card-content">
                <span class="card-title">
                    <i class="material-icons left blue-text">info</i>
                    Instructions
                </span>
                <ul>
                    <li>• Review all adjudications submitted by the review team</li>
                    <li>• Check the computed majority consensus above</li>
                    <li>• If majority exists, click "Compute & Save" to finalize</li>
                    <li>• If no majority (arbitration required), document your decision in the rationale</li>
                    <li>• The consensus will be saved and the case status updated</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Table Utilities Script -->
<script src="assets/js/table-utils.js"></script>

<script>
// Initialize Materialize components
document.addEventListener('DOMContentLoaded', function() {
    M.CharacterCounter.init(document.querySelectorAll('textarea[data-length]'));
    M.updateTextFields();

    // Initialize table sorting and searching
    const adjTable = document.querySelector('.striped.highlight.responsive-table');
    if (adjTable) {
        initializeTable('.striped.highlight.responsive-table', '#search-adjudications');
    }
});

// Handle form submission
document.getElementById('consensus-form').addEventListener('submit', async function(e) {
    e.preventDefault();

    const form = e.target;
    const btn = form.querySelector('button[type="submit"]');
    const formData = new FormData(form);

    btn.disabled = true;
    btn.innerHTML = '<i class="material-icons left">hourglass_empty</i>Saving...';

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(formData)
        });

        if (!response.ok) {
            const text = await response.text();
            throw new Error(`HTTP ${response.status}: ${text}`);
        }

        const result = await response.json();

        if (result.ok) {
            M.toast({ html: '<i class="material-icons left">check_circle</i>Consensus saved successfully!', classes: 'green' });
            setTimeout(() => {
                window.location.href = 'consensus.php?case_event_id=<?= $case_event_id ?>&saved=1';
            }, 1000);
        } else {
            throw new Error(result.error || 'Failed to save consensus');
        }
    } catch (error) {
        console.error('Consensus save error:', error);
        M.toast({ html: '<i class="material-icons left">error</i>Failed to save: ' + error.message, classes: 'red' });
        btn.disabled = false;
        btn.innerHTML = '<i class="material-icons left">check_circle</i>Compute & Save Consensus';
    }
});
</script>

<?php require_once __DIR__ . '/../inc/templates/footer_light.php'; ?>
