<?php
session_start();
function require_login(){ if(!isset($_SESSION['user'])){ header('Location: /index.php'); exit; } }
function current_user(){ return $_SESSION['user'] ?? null; }
function require_role($roles){ $u=current_user(); if(!$u||!in_array($u['role'], (array)$roles,true)){ http_response_code(403); echo "Forbidden"; exit; } }
