<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

header('Content-Type: application/json; charset=utf-8'); // add this

$m = $_SERVER['REQUEST_METHOD'];

if ($m === 'POST') {
  $payload = json_decode(file_get_contents('php://input'), true);
  if (!is_array($payload)) { http_response_code(400); echo json_encode(['error'=>'Invalid JSON']); exit; }

  $cid        = (int)($payload['case_event_id'] ?? 0);
  $framework  = $payload['framework'] ?? 'WHO-UMC';
  $responses  = $payload['responses'] ?? null;
  $auto       = $payload['auto_score'] ?? null; // <- was "None" (invalid in PHP), fix to null
  $causality  = $payload['causality'] ?? null;
  $severity   = $payload['severity'] ?? null;
  $expected   = $payload['expectedness'] ?? null;
  $idxattr    = $payload['index_attribution'] ?? null;
  $sus        = $payload['suspected_concomitants'] ?? [];
  $rat        = $payload['rationale'] ?? '';
  $miss       = $payload['missing_info'] ?? [];

  if (!$cid || !$causality || !$severity || !$expected || !$idxattr) {
    http_response_code(400); echo json_encode(['error'=>'Missing required fields']); exit;
  }

  // (Optional) ensure case_event exists
  $chk = $pdo->prepare('SELECT 1 FROM case_event WHERE id = ?');
  $chk->execute([$cid]);
  if (!$chk->fetchColumn()) { http_response_code(404); echo json_encode(['error'=>'Case event not found']); exit; }

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
    $_SESSION['user']['id'],
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

  // 3+ adjudications → mark submitted
  $cnt = $pdo->prepare("SELECT COUNT(*) FROM adjudication WHERE case_event_id=?");
  $cnt->execute([$cid]);
  if ((int)$cnt->fetchColumn() >= 3) {
    $upd = $pdo->prepare("UPDATE case_event SET status='submitted' WHERE id=?");
    $upd->execute([$cid]);
  }

  echo json_encode(['ok'=>true]); exit;
}

if ($m === 'GET') {
  $cid = (int)($_GET['case_event_id'] ?? 0);
  if (!$cid) { http_response_code(400); echo json_encode(['error'=>'Missing case_event_id']); exit; }
  $st = $pdo->prepare("SELECT * FROM adjudication WHERE case_event_id=? AND adjudicator_id=?");
  $st->execute([$cid, $_SESSION['user']['id']]);
  echo json_encode($st->fetch() ?: []); exit;
}

http_response_code(405); echo "Method not allowed";
