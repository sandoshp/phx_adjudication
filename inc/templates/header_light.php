<?php
/**
 * PHOENIX Adjudication System - Header Template (LIGHT THEME)
 *
 * Materialize CSS based responsive header with light, readable theme
 */

if (!isset($pageTitle)) $pageTitle = 'PHOENIX Adjudication';
if (!isset($user)) $user = current_user();

// Get config safely
$config = ['version' => '1.0.0'];
if (file_exists(__DIR__ . '/../config.php')) {
    $config = require __DIR__ . '/../config.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= htmlspecialchars($pageTitle) ?> - PHOENIX</title>

    <!-- Materialize CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css" rel="stylesheet">

    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <!-- Custom Light Theme -->
    <link rel="stylesheet" href="assets/css/theme-light.css?v=<?= $config['version'] ?? '1.0.0' ?>">

    <!-- Page-specific CSS -->
    <?php if (isset($customCSS)): ?>
        <?php foreach ((array)$customCSS as $css): ?>
            <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>?v=<?= $config['version'] ?? '1.0.0' ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <style>
        nav .brand-logo { font-size: 1.8rem; }
        @media only screen and (max-width: 600px) {
            nav .brand-logo { font-size: 1.4rem; }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav>
        <div class="nav-wrapper container">
            <a href="dashboard_materialize.php" class="brand-logo">
                <i class="material-icons left hide-on-small-only">local_hospital</i>
                PHOENIX Adjudication
            </a>
            <a href="#" data-target="mobile-nav" class="sidenav-trigger">
                <i class="material-icons">menu</i>
            </a>
            <ul class="right hide-on-med-and-down">
                <?php if ($user): ?>
                    <li>
                        <a href="dashboard_materialize.php">
                            <i class="material-icons left">dashboard</i>
                            Dashboard
                        </a>
                    </li>
                    <?php if (in_array($user['role'], ['admin', 'coordinator'])): ?>
                        <li>
                            <a href="admin/import.php">
                                <i class="material-icons left">upload_file</i>
                                Import
                            </a>
                        </li>
                    <?php endif; ?>
                    <li>
                        <a class="dropdown-trigger" href="#!" data-target="user-dropdown">
                            <i class="material-icons left">account_circle</i>
                            <?= htmlspecialchars($user['name']) ?>
                            <i class="material-icons right">arrow_drop_down</i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- User Dropdown -->
    <?php if ($user): ?>
        <ul id="user-dropdown" class="dropdown-content">
            <li>
                <a href="logout.php">
                    <i class="material-icons">exit_to_app</i>
                    Logout
                </a>
            </li>
        </ul>
    <?php endif; ?>

    <!-- Mobile Sidenav -->
    <ul class="sidenav" id="mobile-nav">
        <?php if ($user): ?>
            <li>
                <div class="user-view" style="background-color: #0d6efd;">
                    <a href="#">
                        <i class="material-icons white-text circle large">account_circle</i>
                    </a>
                    <a href="#">
                        <span class="white-text name"><?= htmlspecialchars($user['name']) ?></span>
                    </a>
                    <a href="#">
                        <span class="white-text email"><?= htmlspecialchars($user['email'] ?? '') ?></span>
                    </a>
                </div>
            </li>
            <li>
                <a href="dashboard_materialize.php">
                    <i class="material-icons">dashboard</i>
                    Dashboard
                </a>
            </li>
            <?php if (in_array($user['role'], ['admin', 'coordinator'])): ?>
                <li>
                    <a href="admin/import.php">
                        <i class="material-icons">upload_file</i>
                        Import Data
                    </a>
                </li>
            <?php endif; ?>
            <li><div class="divider"></div></li>
            <li>
                <a href="logout.php">
                    <i class="material-icons">exit_to_app</i>
                    Logout
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <!-- Main Content Container -->
    <main class="container" style="margin-top: 20px; margin-bottom: 40px; min-height: 70vh;">
