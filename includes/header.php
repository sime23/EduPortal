<?php
/**
 * header.php – Shared Navigation Header
 */
require_once __DIR__ . '/check_auth.php';

$role     = $user['role'];
$initials = strtoupper(substr($user['full_name'], 0, 1));
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

function navLink(string $href, string $icon, string $label, string $current, string $page): string {
    $active = ($current === $page) ? ' class="active"' : '';
    return "<a href=\"{$href}\"{$active}>{$icon}<span>{$label}</span></a>";
}

$icons = [
    'dashboard' => '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>',
    'upload'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>',
    'calendar'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
    'grades'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>',
    'profile'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    'manage'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
    'admin'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
    'logout'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
];
?>
<nav class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
        </div>
        <div class="brand-text">
            <span class="brand-name">EduPortal</span>
            <span class="brand-role"><?= ucfirst(htmlspecialchars($role)) ?> Panel</span>
        </div>
    </div>
    <div class="sidebar-user">
        <div class="user-avatar"><?= $initials ?></div>
        <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
            <span class="user-email"><?= htmlspecialchars($user['email']) ?></span>
        </div>
    </div>
    <div class="sidebar-nav">
        <p class="nav-section-label">Main Menu</p>
        <?php if ($role === 'admin'): ?>
            <?= navLink('/admin',            $icons['admin'],    'Admin Panel',        $currentPage, 'index') ?>
            <?= navLink('/dashboard',         $icons['dashboard'], 'Main Dashboard',    $currentPage, 'dashboard') ?>
        <?php elseif ($role === 'student'): ?>
            <?= navLink('/dashboard',  $icons['dashboard'], 'Dashboard',         $currentPage, 'dashboard') ?>
            <?= navLink('/deadlines',  $icons['calendar'], 'Deadlines',          $currentPage, 'deadlines') ?>
            <?= navLink('/grades',     $icons['grades'],   'My Grades',          $currentPage, 'grades') ?>
        <?php else: ?>
            <?= navLink('/dashboard',        $icons['dashboard'], 'Dashboard',         $currentPage, 'dashboard') ?>
            <?= navLink('/manage',            $icons['manage'],   'Manage Assignments', $currentPage, 'manage') ?>
            <?= navLink('/manage-students',   $icons['profile'],  'Manage Students',    $currentPage, 'manage-students') ?>
            <?= navLink('/deadlines',         $icons['calendar'], 'Deadlines',          $currentPage, 'deadlines') ?>
            <?= navLink('/grades',            $icons['grades'],   'Grade Work',         $currentPage, 'grades') ?>
        <?php endif; ?>
        <p class="nav-section-label" style="margin-top:1.5rem;">Account</p>
        <?= navLink('/profile', $icons['profile'], 'Profile', $currentPage, 'profile') ?>
        <a href="/logout" class="logout-link"><?= $icons['logout'] ?><span>Logout</span></a>
    </div>
</nav>
<header class="mobile-header">
    <button class="hamburger" id="hamburgerBtn" aria-label="Toggle menu"><span></span><span></span><span></span></button>
    <span class="mobile-title">EduPortal</span>
    <div class="mobile-avatar"><?= $initials ?></div>
</header>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<script>
const sidebar   = document.getElementById('sidebar');
const overlay   = document.getElementById('sidebarOverlay');
const hamburger = document.getElementById('hamburgerBtn');
function toggleSidebar() {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('visible');
    hamburger.classList.toggle('active');
}
hamburger.addEventListener('click', toggleSidebar);
overlay.addEventListener('click', toggleSidebar);
</script>
