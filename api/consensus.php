<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../inc/db.php'; require_once __DIR__ . '/../inc/auth.php'; require_login();
function require_roles($r){ $u=$_SESSION['user']??null; if(!$u || !in_array($u['role'],$r,true)){ http_response_code(403); echo json_encode(['error'=>'Forbidden']); exit; } }
require_roles(['chair','coordinator','admin']);
$m=$_SERVER['REQUEST_METHOD'];
if($m==='POST'){ $cid=(int)($_POST['case_event_id']??0); if(!$cid){ http_response_code(400); echo json_encode(['error'=>'Missing case_event_id']); exit; }
 $st=$pdo->prepare("SELECT causality,severity,expectedness,suspected_concomitants FROM adjudication WHERE case_event_id=?"); $st->execute([$cid]); $rows=$st->fetchAll();
 if(count($rows)<3){ http_response_code(400); echo json_encode(['error'=>'Need 3 adjudications']); exit; }
 $maj=function($arr){ $c=array_count_values($arr); arsort($c); $k=array_keys($c); if(!count($k)) return null; if(count($k)>=2 && $c[$k[0]]==$c[$k[1]]) return null; return $k[0]; };
 $ca=$maj(array_column($rows,'causality')); $se=$maj(array_column($rows,'severity')); $ex=$maj(array_column($rows,'expectedness'));
 $sus=[]; foreach($rows as $r){ $arr=json_decode($r['suspected_concomitants']??'[]',true)?:[]; foreach($arr as $d){ $sus[$d]=true; } } $sus=array_keys($sus);
 $method=($ca&&$se&&$ex)?'majority':'arbitration'; $rationale=$_POST['rationale']??null;
 $up=$pdo->prepare("INSERT INTO consensus(case_event_id,method,decided_by,causality,severity,expectedness,suspected_drugs,rationale) VALUES (?,?,?,?,?,?,?,?)
 ON DUPLICATE KEY UPDATE method=VALUES(method),decided_by=VALUES(decided_by),causality=VALUES(causality),severity=VALUES(severity),expectedness=VALUES(expectedness),suspected_drugs=VALUES(suspected_drugs),rationale=VALUES(rationale),decided_at=CURRENT_TIMESTAMP");
 $up->execute([$cid,$method,$_SESSION['user']['id'],$ca?:'Unable',$se?:'Moderate',$ex?:'Not_Assessable',json_encode($sus),$rationale]);
 $pdo->prepare("UPDATE case_event SET status='consensus' WHERE id=?")->execute([$cid]);
 echo json_encode(['ok'=>true,'method'=>$method]); exit; }
if($m==='GET'){ $cid=(int)($_GET['case_event_id']??0); if(!$cid){ http_response_code(400); echo json_encode(['error'=>'Missing case_event_id']); exit; }
 $st=$pdo->prepare("SELECT * FROM consensus WHERE case_event_id=?"); $st->execute([$cid]); echo json_encode($st->fetch()?:[]); exit; }
http_response_code(405); echo json_encode(['error'=>'Method not allowed']);
