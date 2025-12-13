<?php
/**
 * PHOENIX Adjudication - Logout
 */
require_once __DIR__ . '/../inc/auth.php';
session_destroy();
header('Location: login.php');
exit;
