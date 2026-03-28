<?php
/**
 * dashboard.php – Main Assignment List Dashboard
 * ───────────────────────────────────────────────
 * Students: see all assignments with Pending/Submitted status.
 * Teachers: see all assignments with submission counts.
 */
session_start();
require_once '../includes/db.php';
require_once '../includes/check_auth.php';

$role   = $user['role'];
$userId = $user['id'];

// ── Fetch assignments with status for students ───────────────
if ($role === 'student') {
    // LEFT JOIN to detect whether this student has submitted
    $stmt = $pdo->prepare('
        SELECT a.id, a.title, a.description, a.deadline, a.attachment, a.created_by,
               c.class_name,
               s.id          AS submission_id,
               s.grade       AS grade,
               s.submitted_at AS submitted_at,
               s.correction_path AS correction_path,
               s.attempts   AS submission_attempts,
               a.master_correction AS master_correction
        FROM   assignments a
        JOIN   class_students cs ON a.class_id = cs.class_id AND cs.student_id = :uid1
        LEFT JOIN classes c    ON a.class_id = c.id
        LEFT JOIN submissions s ON s.assignment_id = a.id AND s.user_id = :uid2
        ORDER BY a.deadline ASC
    ');
    $stmt->execute([':uid1' => $userId, ':uid2' => $userId]);

} else {
    // Teachers/Admin: fetch all assignments + count of submissions
    $isAdmin = ($role === 'admin');
    $sql = '
        SELECT a.id, a.title, a.description, a.deadline, a.attachment, a.created_by,
               c.class_name,
               COUNT(s.id) AS submission_count
        FROM   assignments a
        LEFT JOIN classes c ON a.class_id = c.id
        LEFT JOIN submissions s ON s.assignment_id = a.id
        ' . ($isAdmin ? '' : 'WHERE a.created_by = :uid') . '
        GROUP BY a.id
        ORDER BY a.deadline ASC
    ';
    $stmt = $pdo->prepare($sql);
    if ($isAdmin) {
        $stmt->execute();
    } else {
        $stmt->execute([':uid' => $userId]);
    }
}

$assignments = $stmt->fetchAll();

// ── Stats ────────────────────────────────────────────────────
$totalAssignments = count($assignments);

if ($role === 'student') {
    $submitted = array_filter($assignments, fn($a) => $a['submission_id']);
    $pending   = $totalAssignments - count($submitted);
    $graded    = array_filter($submitted, fn($a) => !empty($a['grade']));
} else {
    // Teacher stats
    $totalSubmissions = array_sum(array_column($assignments, 'submission_count'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – EduPortal</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app-shell">
    <?php require_once '../includes/header.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Dashboard</h1>
            <p>Welcome back, <?= htmlspecialchars($user['full_name']) ?>!</p>
        </div>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon stat-icon-cyan">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <div>
                    <div class="stat-value"><?= $totalAssignments ?></div>
                    <div class="stat-label">Total Assignments</div>
                </div>
            </div>

            <?php if ($role === 'student'): ?>
            <div class="stat-card">
                <div class="stat-icon stat-icon-green">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 12 2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg>
                </div>
                <div>
                    <div class="stat-value"><?= count($submitted) ?></div>
                    <div class="stat-label">Submitted</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-blue">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div>
                    <div class="stat-value"><?= $pending ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-orange">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                </div>
                <div>
                    <div class="stat-value"><?= count($graded) ?></div>
                    <div class="stat-label">Graded</div>
                </div>
            </div>

            <?php else: ?>
            <div class="stat-card">
                <div class="stat-icon stat-icon-green">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <div>
                    <div class="stat-value"><?= $totalSubmissions ?></div>
                    <div class="stat-label">Total Submissions</div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Assignments Grid -->
        <div class="card-header" style="margin-bottom:1rem;">
            <span class="card-title">All Assignments</span>
            <?php if ($role === 'teacher'): ?>
                <a href="manage.php" class="btn btn-primary btn-sm">+ New Assignment</a>
            <?php endif; ?>
        </div>

        <?php if (empty($assignments)): ?>
        <div class="card">
            <div class="empty-state">
                <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <h3>No assignments yet</h3>
                <p>Check back later for new assignments from your teacher.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="assignments-grid">
            <?php foreach ($assignments as $i => $a):
                $deadline    = new DateTime($a['deadline']);
                $now         = new DateTime();
                $diff        = $now->diff($deadline);
                $isPast      = $deadline < $now;
                $isUrgent    = !$isPast && $diff->days == 0 && $diff->h < 24;
                $deadlineStr = $deadline->format('M j, Y · g:i A');
                $style       = "animation-delay:" . ($i * 0.06) . "s";
            ?>
            <div class="assignment-card" style="<?= $style ?>">
                <div class="assignment-card-header">
                    <h3 class="assignment-title"><?= htmlspecialchars($a['title']) ?></h3>
                    <?php if ($role === 'student'): ?>
                        <?php if ($a['submission_id']): ?>
                            <div style="display:flex; flex-direction:column; align-items:flex-end; gap:2px">
                                <span class="badge badge-submitted">✓ Submitted</span>
                                <?php if (isset($a['submission_attempts'])): ?>
                                    <span style="font-size:0.65rem; color:var(--text-muted)">Attempt <?= (int)$a['submission_attempts'] ?>/3</span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <span class="badge badge-pending">Pending</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge badge-teacher"><?= (int)$a['submission_count'] ?> subs</span>
                    <?php endif; ?>
                </div>

                <p class="assignment-desc"><?= htmlspecialchars($a['description']) ?></p>

                <?php if (!empty($a['attachment'])): ?>
                    <div style="margin-bottom: 0.75rem;">
                        <a href="download.php?file=<?= urlencode($a['attachment']) ?>&type=assignment" class="btn btn-sm" style="background: rgba(37,99,235,0.1); color: var(--primary); padding: 0.25rem 0.6rem; text-decoration: none;" target="_blank">
                            📄 Download Attachment
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($role === 'student' && !empty($a['correction_path'])): ?>
                    <div style="margin-bottom: 0.75rem;">
                        <a href="download.php?file=<?= urlencode($a['correction_path']) ?>&type=correction" class="btn btn-sm" style="background: var(--success-bg); color: var(--success); border: 1px solid var(--success); padding: 0.25rem 0.6rem; text-decoration: none;" target="_blank">
                            📥 Download My Correction
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($isPast && !empty($a['master_correction'])): ?>
                    <div style="margin-bottom: 0.75rem;">
                        <a href="download.php?file=<?= urlencode($a['master_correction']) ?>&type=master_correction" class="btn btn-sm" style="background: var(--cyan); color: white; padding: 0.25rem 0.6rem; text-decoration: none;" target="_blank">
                            🌟 Master Correction (Available Now)
                        </a>
                    </div>
                <?php endif; ?>

                <div class="assignment-meta">
                    <span style="background: rgba(37,99,235,0.1); padding: 0.1rem 0.4rem; border-radius: 4px; color: var(--primary); font-size: 0.75rem; font-weight: 500; margin-right: 0.5rem;">📚 <?= htmlspecialchars($a['class_name'] ?? 'Unassigned') ?></span>
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span <?= $isUrgent ? 'class="text-danger fw-600"' : ($isPast ? 'class="text-muted"' : '') ?>>
                        <?= $isPast ? '⚠ Deadline passed · ' : 'Due: ' ?>
                        <?= htmlspecialchars($deadlineStr) ?>
                    </span>
                </div>

                <div class="assignment-footer">
                    <?php if ($role === 'student'): ?>
                        <?php if (!$a['submission_id'] && !$isPast): ?>
                            <a href="submit.php?id=<?= (int)$a['id'] ?>" class="btn btn-primary btn-sm">Submit Now</a>
                        <?php elseif ($a['submission_id']): ?>
                            <div style="display:flex; gap:0.5rem">
                                <a href="grades.php" class="btn btn-secondary btn-sm">View Grade</a>
                                <?php if (!$isPast && (isset($a['submission_attempts']) && $a['submission_attempts'] < 3)): ?>
                                    <a href="submit.php?id=<?= (int)$a['id'] ?>" class="btn btn-primary btn-sm">Re-submit</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="manage.php?edit=<?= (int)$a['id'] ?>" class="btn btn-secondary btn-sm">Manage</a>
                    <?php endif; ?>
                    <?php if ($isUrgent): ?>
                        <span class="badge badge-urgent">🔴 Due Soon</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
