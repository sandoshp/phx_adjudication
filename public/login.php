<?php
/**
 * PHOENIX Adjudication - Login Page (Materialize CSS)
 */
require_once __DIR__ . '/../inc/auth.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user'])) {
    header('Location: dashboard_materialize.php');
    exit;
}

$error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PHOENIX Adjudication</title>

    <!-- Materialize CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css" rel="stylesheet">

    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <!-- Custom Dark Theme -->
    <link rel="stylesheet" href="assets/css/theme-dark.css">

    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            max-width: 450px;
            margin: 20px auto;
        }
        .brand-logo-login {
            font-size: 2.5rem;
            margin-bottom: 30px;
            text-align: center;
        }
        .brand-logo-login i {
            font-size: 3rem;
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col s12">
                <div class="card login-card blue-grey darken-3">
                    <div class="card-content white-text">
                        <div class="brand-logo-login">
                            <i class="material-icons blue-text text-lighten-2">local_hospital</i>
                            <br>
                            <span class="blue-text text-lighten-2">PHOENIX</span>
                        </div>
                        <p class="center-align grey-text">Pharmacogenomic Trial Adjudication System</p>

                        <?php if ($error): ?>
                            <div class="card-panel red darken-2 white-text" style="margin-top: 20px;">
                                <i class="material-icons left">error</i>
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="../api/auth.php" style="margin-top: 30px;">
                            <div class="input-field">
                                <i class="material-icons prefix white-text">email</i>
                                <input id="email" type="email" name="email" class="white-text" required autofocus>
                                <label for="email" class="white-text">Email Address</label>
                            </div>

                            <div class="input-field">
                                <i class="material-icons prefix white-text">lock</i>
                                <input id="password" type="password" name="password" class="white-text" required>
                                <label for="password" class="white-text">Password</label>
                            </div>

                            <div style="margin-top: 30px;">
                                <button class="btn waves-effect waves-light blue btn-large btn-block" type="submit" style="width: 100%;">
                                    <i class="material-icons left">login</i>
                                    Sign In
                                </button>
                            </div>
                        </form>

                        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #455a64;">
                            <p class="center-align grey-text text-lighten-1" style="font-size: 0.9rem;">
                                <i class="material-icons tiny">info</i>
                                Contact your administrator for login credentials
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Test Link -->
                <div class="center-align" style="margin-top: 20px;">
                    <a href="test_dashboard.php" class="grey-text text-lighten-1">
                        <i class="material-icons tiny">build</i>
                        Test Page
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Materialize JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    <script>
        // Auto-focus first field
        document.addEventListener('DOMContentLoaded', function() {
            M.updateTextFields();
        });
    </script>
</body>
</html>
