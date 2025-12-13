<?php
$config = require __DIR__ . '/config.php';
try { $pdo = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']};charset={$config['db_charset']}", $config['db_user'], $config['db_pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); }
catch (PDOException $e) { http_response_code(500); echo "Database connection failed."; if ($config['debug']) echo " Error: ".$e->getMessage(); exit; }
