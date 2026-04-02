<?php
/**
 * index.php – Login / Authentication Page
 * ────────────────────────────────────────
 * Handles both GET (render form) and POST (process login).
 * On success: redirects to dashboard with session data.
 */
session_start();

// Redirect already-authenticated users straight to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard');
    exit;
}

require_once 'includes/db.php';

$error = '';

// ── POST: Process login form ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs (PDO handles injection; trim handles whitespace of input)
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    // Basic server-side validation
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Fetch user by email exclusively
        $stmt = $pdo->prepare('SELECT id, full_name, email, password, role, profile_pic FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        // Verify password hash with PHP's built-in password_verify
        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session ID to prevent session-fixation attacks
            session_regenerate_id(true);

            // Store user info in session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = [
                'id'        => $user['id'],
                'full_name' => $user['full_name'],
                'email'     => $user['email'],
                'role'      => $user['role'],
                'profile_pic' => $user['profile_pic'],
            ];

            // Admins go to admin dashboard
            if ($user['role'] === 'admin') {
                header('Location: /admin');
            } else {
                header('Location: /dashboard');
            }
            exit;
        } else {
            // Generic error avoids leaking whether email or password was wrong
            $error = 'Invalid credentials or role mismatch. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – EduPortal</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="login-shell">

    <!-- Left decorative panel -->
    <div class="login-visual">
        <!-- Spline 3D Background -->
        <spline-viewer url="https://prod.spline.design/Oc1uJe9MNub3kmWm/scene.splinecode" style="position: absolute; inset: 0; width: 100%; height: 100%; min-width: 10px; min-height: 10px; z-index: 0; display: block;"></spline-viewer>

        <div class="login-visual-content" style="position: relative; z-index: 1; pointer-events: none;">
            <div class="login-visual-logo">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                </svg>
            </div>
            <h1>EduPortal</h1>
            <p>Your centralized assignment management platform</p>
            <div class="login-features">
                <div class="login-feature">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 12 2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg>
                    Submit assignments securely
                </div>
                <div class="login-feature">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Track deadlines at a glance
                </div>
                <div class="login-feature">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    View grades and feedback instantly
                </div>
            </div>

        </div>
    </div>

    <!-- Right form panel -->
    <div class="login-form-panel">
        <div class="login-form-inner">
            <h2>Welcome back</h2>
            <p>Sign in to continue to your portal</p>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/" novalidate>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input
                        class="form-control"
                        type="email"
                        id="email"
                        name="email"
                        placeholder="you@example.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        required autocomplete="email"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrapper">
                        <input
                            class="form-control"
                            type="password"
                            id="password"
                            name="password"
                            placeholder="••••••••"
                            required autocomplete="current-password"
                        >
                        <button type="button" class="password-toggle" onclick="togglePass()" aria-label="Show/hide password">
                            <svg id="eyeIcon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width:100%;margin-top:0.5rem;">
                    Sign In
                </button>
            </form>
        </div>
    </div>
</div>

<script type="module" src="https://unpkg.com/@splinetool/viewer@1.12.73/build/spline-viewer.js"></script>
<script>
// No Javascript role handling required anymore

// Password visibility toggle
function togglePass() {
    const input = document.getElementById('password');
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    // Swap icon
    document.getElementById('eyeIcon').innerHTML = isText
        ? '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'
        : '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
}
</script>
</body>
</html>
