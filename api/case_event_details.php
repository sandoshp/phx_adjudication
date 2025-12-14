<?php
/**
 * API endpoint for case event details management
 * Handles:
 * - GET: Fetch case event details with evidence (LAB/ICD)
 * - POST (mark_absent): Mark event as absent
 * - POST (update_details): Update evidence details (LAB/ICD)
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

try {
  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

  if ($method === 'GET') {
    // Fetch case event details with evidence
    $case_event_id = (int)($_GET['case_event_id'] ?? 0);
    if ($case_event_id <= 0) {
      http_response_code(400);
      echo json_encode(['error' => 'case_event_id required']);
      exit;
    }

    // Get case event with dictionary_event details
    $stmt = $pdo->prepare("
      SELECT ce.*, de.diagnosis, de.category, de.source, de.icd10, de.ctcae_term,
             de.admission_grade, de.caveat1, de.outcome1, de.caveat2, de.outcome2,
             de.caveat3, de.outcome3, de.lcat1, de.lcat1_met1, de.lcat1_met2,
             de.lcat1_notmet, de.lcat2, de.lcat2_met1, de.lcat2_notmet,
             p.patient_code
      FROM case_event ce
      JOIN dictionary_event de ON de.id = ce.dict_event_id
      JOIN patients p ON p.id = ce.patient_id
      WHERE ce.id = ?
    ");
    $stmt->execute([$case_event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
      http_response_code(404);
      echo json_encode(['error' => 'Case event not found']);
      exit;
    }

    // Fetch evidence based on source type
    $evidence = [];
    if ($event['source'] === 'LAB') {
      $labStmt = $pdo->prepare("
        SELECT * FROM evidence_lab WHERE case_event_id = ? ORDER BY sample_datetime DESC
      ");
      $labStmt->execute([$case_event_id]);
      $evidence = $labStmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($event['source'] === 'ICD') {
      $icdStmt = $pdo->prepare("
        SELECT * FROM evidence_icd WHERE case_event_id = ? ORDER BY event_date DESC
      ");
      $icdStmt->execute([$case_event_id]);
      $evidence = $icdStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
      'ok' => true,
      'event' => $event,
      'evidence' => $evidence
    ]);
    exit;
  }

  if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) $data = $_POST;

    $action = $data['action'] ?? '';
    $case_event_id = (int)($data['case_event_id'] ?? 0);

    if ($case_event_id <= 0) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'case_event_id required']);
      exit;
    }

    // Mark absent action
    if ($action === 'mark_absent') {
      $is_absent = (int)($data['is_absent'] ?? 1);
      $stmt = $pdo->prepare("UPDATE case_event SET is_absent = ? WHERE id = ?");
      $stmt->execute([$is_absent, $case_event_id]);

      echo json_encode(['ok' => true, 'message' => 'Event marked as absent']);
      exit;
    }

    // Update details action
    if ($action === 'update_details') {
      // Get event source type
      $sourceStmt = $pdo->prepare("
        SELECT de.source FROM case_event ce
        JOIN dictionary_event de ON de.id = ce.dict_event_id
        WHERE ce.id = ?
      ");
      $sourceStmt->execute([$case_event_id]);
      $sourceRow = $sourceStmt->fetch(PDO::FETCH_ASSOC);

      if (!$sourceRow) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Case event not found']);
        exit;
      }

      $source = $sourceRow['source'];
      $pdo->beginTransaction();

      try {
        if ($source === 'LAB') {
          // Update/insert lab evidence
          $test = trim($data['test'] ?? '');
          $value = trim($data['value'] ?? '');
          $units = trim($data['units'] ?? '');
          $ref_low = trim($data['ref_low'] ?? '');
          $ref_high = trim($data['ref_high'] ?? '');
          $threshold_met = isset($data['threshold_met']) ? (int)$data['threshold_met'] : null;
          $sample_datetime = trim($data['sample_datetime'] ?? '');

          if (empty($test)) {
            throw new Exception('Test name is required for LAB evidence');
          }

          // Check if evidence already exists
          $existingStmt = $pdo->prepare("SELECT id FROM evidence_lab WHERE case_event_id = ? LIMIT 1");
          $existingStmt->execute([$case_event_id]);
          $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

          if ($existing) {
            // Update existing
            $updateStmt = $pdo->prepare("
              UPDATE evidence_lab
              SET test = ?, value = ?, units = ?, ref_low = ?, ref_high = ?,
                  threshold_met = ?, sample_datetime = ?
              WHERE id = ?
            ");
            $updateStmt->execute([
              $test, $value, $units, $ref_low, $ref_high, $threshold_met,
              $sample_datetime ?: null, $existing['id']
            ]);
          } else {
            // Insert new
            $insertStmt = $pdo->prepare("
              INSERT INTO evidence_lab (case_event_id, test, value, units, ref_low, ref_high, threshold_met, sample_datetime)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([
              $case_event_id, $test, $value, $units, $ref_low, $ref_high, $threshold_met,
              $sample_datetime ?: null
            ]);
          }
        } elseif ($source === 'ICD') {
          // Update/insert ICD evidence
          $icd_code = trim($data['icd_code'] ?? '');
          $event_date = trim($data['event_date'] ?? '');
          $encounter_id = trim($data['encounter_id'] ?? '');
          $details = trim($data['details'] ?? '');

          if (empty($icd_code)) {
            throw new Exception('ICD code is required for ICD evidence');
          }

          // Check if evidence already exists
          $existingStmt = $pdo->prepare("SELECT id FROM evidence_icd WHERE case_event_id = ? LIMIT 1");
          $existingStmt->execute([$case_event_id]);
          $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

          if ($existing) {
            // Update existing
            $updateStmt = $pdo->prepare("
              UPDATE evidence_icd
              SET icd_code = ?, event_date = ?, encounter_id = ?, details = ?
              WHERE id = ?
            ");
            $updateStmt->execute([
              $icd_code, $event_date ?: null, $encounter_id, $details, $existing['id']
            ]);
          } else {
            // Insert new
            $insertStmt = $pdo->prepare("
              INSERT INTO evidence_icd (case_event_id, icd_code, event_date, encounter_id, details)
              VALUES (?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([
              $case_event_id, $icd_code, $event_date ?: null, $encounter_id, $details
            ]);
          }
        }

        $pdo->commit();
        echo json_encode(['ok' => true, 'message' => 'Details updated successfully']);
      } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
      }
      exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid action']);
    exit;
  }

  http_response_code(405);
  echo json_encode(['error' => 'Method Not Allowed']);
} catch (Throwable $e) {
  error_log('case_event_details.php error: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Server error']);
}
