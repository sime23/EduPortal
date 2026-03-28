<?php
/**
 * profile.php – User Profile & System Requirements Page
 * ───────────────────────────────────────────────────────
 * Allows users to update their full_name and email.
 * Optionally change password.
 * Displays system requirements section.
 */
session_start();
require_once '../includes/db.php';
require_once '../includes/check_auth.php';

$userId  = $user['id'];
$error   = '';
$success = '';

// ── POST: Update profile info ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName   = trim($_POST['full_name']        ?? '');
    $email      = trim($_POST['email']            ?? '');
    $newPass    = trim($_POST['new_password']     ?? '');
    $confirmPass = trim($_POST['confirm_password'] ?? '');

    // Validate name and email
    if (strlen($fullName) < 2) {
        $error = 'Please enter your full name (at least 2 characters).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check email isn't already used by another account
        $emailCheck = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id');
        $emailCheck->execute([':email' => $email, ':id' => $userId]);
        if ($emailCheck->fetch()) {
            $error = 'That email is already in use by another account.';
        } else {
            if ($newPass !== '') {
                // Password change requested
                if (strlen($newPass) < 6) {
                    $error = 'New password must be at least 6 characters.';
                } elseif ($newPass !== $confirmPass) {
                    $error = 'Passwords do not match.';
                }
            }

            if (!$error) {
                if ($newPass !== '') {
                    // Update with new password hash
                    $hash = password_hash($newPass, PASSWORD_BCRYPT);
                    $upd = $pdo->prepare('UPDATE users SET full_name=:fn, email=:em, password=:pw WHERE id=:id');
                    $upd->execute([':fn' => $fullName, ':em' => $email, ':pw' => $hash, ':id' => $userId]);
                } else {
                    $upd = $pdo->prepare('UPDATE users SET full_name=:fn, email=:em WHERE id=:id');
                    $upd->execute([':fn' => $fullName, ':em' => $email, ':id' => $userId]);
                }

                // Refresh session user data
                $_SESSION['user']['full_name'] = $fullName;
                $_SESSION['user']['email']     = $email;
                $user = $_SESSION['user'];
                $success = 'Profile updated successfully!';
            }
        }
    }
}

// Re-fetch latest user data from DB to display
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute([':id' => $userId]);
$dbUser = $stmt->fetch();

if (!$dbUser) {
    // If the database was reset but the session remained, log them out
    session_destroy();
    header('Location: index.php');
    exit;
}

$initials = strtoupper(substr($dbUser['full_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile – EduPortal</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app-shell">
    <?php require_once '../includes/header.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>My Profile</h1>
            <p>Manage your account details and view system information</p>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start">

            <!-- Profile Form -->
            <div class="card">
                <?php if ($error):   ?><div class="alert alert-error">  <?= htmlspecialchars($error)   ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

                <div style="text-align:center;margin-bottom:1.5rem">
                    <div class="profile-avatar-ring"><?= $initials ?></div>
                    <div style="font-size:.9rem;font-weight:600"><?= htmlspecialchars($dbUser['full_name']) ?></div>
                    <span class="badge <?= $dbUser['role'] === 'teacher' ? 'badge-teacher' : 'badge-student' ?>" style="margin-top:.35rem">
                        <?= ucfirst($dbUser['role']) ?>
                    </span>
                </div>

                <form method="POST" action="profile.php" novalidate>
                    <div class="form-group">
                        <label class="form-label" for="full_name">Full Name</label>
                        <input type="text" name="full_name" id="full_name" class="form-control"
                               value="<?= htmlspecialchars($dbUser['full_name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" name="email" id="email" class="form-control"
                               value="<?= htmlspecialchars($dbUser['email']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="new_password">New Password <span class="text-muted">(leave blank to keep current)</span></label>
                        <input type="password" name="new_password" id="new_password" class="form-control"
                               placeholder="Min 6 characters" autocomplete="new-password">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                               placeholder="Repeat new password" autocomplete="new-password">
                    </div>

                    <div style="display:flex;justify-content:flex-end">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>

            <!-- System Requirements -->
            <div>
                <div class="card">
                    <div class="card-title mb-2">System Requirements</div>

                    <p style="font-size:.85rem;color:var(--text-secondary);margin-bottom:1rem">
                        This portal requires the following to function correctly:
                    </p>

                    <div class="requirements-list">
                        <div class="requirement-item">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            <div>
                                <div style="font-weight:600;font-size:.85rem">Supported File Formats</div>
                                <div style="font-size:.78rem;color:var(--text-muted)">.PDF, .DOCX, .DOC</div>
                            </div>
                        </div>
                        <div class="requirement-item">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                            <div>
                                <div style="font-weight:600;font-size:.85rem">Maximum File Size</div>
                                <div style="font-size:.78rem;color:var(--text-muted)">10 MB per submission</div>
                            </div>
                        </div>
                        <div class="requirement-item">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                            <div>
                                <div style="font-weight:600;font-size:.85rem">Browser Compatibility</div>
                                <div style="font-size:.78rem;color:var(--text-muted)">Chrome 90+, Firefox 88+, Safari 14+, Edge 90+</div>
                            </div>
                        </div>
                        <div class="requirement-item">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <div>
                                <div style="font-weight:600;font-size:.85rem">Security</div>
                                <div style="font-size:.78rem;color:var(--text-muted)">HTTPS recommended · Sessions expire after inactivity</div>
                            </div>
                        </div>
                        <div class="requirement-item">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <div>
                                <div style="font-weight:600;font-size:.85rem">Submission Policy</div>
                                <div style="font-size:.78rem;color:var(--text-muted)">Multiple submissions allowed for corrections</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Account Info -->
                <div class="card mt-3">
                    <div class="card-title mb-2">Account Information</div>
                    <div style="display:flex;flex-direction:column;gap:.6rem">
                        <div style="display:flex;justify-content:space-between;font-size:.85rem">
                            <span class="text-muted">Account ID</span>
                            <span class="fw-600">#<?= str_pad($dbUser['id'], 5, '0', STR_PAD_LEFT) ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:.85rem">
                            <span class="text-muted">Role</span>
                            <span class="fw-600"><?= ucfirst($dbUser['role']) ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:.85rem">
                            <span class="text-muted">Member Since</span>
                            <span class="fw-600"><?= date('M j, Y', strtotime($dbUser['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>
