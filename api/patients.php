<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $sql = "SELECT p.id,
                       p.patient_code,
                       DATE_FORMAT(p.randomisation_date, '%Y-%m-%d') AS randomisation_date,
                       DATE_FORMAT(p.followup_end_date, '%Y-%m-%d') AS followup_end_date,
                       p.index_drug_id,
                       d.name AS index_drug_name
                FROM patients p
                LEFT JOIN drugs d ON d.id = p.index_drug_id
                ORDER BY p.id DESC
                LIMIT 200";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            // allow x-www-form-urlencoded fallback
            $data = $_POST;
        }

        $patient_code       = trim((string)($data['patient_code'] ?? ''));
        $randomisation_date = trim((string)($data['randomisation_date'] ?? ''));
        $index_drug_id      = isset($data['index_drug_id']) ? (int)$data['index_drug_id'] : 0;

        if ($patient_code === '' || $randomisation_date === '' || $index_drug_id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
            exit;
        }

        // Validate date format YYYY-MM-DD
        $dt = DateTime::createFromFormat('Y-m-d', $randomisation_date);
        if (!$dt || $dt->format('Y-m-d') !== $randomisation_date) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid randomisation_date']);
            exit;
        }

        // Follow-up end = +3 months
        $fu = clone $dt;
        $fu->modify('+3 months');
        $followup_end_date = $fu->format('Y-m-d');

        $stmt = $pdo->prepare("INSERT INTO patients
            (patient_code, randomisation_date, followup_end_date, index_drug_id)
            VALUES (:code, :rand, :fu, :drug)");
        $stmt->execute([
            ':code' => $patient_code,
            ':rand' => $randomisation_date,
            ':fu'   => $followup_end_date,
            ':drug' => $index_drug_id
        ]);

        echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
} catch (Throwable $e) {
    error_log('patients.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
