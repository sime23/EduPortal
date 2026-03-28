<?php
/**
 * includes/check_auth.php
 * Included at the top of every protected page.
 * Checks login status and handles role-based access control.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = $_SESSION['user'] ?? null;
if (!isset($_SESSION['user_id']) || !$user) {
    header('Location: /ass/public/index.php');
    exit;
}

/**
 * enforceRole()
 * Usage: enforceRole('admin') or enforceRole(['teacher', 'admin'])
 */
function enforceRole($allowedRoles) {
    global $user;
    $role = $user['role'];
    
    if (is_string($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    
    if (!in_array($role, $allowedRoles, true)) {
        // Redirect to 403 Forbidden page
        header('Location: /ass/public/403.php');
        exit;
    }
}
