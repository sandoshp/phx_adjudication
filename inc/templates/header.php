<?php
/**
 * PHOENIX Adjudication System - Header Template
 *
 * Materialize CSS based responsive header with dark theme
 *
 * Variables expected:
 *   $pageTitle   - Page title (optional, defaults to 'PHOENIX Adjudication')
 *   $user        - Current user array from current_user() (optional)
 *   $customCSS   - Array of additional CSS files (optional)
 *   $customJS    - Array of additional JS files (optional)
 *   $config      - Configuration array (optional)
 */

if (!isset($pageTitle)) $pageTitle = 'PHOENIX Adjudication';
if (!isset($user)) $user = current_user();
if (!isset($config)) $config = require __DIR__ . '/../config.php';
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

    <!-- Custom Dark Theme -->
    <link rel="stylesheet" href="/assets/css/theme-dark.css?v=<?= $config['version'] ?? '1.0.0' ?>">

    <!-- Page-specific CSS -->
    <?php if (isset($customCSS)): ?>
        <?php foreach ((array)$customCSS as $css): ?>
            <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>?v=<?= $config['version'] ?? '1.0.0' ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <style>
        /* Quick fixes for dark theme in nav */
        nav { background-color: #263238 !important; }
        nav .brand-logo { font-size: 1.8rem; }
        @media only screen and (max-width: 600px) {
            nav .brand-logo { font-size: 1.4rem; }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="nav-extended blue-grey darken-4">
        <div class="nav-wrapper container">
            <a href="/dashboard.php" class="brand-logo">
                <i class="material-icons left hide-on-small-only">local_hospital</i>
                PHOENIX Adjudication
            </a>
            <a href="#" data-target="mobile-nav" class="sidenav-trigger">
                <i class="material-icons">menu</i>
            </a>
            <ul class="right hide-on-med-and-down">
                <?php if ($user): ?>
                    <li>
                        <a href="/dashboard.php">
                            <i class="material-icons left">dashboard</i>
                            Dashboard
                        </a>
                    </li>
                    <?php if (in_array($user['role'], ['admin', 'coordinator'])): ?>
                        <li>
                            <a href="/admin/import.php">
                                <i class="material-icons left">upload_file</i>
                                Import
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if (in_array($user['role'], ['chair', 'coordinator', 'admin'])): ?>
                        <li>
                            <a href="/consensus.php">
                                <i class="material-icons left">gavel</i>
                                Consensus
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
                <a href="/profile.php">
                    <i class="material-icons">person</i>
                    Profile
                </a>
            </li>
            <li>
                <a href="/settings.php">
                    <i class="material-icons">settings</i>
                    Settings
                </a>
            </li>
            <li class="divider"></li>
            <li>
                <a href="/logout.php">
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
                <div class="user-view">
                    <div class="background blue-grey darken-3"></div>
                    <a href="/profile.php">
                        <i class="material-icons white-text circle large">account_circle</i>
                    </a>
                    <a href="/profile.php">
                        <span class="white-text name"><?= htmlspecialchars($user['name']) ?></span>
                    </a>
                    <a href="/profile.php">
                        <span class="white-text email"><?= htmlspecialchars($user['email']) ?></span>
                    </a>
                </div>
            </li>
            <li>
                <a href="/dashboard.php">
                    <i class="material-icons">dashboard</i>
                    Dashboard
                </a>
            </li>
            <?php if (in_array($user['role'], ['admin', 'coordinator'])): ?>
                <li>
                    <a href="/admin/import.php">
                        <i class="material-icons">upload_file</i>
                        Import Data
                    </a>
                </li>
            <?php endif; ?>
            <?php if (in_array($user['role'], ['chair', 'coordinator', 'admin'])): ?>
                <li>
                    <a href="/consensus.php">
                        <i class="material-icons">gavel</i>
                        Consensus
                    </a>
                </li>
            <?php endif; ?>
            <li><div class="divider"></div></li>
            <li>
                <a href="/settings.php">
                    <i class="material-icons">settings</i>
                    Settings
                </a>
            </li>
            <li>
                <a href="/logout.php">
                    <i class="material-icons">exit_to_app</i>
                    Logout
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <!-- Main Content Container -->
    <main class="container" style="margin-top: 20px; margin-bottom: 40px; min-height: 70vh;">
