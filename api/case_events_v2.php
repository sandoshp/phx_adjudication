<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();
$user = current_user(); // expects ['id'=>..., ...]

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

        // Join case_event -> dictionary_event; include adjudication count and consensus flag
        $sql = "
            SELECT
                ce.id,
                ce.patient_id,
                ce.status,
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

        // Idempotent insert: add one case_event per dictionary_event that isn't already linked to this patient
        // (We do NOT modify schema; we avoid duplicates via NOT EXISTS)
        $pdo->beginTransaction();
        try {
            // Optional: set creator (nullable in your schema)
            $creatorId = isset($user['id']) ? (int)$user['id'] : null;

            // Insert as 'open'; leave onset_datetime NULL (adjudicators can set)
            // Bring everything from dictionary_event; link by dict_event_id only
            $sql = "
                INSERT INTO case_event (patient_id, dict_event_id, status, created_by)
                SELECT :pid, de.id, 'open', :uid
                FROM dictionary_event de
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM case_event ce
                    WHERE ce.patient_id = :pid2
                      AND ce.dict_event_id = de.id
                )
            ";
            $ins = $pdo->prepare($sql);
            $ins->execute([
                ':pid'  => $patient_id,
                ':pid2' => $patient_id,
                ':uid'  => $creatorId,
            ]);
            $inserted = $ins->rowCount(); // number of rows inserted

            $pdo->commit();
            echo json_encode(['ok' => true, 'inserted' => $inserted]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
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
