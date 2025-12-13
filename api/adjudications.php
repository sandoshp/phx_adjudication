<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();


function is_ajax_request(): bool {
  $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
  $ctype  = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
  $xreq   = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
  return str_contains($accept, 'application/json')
      || str_contains($ctype,  'application/json')
      || $xreq === 'xmlhttprequest';
}

header('Content-Type: application/json; charset=utf-8');

$m = $_SERVER['REQUEST_METHOD'];

if ($m === 'POST') {
  $raw = file_get_contents('php://input');
  $payload = json_decode($raw, true);
  
  // Fallback to form-encoded POST if JSON is empty
if (!is_array($payload) || $payload === []) {
  $payload = $_POST ?: [];
  // normalise arrays from form POST
  if (isset($_POST['suspected_concomitants'])) {
    $payload['suspected_concomitants'] = (array)$_POST['suspected_concomitants'];
  }
  if (isset($_POST['missing_info'])) {
    $payload['missing_info'] = (array)$_POST['missing_info'];
  }
}
  
  if (!is_array($payload)) { 
  http_response_code(400); 
  echo json_encode(['error'=>'Invalid JSON']); 
  exit; 
  }

  $cid        = (int)($payload['case_event_id'] ?? 0);
  $framework  = $payload['framework'] ?? 'WHO-UMC';
  $responses  = $payload['responses'] ?? null;
  $auto       = $payload['auto_score'] ?? null;
  $causality  = $payload['causality'] ?? null;
  $severity   = $payload['severity'] ?? null;
  $expected   = $payload['expectedness'] ?? null;
  $idxattr    = $payload['index_attribution'] ?? null; // 'Yes'|'No'|'Indeterminate'
  $sus        = $payload['suspected_concomitants'] ?? [];
  $rat        = $payload['rationale'] ?? '';
  $miss       = $payload['missing_info'] ?? [];

  if (!$cid || !$causality || !$severity || !$expected || !$idxattr) {
    http_response_code(400); echo json_encode(['error'=>'Missing required fields']); exit;
  }

  $adjudicatorId = (int)($_SESSION['user']['id'] ?? 0);

  /* --- Fetch case_event first (no join) --- */
  $ceStmt = $pdo->prepare("SELECT dict_event_id, patient_id FROM case_event WHERE id = ?");
  $ceStmt->execute([$cid]);
  $ce = $ceStmt->fetch(PDO::FETCH_ASSOC);
  if (!$ce) {
    http_response_code(404);
    echo json_encode(['error'=>'Case event not found (id='.$cid.')']);
    exit;
  }
  $dict_event_id = (int)$ce['dict_event_id'];
  $patient_id    = (int)$ce['patient_id'];

  /* --- Fetch patient (may be missing; tolerate) --- */
  $idxStmt = $pdo->prepare("SELECT index_drug_id FROM patients WHERE id = ?");
  $idxStmt->execute([$patient_id]);
  $index_drug_id = $idxStmt->fetchColumn();
  $index_drug_id = $index_drug_id !== false ? (int)$index_drug_id : 0;

  /* --- Build relevant sets --- */
  $relStmt = $pdo->prepare("SELECT drug_id FROM drug_event_map WHERE dict_event_id = ?");
  $relStmt->execute([$dict_event_id]);
  $relevantSet = array_map('intval', $relStmt->fetchAll(PDO::FETCH_COLUMN));

  $pcdStmt = $pdo->prepare("SELECT drug_id FROM patient_concomitant_drug WHERE patient_id = ?");
  $pcdStmt->execute([$patient_id]);
  $patientConcomSet = array_map('intval', $pcdStmt->fetchAll(PDO::FETCH_COLUMN));

  // Normalise and filter suspect list to (relevant ∩ patient concomitants)
  $sus = array_values(array_unique(array_filter(array_map('intval', (array)$sus), fn($x)=>$x>0)));
  $sus = array_values(array_filter($sus, fn($x)=> in_array($x, $relevantSet, true) && in_array($x, $patientConcomSet, true)));

  $pdo->beginTransaction();
  try {
    // Upsert adjudication
    $st = $pdo->prepare(
      "INSERT INTO adjudication
       (case_event_id,adjudicator_id,framework,responses,auto_score,causality,severity,expectedness,index_attribution,suspected_concomitants,rationale,missing_info)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
       ON DUPLICATE KEY UPDATE
         framework=VALUES(framework),
         responses=VALUES(responses),
         auto_score=VALUES(auto_score),
         causality=VALUES(causality),
         severity=VALUES(severity),
         expectedness=VALUES(expectedness),
         index_attribution=VALUES(index_attribution),
         suspected_concomitants=VALUES(suspected_concomitants),
         rationale=VALUES(rationale),
         missing_info=VALUES(missing_info),
         submitted_at=CURRENT_TIMESTAMP"
    );
    $st->execute([
      $cid,
      $adjudicatorId,
      $framework,
      $responses !== null ? json_encode($responses, JSON_UNESCAPED_UNICODE) : null,
      $auto === null ? null : (int)$auto,
      $causality,
      $severity,
      $expected,
      $idxattr,
      json_encode($sus, JSON_UNESCAPED_UNICODE),
      $rat,
      json_encode($miss, JSON_UNESCAPED_UNICODE)
    ]);

    // Get adjudication id
    $aidStmt = $pdo->prepare("SELECT id FROM adjudication WHERE case_event_id=? AND adjudicator_id=?");
    $aidStmt->execute([$cid, $adjudicatorId]);
    $adjudication_id = (int)$aidStmt->fetchColumn();

    // Sync adjudication_drug
    if ($adjudication_id) {
      // Index drug only if we have it AND it's relevant to this outcome
      if ($index_drug_id && in_array($index_drug_id, $relevantSet, true)) {
        $upIdx = $pdo->prepare("
          INSERT INTO adjudication_drug (adjudication_id, drug_id, role, attribution)
          VALUES (:aid, :did, 'index', :attr)
          ON DUPLICATE KEY UPDATE attribution = VALUES(attribution)
        ");
        $upIdx->execute([':aid'=>$adjudication_id, ':did'=>$index_drug_id, ':attr'=>$idxattr]);
      } else {
        // If previously present but not relevant anymore, remove it
        $delIdx = $pdo->prepare("DELETE FROM adjudication_drug WHERE adjudication_id=? AND role='index'");
        $delIdx->execute([$adjudication_id]);
      }

      // Replace concomitant set
      $pdo->prepare("DELETE FROM adjudication_drug WHERE adjudication_id=? AND role='concomitant'")
          ->execute([$adjudication_id]);
      if (!empty($sus)) {
        $insCon = $pdo->prepare("
          INSERT INTO adjudication_drug (adjudication_id, drug_id, role, attribution)
          VALUES (:aid, :did, 'concomitant', 'Yes')
          ON DUPLICATE KEY UPDATE attribution='Yes'
        ");
        foreach ($sus as $did) {
          $insCon->execute([':aid'=>$adjudication_id, ':did'=>$did]);
        }
      }
    }

    // If 3+ adjudications exist for this case_event, mark submitted
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM adjudication WHERE case_event_id=?");
    $cnt->execute([$cid]);
    if ((int)$cnt->fetchColumn() >= 3) {
      $upd = $pdo->prepare("UPDATE case_event SET status='submitted' WHERE id=?");
      $upd->execute([$cid]);
    }

    $pdo->commit();
	
	if (is_ajax_request()) {
		  echo json_encode(['ok' => true]);
		  exit;
		}
		// Non-AJAX fallback: redirect back to the case page with a flash flag
		// The API lives in /api/, the page in /public/, so go up one level.
		header('Content-Type: text/html; charset=utf-8');
		header('Location: ../public/case_event.php?id=' . $cid . '&saved=1', true, 303);
		exit;
  } catch (Throwable $e) {
    $pdo->rollBack();
    error_log('adjudications save error: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['error'=>'Server error']); exit;
  }
}

if ($m === 'GET') {
  $cid = (int)($_GET['case_event_id'] ?? 0);
  if (!$cid) { http_response_code(400); echo json_encode(['error'=>'Missing case_event_id']); exit; }
  $st = $pdo->prepare("SELECT * FROM adjudication WHERE case_event_id=? AND adjudicator_id=?");
  $st->execute([$cid, $_SESSION['user']['id']]);
  echo json_encode($st->fetch() ?: []); exit;
}

http_response_code(405); echo "Method not allowed";
