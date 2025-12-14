<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();
$user = current_user();

try {
  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

  // ---------- GET: list case events for a patient ----------
  if ($method === 'GET') {
    $patient_id = (int)($_GET['patient_id'] ?? ($_GET['id'] ?? 0));
    if ($patient_id <= 0) {
      http_response_code(400);
      echo json_encode(['error' => 'patient_id required']);
      exit;
    }

    $sql = "
      SELECT
        ce.id,
        ce.patient_id,
		ce.dict_event_id,
        ce.status,
        ce.is_absent,
        ce.onset_datetime,
        ce.phenotype_override,
        ce.is_lab_primary,
        de.category,
        de.diagnosis,
        de.icd10,
        de.source,
        CAST(ce.status = 'consensus' AS UNSIGNED) AS has_consensus,
        CAST(COALESCE(adj.cnt, 0) AS UNSIGNED)    AS adjudications_count
      FROM case_event ce
      JOIN dictionary_event de ON de.id = ce.dict_event_id
      LEFT JOIN (
        SELECT case_event_id, COUNT(*) AS cnt
        FROM adjudication
        GROUP BY case_event_id
      ) adj ON adj.case_event_id = ce.id
      WHERE ce.patient_id = :pid
      ORDER BY ce.id DESC
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':pid' => $patient_id]);
    echo json_encode($st->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
    exit;
  }

  // ---------- POST: generate from dictionary for a patient ----------
  if ($method === 'POST') {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = $_POST;

    $action     = $data['action'] ?? '';
    $patient_id = (int)($data['patient_id'] ?? 0);

    if ($action !== 'generate' || $patient_id <= 0) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'Invalid payload']);
      exit;
    }

    // Verify patient exists (and fetch index_drug_id)
    $chk = $pdo->prepare("SELECT index_drug_id FROM patients WHERE id = ?");
    $chk->execute([$patient_id]);
    $prow = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$prow) {
      http_response_code(404);
      echo json_encode(['ok' => false, 'error' => 'Patient not found']);
      exit;
    }

    $creatorId = isset($user['id']) ? (int)$user['id'] : null;

    /**
     * Insert ONLY events mapped to the patient's drugs:
     * - patient’s index_drug_id
     * - UNION patient_concomitant_drug.drug_id
     * Then DISTINCT dict_event_ids (in case multiple drugs map to the same event).
     * Idempotency: LEFT JOIN existing case_event rows and keep only those with ce.id IS NULL.
     */
    $pdo->beginTransaction();
    try {
      $sql = "
        INSERT INTO case_event (patient_id, dict_event_id, status, created_by)
        SELECT :pid, dem.dict_event_id, 'open', :uid
        FROM drug_event_map dem
        JOIN (
          SELECT p.index_drug_id AS drug_id
          FROM patients p
          WHERE p.id = :pid_a
          UNION
          SELECT pcd.drug_id
          FROM patient_concomitant_drug pcd
          WHERE pcd.patient_id = :pid_b
        ) pd ON pd.drug_id = dem.drug_id
        LEFT JOIN case_event ce
          ON ce.patient_id = :pid_c
         AND ce.dict_event_id = dem.dict_event_id
        WHERE ce.id IS NULL
        GROUP BY dem.dict_event_id
      ";
      $ins = $pdo->prepare($sql);
      $ins->execute([
        ':pid'   => $patient_id,
        ':uid'   => $creatorId,
        ':pid_a' => $patient_id,
        ':pid_b' => $patient_id,
        ':pid_c' => $patient_id,
      ]);
      $inserted = $ins->rowCount();

      $pdo->commit();
      echo json_encode(['ok' => true, 'inserted' => $inserted]);
    } catch (Throwable $e) {
      $pdo->rollBack();
      error_log('case_events generate error: '.$e->getMessage());
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => 'Server error']);
    }
    exit;
  }

  http_response_code(405);
  echo json_encode(['error' => 'Method Not Allowed']);
} catch (Throwable $e) {
  error_log('case_events.php error: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Server error']);
}
