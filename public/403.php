<?php
session_start();
$user = $_SESSION['user'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Forbidden – EduPortal</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body style="display:flex;align-items:center;justify-content:center;height:100vh;background:var(--bg-light)">
    <div class="card" style="max-width:480px;text-align:center;padding:3rem 2rem">
        <svg fill="none" stroke="var(--danger)" stroke-width="2" viewBox="0 0 24 24" style="width:64px;height:64px;margin:0 auto 1.5rem"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <h1 style="font-size:1.75rem;margin-bottom:1rem;color:var(--text-primary)">403 Forbidden</h1>
        <p style="color:var(--text-secondary);margin-bottom:2rem">You don't have permission to access this page. This area is restricted to specific roles.</p>
        
        <?php if ($user): ?>
            <a href="/ass/public/dashboard.php" class="btn btn-primary">Return to Dashboard</a>
        <?php else: ?>
            <a href="/ass/public/index.php" class="btn btn-primary">Go to Login</a>
        <?php endif; ?>
    </div>
</body>
</html>
