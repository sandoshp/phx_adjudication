<?php
require_once __DIR__ . '/../inc/db.php'; 
require_once __DIR__ . '/../inc/auth.php';
if($_SERVER['REQUEST_METHOD']==='POST'){ $email=$_POST['email']??''; $password=$_POST['password']??'';
 $st=$pdo->prepare("SELECT * FROM users WHERE email=? AND active=1"); $st->execute([$email]); $u=$st->fetch();
 if($u && password_verify($password,$u['password_hash'])){ $_SESSION['user']=['id'=>$u['id'],'email'=>$u['email'],'name'=>$u['name'],'role'=>$u['role']]; header('Location: ../public/dashboard.php'); exit; }
 $_SESSION['login_error']="Invalid credentials"; header('Location: ../public/index.php'); exit; } http_response_code(405); echo "Method not allowed";
