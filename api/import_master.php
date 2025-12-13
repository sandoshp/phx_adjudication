<?php
require_once __DIR__ . '/../inc/db.php'; require_once __DIR__ . '/../inc/auth.php'; require_login(); 
$u=$_SESSION['user']; if(!in_array($u['role'],['admin','coordinator'],true)){ http_response_code(403); echo "Forbidden"; exit; }
if($_SERVER['REQUEST_METHOD']!=='POST'){ http_response_code(405); echo "Method not allowed"; exit; }
if(!isset($_FILES['csv']) || $_FILES['csv']['error']!==UPLOAD_ERR_OK){ echo "Upload failed"; exit; }
$path=$_FILES['csv']['tmp_name']; if(($h=fopen($path,"r"))===FALSE){ echo "Failed to open"; exit; }
$header=fgetcsv($h); $map=array_flip($header); $rows=[]; while(($d=fgetcsv($h))!==FALSE){ $rows[]=$d; } fclose($h);
$pdo->beginTransaction();
$selDrug=$pdo->prepare("SELECT id FROM drugs WHERE name=?"); $insDrug=$pdo->prepare("INSERT INTO drugs(name,genes) VALUES(?,?)");
$selEvent=$pdo->prepare("SELECT id FROM dictionary_event WHERE diagnosis=? AND IFNULL(icd10,'')=IFNULL(?, '') AND source=?");
$insEvent=$pdo->prepare("INSERT INTO dictionary_event(diagnosis,category,ctcae_term,admission_grade,icd10,source,caveat1,outcome1,caveat2,outcome2,caveat3,outcome3,lcat1,lcat1_met1,lcat1_met2,lcat1_notmet,lcat2,lcat2_met1,lcat2_notmet) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
$insMap=$pdo->prepare("INSERT IGNORE INTO drug_event_map(drug_id,dict_event_id,expected_flag) VALUES (?,?,NULL)");
$cd=$ce=$cm=0;
foreach($rows as $r){
  $get=function($n)use($r,$map){ return $r[$map[$n]]??null; };
  $drug=strtoupper(trim($get('Drug'))); $genes=trim($get('Gene')??''); $dx=trim($get('Diagnosis')??''); $cat=trim($get('Category')??''); $ct=trim($get('CTCAE v5')??''); $ag=trim($get('Admission Diagnosis Grade')??''); $icd=trim($get('ICD10')??''); $src=strtoupper(trim($get('Source')??'ICD'));
  $c1=$get('Caveat1'); $o1=$get('Outcome1'); $c2=$get('Caveat2'); $o2=$get('Outcome2'); $c3=$get('Caveat3'); $o3=$get('Outcome3');
  $l1=$get('LCAT1'); $l1m1=$get('LCAT1met1'); $l1m2=$get('LCAT1met2'); $l1nm=$get('LCAT1notmet'); $l2=$get('LCAT2'); $l2m1=$get('LCAT2met1'); $l2nm=$get('LCAT2notmet');
  if(!$drug||!$dx||!$src) continue; if($icd==='-') $icd=null;
  $selDrug->execute([$drug]); $did=$selDrug->fetchColumn(); if(!$did){ $insDrug->execute([$drug,$genes]); $did=$pdo->lastInsertId(); $cd++; }
  $selEvent->execute([$dx,$icd,$src]); $eid=$selEvent->fetchColumn(); if(!$eid){ $insEvent->execute([$dx,$cat,$ct,$ag,$icd,$src,$c1,$o1,$c2,$o2,$c3,$o3,$l1,$l1m1,$l1m2,$l1nm,$l2,$l2m1,$l2nm]); $eid=$pdo->lastInsertId(); $ce++; }
  $insMap->execute([$did,$eid]); $cm++;
}
$pdo->commit(); echo "Imported: $cd drugs, $ce events, $cm mappings.";
