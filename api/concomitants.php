<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

try {
  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

  if ($method === 'GET') {
    $patient_id = (int)($_GET['patient_id'] ?? 0);
    if ($patient_id <= 0) { http_response_code(400); echo json_encode(['error'=>'patient_id required']); exit; }

    $st = $pdo->prepare("
      SELECT pcd.drug_id, d.name, 
             DATE_FORMAT(pcd.start_date,'%Y-%m-%d') AS start_date,
             DATE_FORMAT(pcd.stop_date,'%Y-%m-%d')  AS stop_date
      FROM patient_concomitant_drug pcd
      JOIN drugs d ON d.id = pcd.drug_id
      WHERE pcd.patient_id = ?
      ORDER BY d.name
    ");
    $st->execute([$patient_id]);
    echo json_encode($st->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) $data = $_POST;

    $patient_id = (int)($data['patient_id'] ?? 0);

    // Support both new format (concomitants array) and old format (drug_ids array)
    $concomitants = $data['concomitants'] ?? null;

    if ($patient_id <= 0) {
      http_response_code(400); echo json_encode(['ok'=>false,'error'=>'patient_id required']); exit;
    }

    // Get patient's index drug to exclude
    $ix = $pdo->prepare("SELECT index_drug_id FROM patients WHERE id=?");
    $ix->execute([$patient_id]);
    $row = $ix->fetch(PDO::FETCH_ASSOC);
    if (!$row) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Patient not found']); exit; }
    $index_drug_id = (int)$row['index_drug_id'];

    // Process concomitants array format: [{drug_id, start_date, stop_date}, ...]
    if (is_array($concomitants)) {
      $validConcomitants = [];
      foreach ($concomitants as $c) {
        $did = (int)($c['drug_id'] ?? 0);
        if ($did <= 0 || $did === $index_drug_id) continue;

        $sd = trim((string)($c['start_date'] ?? ''));
        $ed = trim((string)($c['stop_date'] ?? ''));

        // Validate dates or set to NULL
        $sd = ($sd && DateTime::createFromFormat('Y-m-d', $sd)?->format('Y-m-d') === $sd) ? $sd : null;
        $ed = ($ed && DateTime::createFromFormat('Y-m-d', $ed)?->format('Y-m-d') === $ed) ? $ed : null;

        $validConcomitants[] = ['drug_id' => $did, 'start_date' => $sd, 'stop_date' => $ed];
      }

      $pdo->beginTransaction();
      try {
        $deleted = 0;
        $count = 0;

        if (empty($validConcomitants)) {
          // If no drugs selected, delete ALL concomitants for this patient
          $delStmt = $pdo->prepare("DELETE FROM patient_concomitant_drug WHERE patient_id = ?");
          $delStmt->execute([$patient_id]);
          $deleted = $delStmt->rowCount();
        } else {
          // Get list of drug IDs to keep
          $drugIdsToKeep = array_column($validConcomitants, 'drug_id');

          // Delete any existing concomitants NOT in the list
          $placeholders = implode(',', array_fill(0, count($drugIdsToKeep), '?'));
          $delSql = "DELETE FROM patient_concomitant_drug
                     WHERE patient_id = ? AND drug_id NOT IN ($placeholders)";
          $delStmt = $pdo->prepare($delSql);
          $delStmt->execute(array_merge([$patient_id], $drugIdsToKeep));
          $deleted = $delStmt->rowCount();

          // Insert or update the selected drugs with their individual dates
          $ins = $pdo->prepare("
            INSERT INTO patient_concomitant_drug (patient_id, drug_id, start_date, stop_date)
            VALUES (:pid, :did, :sd, :ed)
            ON DUPLICATE KEY UPDATE start_date = VALUES(start_date), stop_date = VALUES(stop_date)
          ");
          foreach ($validConcomitants as $c) {
            $ins->execute([
              ':pid' => $patient_id,
              ':did' => $c['drug_id'],
              ':sd' => $c['start_date'],
              ':ed' => $c['stop_date']
            ]);
            $count += $ins->rowCount() > 0 ? 1 : 0;
          }
        }

        $pdo->commit();
        echo json_encode(['ok'=>true,'inserted'=>$count,'deleted'=>$deleted]);
      } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('concomitants insert error: '.$e->getMessage());
        http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Server error']);
      }
      exit;
    }

    // Legacy format support: drug_ids array with single start/stop dates
    $drug_ids   = $data['drug_ids'] ?? [];
    $start_date = trim((string)($data['start_date'] ?? ''));
    $stop_date  = trim((string)($data['stop_date']  ?? ''));

    if (!is_array($drug_ids) || count($drug_ids) === 0) {
      http_response_code(400); echo json_encode(['ok'=>false,'error'=>'concomitants[] or drug_ids[] required']); exit;
    }

    // Validate dates (YYYY-MM-DD) or set to NULL
    $sd = ($start_date && DateTime::createFromFormat('Y-m-d', $start_date)?->format('Y-m-d') === $start_date) ? $start_date : null;
    $ed = ($stop_date  && DateTime::createFromFormat('Y-m-d', $stop_date )?->format('Y-m-d')  === $stop_date ) ? $stop_date  : null;

    // Normalise IDs and exclude index drug
    $drug_ids = array_values(array_unique(array_map('intval', $drug_ids)));
    $drug_ids = array_values(array_filter($drug_ids, fn($id) => $id > 0 && $id !== $index_drug_id));

    if (empty($drug_ids)) {
      echo json_encode(['ok'=>true,'inserted'=>0]);
      exit;
    }

    $pdo->beginTransaction();
    try {
      // Legacy mode: just insert/update without deleting
      $ins = $pdo->prepare("
        INSERT INTO patient_concomitant_drug (patient_id, drug_id, start_date, stop_date)
        VALUES (:pid, :did, :sd, :ed)
        ON DUPLICATE KEY UPDATE start_date = VALUES(start_date), stop_date = VALUES(stop_date)
      ");
      $count = 0;
      foreach ($drug_ids as $did) {
        $ins->execute([':pid'=>$patient_id, ':did'=>$did, ':sd'=>$sd, ':ed'=>$ed]);
        $count += $ins->rowCount() > 0 ? 1 : 0;
      }
      $pdo->commit();
      echo json_encode(['ok'=>true,'inserted'=>$count]);
    } catch (Throwable $e) {
      $pdo->rollBack();
      error_log('concomitants insert error: '.$e->getMessage());
      http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Server error']);
    }
    exit;
  }

  http_response_code(405); echo json_encode(['error'=>'Method Not Allowed']);
} catch (Throwable $e) {
  error_log('concomitants.php error: '.$e->getMessage());
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Server error']);
}
