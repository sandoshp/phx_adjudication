<?php
require_once __DIR__ . '/../inc/db.php'; require_once __DIR__ . '/../inc/auth.php'; require_once __DIR__ . '/../inc/helpers.php'; require_login();
$m=$_SERVER['REQUEST_METHOD'];
if($m==='POST'){ $pid=(int)($_POST['patient_id']??0); if(!$pid) json_response(['error'=>'Missing patient_id'],400);
 $st=$pdo->prepare("SELECT p.*, d.id AS drug_id FROM patients p JOIN drugs d ON d.id=p.index_drug_id WHERE p.id=?"); $st->execute([$pid]); $p=$st->fetch(); if(!$p) json_response(['error'=>'Patient not found'],404);
 $map=$pdo->prepare("SELECT dict_event_id FROM drug_event_map WHERE drug_id=?"); $map->execute([$p['drug_id']]); $eids=$map->fetchAll(PDO::FETCH_COLUMN);
 $ins=$pdo->prepare("INSERT INTO case_event (patient_id,dict_event_id,status,created_by) VALUES (?,?, 'open', ?)"); $count=0;
 foreach($eids as $eid){ $chk=$pdo->prepare("SELECT id FROM case_event WHERE patient_id=? AND dict_event_id=?"); $chk->execute([$pid,$eid]); if(!$chk->fetchColumn()){ $ins->execute([$pid,$eid,$_SESSION['user']['id']]); $count++; } }
 json_response(['ok'=>true,'created'=>$count]); }
if($m==='GET'){ $pid=(int)($_GET['patient_id']??0); if(!$pid) json_response(['error'=>'Missing patient_id'],400);
 $st=$pdo->prepare("SELECT ce.id, ce.status, de.diagnosis, de.category, de.icd10, de.source,
 (SELECT COUNT(*) FROM adjudication a WHERE a.case_event_id=ce.id) AS adjudications_count,
 (SELECT COUNT(*) FROM adjudication a WHERE a.case_event_id=ce.id AND a.adjudicator_id=?) AS my_submission,
 (SELECT 1 FROM consensus c WHERE c.case_event_id=ce.id) AS has_consensus
 FROM case_event ce JOIN dictionary_event de ON de.id=ce.dict_event_id WHERE ce.patient_id=? ORDER BY de.category,de.diagnosis");
 $st->execute([$_SESSION['user']['id'],$pid]); json_response($st->fetchAll()); }
http_response_code(405); echo "Method not allowed";
