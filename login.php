<?php
session_start();
require_once 'config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

if (isset($_GET['error']) && $_GET['error'] == 'timeout') {
    $error = 'Your session has expired due to inactivity. Please sign in again.';
} else {
    $error = '';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'Active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Login success
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['department'] = $user['department'];
            $_SESSION['last_activity'] = time();

            header("Location: " . BASE_URL . "index.php");
            exit();
        } else {
            $error = "Invalid username or password, or account is inactive.";
        }
    } catch (Exception $e) {
        $error = "An error occurred. Please try again later.";
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Hana Check Sheet Online</title>
    <link href="assets/libs/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/libs/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('assets/images/login-bg.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            padding: 3rem;
        }

        .login-logo {
            width: 80px;
            height: 80px;
            background: #4f46e5;
            color: white;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1.5rem;
            box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
        }

        .form-control {
            border-radius: 12px;
            padding: 0.75rem 1.25rem;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .form-control:focus {
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
            border-color: #4f46e5;
        }

        .btn-login {
            border-radius: 12px;
            padding: 0.75rem;
            font-weight: 600;
            background: #4f46e5;
            border: none;
            box-shadow: 0 4px 6px rgba(79, 70, 229, 0.2);
            transition: all 0.3s;
        }

        .btn-login:hover {
            background: #4338ca;
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(79, 70, 229, 0.3);
        }
    </style>
</head>

<body>

    <div class="login-card">
        <div class="text-center mb-4">
            <div class="login-logo">
                <i class="fas fa-microchip"></i>
            </div>
            <h3 class="fw-bold text-dark">Welcome Back</h3>
            <p class="text-muted small">Hana Check Sheet Online System</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 small rounded-3 mb-4">
                <i class="fas fa-exclamation-circle me-1"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-bold text-uppercase text-muted">EN Number</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i
                            class="fas fa-id-card text-muted"></i></span>
                    <input type="text" name="username" class="form-control border-start-0"
                        placeholder="Enter your EN Number" required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label small fw-bold text-uppercase text-muted">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-lock text-muted"></i></span>
                    <input type="password" name="password" class="form-control border-start-0" placeholder="••••••••"
                        required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-login w-100 mb-3 text-white">
                Sign In <i class="fas fa-arrow-right ms-2"></i>
            </button>
            <div class="text-center">
                <a href="#" class="text-decoration-none small text-muted">Forgot password?</a>
            </div>
        </form>
    </div>

    <script src="assets/libs/bootstrap/bootstrap.bundle.min.js"></script>
</body>

</html>