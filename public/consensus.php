<?php
require_once __DIR__ . '/../inc/auth.php'; require_once __DIR__ . '/../inc/db.php'; require_login(); $u=current_user(); if(!in_array($u['role'],['chair','coordinator','admin'],true)){ http_response_code(403); echo "Forbidden"; exit; }
$case_event_id=(int)($_GET['id']??0); $st=$pdo->prepare("SELECT ce.*, de.diagnosis, de.category FROM case_event ce JOIN dictionary_event de ON de.id=ce.dict_event_id WHERE ce.id=?"); $st->execute([$case_event_id]); $ev=$st->fetch(); if(!$ev){ http_response_code(404); echo "Case event not found"; exit; }
?><!doctype html><html><head><meta charset="utf-8"><title>Consensus: <?= htmlspecialchars($ev['diagnosis']) ?></title><link rel="stylesheet" href="/assets/styles.css"><script src="/assets/js/api.js" defer></script></head><body>
<header><div class="brand">PHOENIX Adjudication</div><nav><a href="/dashboard.php">Back</a><a href="/logout.php">Logout</a></nav></header>
<main class="container"><section class="card"><h2>Consensus for <?= htmlspecialchars($ev['diagnosis']) ?> (<?= htmlspecialchars($ev['category']) ?>)</h2>
<form method="post" action="/api/consensus.php"><input type="hidden" name="case_event_id" value="<?= $case_event_id ?>"><label>Rationale<textarea name="rationale" rows="4"></textarea></label><button type="submit">Compute Majority / Save</button></form>
</section></main></body></html>
