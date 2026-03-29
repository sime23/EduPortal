<?php
/**
 * admin.php – Admin Dashboard
 * ───────────────────────────
 * Restricted to users with role = 'admin'.
 * Allows creating new Student or Teacher accounts.
 * Lists all users with edit/delete capabilities.
 */
session_start();
require_once '../includes/db.php';
require_once '../includes/check_auth.php';

enforceRole('admin');

$error   = '';
$success = '';

// ── POST: Create a new user account ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_user') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email']     ?? '');
    $password = trim($_POST['password']  ?? '');
    $role     = $_POST['role']           ?? 'student';

    if (strlen($fullName) < 2) {
        $error = 'Full name must be at least 2 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($role !== 'teacher') {
        $error = 'Admins can only create Teacher accounts.';
    } else {
        // Check for duplicate email
        $dup = $pdo->prepare('SELECT id FROM users WHERE email = :email');
        $dup->execute([':email' => $email]);
        if ($dup->fetch()) {
            $error = 'An account with that email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $ins  = $pdo->prepare('INSERT INTO users (full_name, email, password, role) VALUES (:fn, :em, :pw, :role)');
            $ins->execute([':fn' => $fullName, ':em' => $email, ':pw' => $hash, ':role' => $role]);
            $success = "Account for {$fullName} ({$role}) created successfully!";
        }
    }
}

// ── POST: Delete a user ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
    $delId = (int)($_POST['user_id'] ?? 0);
    if ($delId > 0 && $delId !== (int)$user['id']) { // can't delete self
        // Ensure the deleted user is actually a teacher (prevent deleting students or other admins)
        $del = $pdo->prepare('DELETE FROM users WHERE id = :id AND role = "teacher"');
        $del->execute([':id' => $delId]);
        if ($del->rowCount() > 0) {
            $success = 'Teacher deleted successfully.';
        } else {
            $error = 'Could not delete user. Either they do not exist or they are not a teacher.';
        }
    } else {
        $error = 'Cannot delete yourself or another admin.';
    }
}

// ── Fetch all users ──────────────────────────────────────────
$allUsers = $pdo->query('SELECT id, full_name, email, role, created_at FROM users ORDER BY role, full_name')->fetchAll();

// ── Stats ─────────────────────────────────────────────────────
$totalStudents  = count(array_filter($allUsers, fn($u) => $u['role'] === 'student'));
$totalTeachers  = count(array_filter($allUsers, fn($u) => $u['role'] === 'teacher'));
$totalAssignments = $pdo->query('SELECT COUNT(*) FROM assignments')->fetchColumn();
$totalSubmissions = $pdo->query('SELECT COUNT(*) FROM submissions')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel – EduPortal</title>
    <link rel="stylesheet" href="/ass/assets/style.css">
    <style>
        .role-badge-admin    { background:#f3e8ff; color:#7c3aed; }
        .role-badge-teacher  { background:#e0f2fe; color:#0369a1; }
        .role-badge-student  { background:#dcfce7; color:#15803d; }
        .user-table-row:hover { background: var(--bg-hover, #f8fafc); }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require_once '../includes/header.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Admin Panel</h1>
            <p>Manage users and monitor system activity</p>
        </div>

        <?php if ($error):   ?><div class="alert alert-error">  <?= htmlspecialchars($error)   ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon stat-icon-cyan">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <div><div class="stat-value"><?= $totalStudents ?></div><div class="stat-label">Students</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-blue">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                </div>
                <div><div class="stat-value"><?= $totalTeachers ?></div><div class="stat-label">Teachers</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-green">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <div><div class="stat-value"><?= $totalAssignments ?></div><div class="stat-label">Assignments</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-orange">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 12 2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg>
                </div>
                <div><div class="stat-value"><?= $totalSubmissions ?></div><div class="stat-label">Submissions</div></div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:360px 1fr;gap:1.5rem;align-items:start">

            <!-- Create User Form -->
            <div class="card">
                <div class="card-title mb-2">➕ Create New Teacher Account</div>
                <form method="POST" action="index.php" novalidate>
                    <input type="hidden" name="action" value="create_user">

                    <div class="form-group">
                        <label class="form-label" for="full_name">Full Name</label>
                        <input type="text" name="full_name" id="full_name" class="form-control"
                               placeholder="e.g. Jane Doe" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" name="email" id="email" class="form-control"
                               placeholder="jane@example.com" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" name="password" id="password" class="form-control"
                               placeholder="Min 6 characters" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="role">Role</label>
                        <select name="role" id="role" class="form-control" required>
                            <option value="teacher">📚 Teacher</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%">
                        Create Account
                    </button>
                </form>
            </div>

            <!-- Users Table -->
            <div class="card">
                <div class="card-title mb-2">👥 All Users (<?= count($allUsers) ?>)</div>
                <?php if (empty($allUsers)): ?>
                    <p class="text-muted">No users found.</p>
                <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead><tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th>Action</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($allUsers as $u): ?>
                        <tr class="user-table-row">
                            <td class="text-muted" style="font-size:.8rem"><?= str_pad($u['id'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td class="fw-600" style="font-size:.875rem"><?= htmlspecialchars($u['full_name']) ?></td>
                            <td class="text-muted" style="font-size:.825rem"><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <span class="badge role-badge-<?= $u['role'] ?>">
                                    <?= ucfirst($u['role']) ?>
                                </span>
                            </td>
                            <td class="text-muted" style="font-size:.8rem"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                            <td>
                                <?php if ($u['role'] !== 'admin'): ?>
                                <form method="POST" action="index.php" style="display:inline"
                                      onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($u['full_name'])) ?>? This cannot be undone.')">
                                    <input type="hidden" name="action"  value="delete_user">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:.8rem">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>
