<?php
require_once __DIR__ . '/../inc/db.php'; require_once __DIR__ . '/../inc/auth.php'; require_once __DIR__ . '/../inc/helpers.php'; require_login();
$m=$_SERVER['REQUEST_METHOD'];
if($m==='POST'){ $pc=$_POST['patient_code']??''; $rd=$_POST['randomisation_date']??''; $idx=(int)($_POST['index_drug_id']??0);
 if(!$pc||!$rd||!$idx) json_response(['error'=>'Missing fields'],400);
 $fu=followup_end($rd); $st=$pdo->prepare("INSERT INTO patients (patient_code,randomisation_date,followup_end_date,index_drug_id,created_by) VALUES (?,?,?,?,?)");
 $st->execute([$pc,$rd,$fu,$idx,$_SESSION['user']['id']]); json_response(['ok'=>true,'patient_id'=>$pdo->lastInsertId()]); }
if($m==='GET'){ $st=$pdo->query("SELECT p.*, d.name AS index_drug FROM patients p JOIN drugs d ON d.id=p.index_drug_id ORDER BY p.created_at DESC LIMIT 200"); json_response($st->fetchAll()); }
http_response_code(405); echo "Method not allowed";
