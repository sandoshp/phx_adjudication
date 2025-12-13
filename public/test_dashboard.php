<?php
/**
 * Simple test page - no authentication required
 * Use this to test if Materialize CSS and paths are working
 */

$pageTitle = 'Test Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - PHOENIX</title>

    <!-- Materialize CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css" rel="stylesheet">

    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <!-- Custom Dark Theme -->
    <link rel="stylesheet" href="assets/css/theme-dark.css">

    <style>
        body { background-color: #0f172a; color: #e2e8f0; }
        .test-card { margin-top: 50px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col s12 test-card">
                <div class="card blue-grey darken-3">
                    <div class="card-content white-text">
                        <span class="card-title">
                            <i class="material-icons left">check_circle</i>
                            Dashboard Test Page
                        </span>
                        <p>If you can see this styled page, the following are working:</p>
                        <ul class="collection">
                            <li class="collection-item green-text">✓ PHP is executing correctly</li>
                            <li class="collection-item green-text">✓ Materialize CSS loaded from CDN</li>
                            <li class="collection-item green-text">✓ Material Icons loaded</li>
                            <li class="collection-item <?= file_exists(__DIR__ . '/assets/css/theme-dark.css') ? 'green-text">✓' : 'red-text">✗' ?> Custom theme file exists</li>
                        </ul>

                        <h5>Environment Info:</h5>
                        <table class="striped">
                            <tbody>
                                <tr>
                                    <td><strong>PHP Version</strong></td>
                                    <td><?= PHP_VERSION ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Server Software</strong></td>
                                    <td><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Document Root</strong></td>
                                    <td><?= $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown' ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Script Path</strong></td>
                                    <td><?= __FILE__ ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Request URI</strong></td>
                                    <td><?= $_SERVER['REQUEST_URI'] ?? 'Unknown' ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Theme File Path</strong></td>
                                    <td><?= __DIR__ . '/assets/css/theme-dark.css' ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Theme File Exists</strong></td>
                                    <td><?= file_exists(__DIR__ . '/assets/css/theme-dark.css') ? 'YES' : 'NO' ?></td>
                                </tr>
                            </tbody>
                        </table>

                        <h5 style="margin-top: 30px;">Session Status:</h5>
                        <p>
                            <?php
                            session_start();
                            if (isset($_SESSION['user'])) {
                                echo '<span class="green-text">✓ Logged in as: ' . htmlspecialchars($_SESSION['user']['email'] ?? 'Unknown') . '</span>';
                            } else {
                                echo '<span class="orange-text">⚠ Not logged in</span>';
                            }
                            ?>
                        </p>

                        <div style="margin-top: 30px;">
                            <?php if (!isset($_SESSION['user'])): ?>
                                <a href="login.php" class="btn blue waves-effect waves-light">
                                    <i class="material-icons left">login</i>
                                    Go to Login
                                </a>
                            <?php else: ?>
                                <a href="dashboard_materialize.php" class="btn blue waves-effect waves-light">
                                    <i class="material-icons left">dashboard</i>
                                    Go to Dashboard
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Materialize JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
</body>
</html>
