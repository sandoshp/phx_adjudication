<?php
session_start();

function require_login() {
    if (!isset($_SESSION['user'])) {
        // Use relative path for Windows XAMPP compatibility
        // If we're in /public/, go to index.php
        // If we're in /public/admin/, go to ../index.php
        $redirect = 'index.php';

        // Check if we're in a subdirectory
        $currentPath = $_SERVER['PHP_SELF'] ?? '';
        if (strpos($currentPath, '/admin/') !== false) {
            $redirect = '../index.php';
        }

        header('Location: ' . $redirect);
        exit;
    }
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function require_role($roles) {
    $u = current_user();
    if (!$u || !in_array($u['role'], (array)$roles, true)) {
        http_response_code(403);
        echo "Forbidden";
        exit;
    }
}
