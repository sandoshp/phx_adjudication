<?php
require_once __DIR__ . '/../inc/db.php'; require_once __DIR__ . '/../inc/auth.php'; require_login();
if($_SERVER['REQUEST_METHOD']==='GET'){ $st=$pdo->query("SELECT id,name FROM drugs ORDER BY name"); header('Content-Type: application/json'); echo json_encode($st->fetchAll()); exit; }
http_response_code(405); echo "Method not allowed";
