<?php
/**
 * PHOENIX Adjudication - Index (redirects to appropriate page)
 */
require_once __DIR__ . '/../inc/auth.php';

if (isset($_SESSION['user'])) {
    header('Location: dashboard_materialize.php');
} else {
    header('Location: login.php');
}
exit;
