<?php
require_once __DIR__ . '/../inc/auth.php'; if(isset($_SESSION['user'])){ header('Location: ../public/dashboard.php'); exit; }
$err=$_SESSION['login_error']??null; unset($_SESSION['login_error']);
?><!doctype html><html><head><meta charset="utf-8"><title>PHOENIX Adjudication - Login</title><link rel="stylesheet" href="/assets/styles.css"></head><body><div class="container"><h1>PHOENIX Adjudication</h1><?php if($err): ?><div class="alert error"><?= htmlspecialchars($err) ?></div><?php endif; ?><form method="post" action="../api/auth.php"><label>Email <input type="email" name="email" required></label><label>Password <input type="password" name="password" required></label><button type="submit">Login</button></form></div></body></html>
