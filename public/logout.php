<?php
require_once __DIR__ . '/../inc/auth.php'; session_destroy(); header('Location: ../public/index.php');
