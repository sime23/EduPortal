<?php
/**
 * deadlines.php – Deadline View with Calendar Highlights
 * ────────────────────────────────────────────────────────
 * Shows all assignment deadlines in chronological order.
 * Deadlines within 24 hours are highlighted in red.
 * Includes a simple calendar widget for the current month.
 */
session_start();
require_once 'includes/db.php';
require_once 'includes/check_auth.php';

$userId = $user['id'];
$role   = $user['role'];

// Fetch all assignments with submission status for students
if ($role === 'student') {
    $stmt = $pdo->prepare('
        SELECT a.*, s.id AS submitted, c.class_name
        FROM   assignments a
        JOIN   class_students cs ON a.class_id = cs.class_id AND cs.student_id = :uid1
        LEFT JOIN classes c ON a.class_id = c.id
        LEFT JOIN submissions s ON s.assignment_id = a.id AND s.user_id = :uid2
        ORDER BY a.deadline ASC
    ');
    $stmt->execute([':uid1' => $userId, ':uid2' => $userId]);
} else {
    $isAdmin = ($role === 'admin');
    $sql = '
        SELECT a.*, NULL AS submitted, c.class_name 
        FROM assignments a
        LEFT JOIN classes c ON a.class_id = c.id
        ' . ($isAdmin ? '' : 'WHERE a.created_by = :uid') . '
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

// Build a set of days (in current month) that have deadlines for the mini calendar
$now          = new DateTime();
$currentMonth = (int)$now->format('m');
$currentYear  = (int)$now->format('Y');
$daysWithDeadlines = [];

foreach ($assignments as $a) {
    $dl = new DateTime($a['deadline']);
    if ((int)$dl->format('m') === $currentMonth && (int)$dl->format('Y') === $currentYear) {
        $daysWithDeadlines[(int)$dl->format('j')] = true;
    }
}

// Calendar helpers
$firstDay   = new DateTime("{$currentYear}-{$currentMonth}-01");
$daysInMonth = (int)$firstDay->format('t');
$startWeekday = (int)$firstDay->format('N'); // 1=Mon … 7=Sun
$monthName = $firstDay->format('F Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deadlines – EduPortal</title>
    <link rel="stylesheet" href="/ass/assets/style.css">
    <style>
        /* Mini calendar */
        .mini-calendar { background:var(--white); border-radius:var(--radius-lg); padding:1.25rem; box-shadow:var(--shadow-sm); border:1px solid var(--border-light); }
        .cal-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; }
        .cal-title { font-weight:700; font-size:.975rem; color:var(--text-primary); }
        .cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:4px; }
        .cal-day-label { text-align:center; font-size:.7rem; font-weight:600; color:var(--text-muted); padding:.25rem 0; text-transform:uppercase; }
        .cal-day { aspect-ratio:1; display:flex; align-items:center; justify-content:center; border-radius:50%; font-size:.8rem; cursor:default; transition:all .15s; }
        .cal-day.today { background:var(--cyan); color:white; font-weight:700; }
        .cal-day.has-deadline { background:var(--danger-bg); color:var(--danger); font-weight:600; }
        .cal-day.has-deadline.today { background:var(--danger); color:white; }
        .cal-day.other { color:var(--border); }
        .two-col { display:grid; grid-template-columns:300px 1fr; gap:1.5rem; align-items:start; }
        @media(max-width:800px){ .two-col { grid-template-columns:1fr; } }
        .legend { display:flex; gap:1rem; flex-wrap:wrap; margin-top:.75rem; }
        .legend-item { display:flex; align-items:center; gap:.35rem; font-size:.775rem; color:var(--text-secondary); }
        .legend-dot { width:10px; height:10px; border-radius:50%; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require_once 'includes/header.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Deadlines</h1>
            <p>Stay on top of all upcoming assignment deadlines</p>
        </div>

        <div class="two-col">
            <!-- Mini Calendar -->
            <div>
                <div class="mini-calendar">
                    <div class="cal-header">
                        <span class="cal-title"><?= $monthName ?></span>
                    </div>
                    <div class="cal-grid">
                        <?php foreach (['M','T','W','T','F','S','S'] as $d): ?>
                            <div class="cal-day-label"><?= $d ?></div>
                        <?php endforeach; ?>

                        <?php
                        // Empty cells before month starts (Mon=1, so offset = startWeekday-1)
                        for ($i = 1; $i < $startWeekday; $i++):
                        ?>
                            <div class="cal-day other"></div>
                        <?php endfor; ?>

                        <?php for ($day = 1; $day <= $daysInMonth; $day++):
                            $classes = ['cal-day'];
                            if ($day === (int)$now->format('j')) $classes[] = 'today';
                            if (isset($daysWithDeadlines[$day]))  $classes[] = 'has-deadline';
                        ?>
                            <div class="<?= implode(' ', $classes) ?>"><?= $day ?></div>
                        <?php endfor; ?>
                    </div>
                    <div class="legend mt-2">
                        <div class="legend-item"><div class="legend-dot" style="background:var(--cyan)"></div> Today</div>
                        <div class="legend-item"><div class="legend-dot" style="background:var(--danger)"></div> Deadline</div>
                    </div>
                </div>
            </div>

            <!-- Chronological Deadline List -->
            <div>
                <?php if (empty($assignments)): ?>
                    <div class="card">
                        <div class="empty-state">
                            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <h3>No deadlines found</h3>
                            <p>No assignments have been scheduled yet.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="deadline-list">
                        <?php foreach ($assignments as $i => $a):
                            $dl      = new DateTime($a['deadline']);
                            $nowDt   = new DateTime();
                            $diff    = $nowDt->diff($dl);
                            $isPast  = $dl < $nowDt;
                            $isUrgent = !$isPast && $diff->days == 0;
                            $style   = "animation-delay:" . ($i * 0.07) . "s";
                        ?>
                        <div class="deadline-item <?= $isUrgent ? 'urgent' : '' ?>" style="<?= $style ?>">
                            <div class="deadline-date-box <?= $isUrgent ? 'urgent-box' : '' ?>">
                                <div class="deadline-day"><?= $dl->format('j') ?></div>
                                <div class="deadline-mon"><?= $dl->format('M') ?></div>
                            </div>
                            <div class="deadline-info">
                                <div class="deadline-name">
                                    <?= htmlspecialchars($a['title']) ?>
                                    <span style="font-size: 0.75rem; color: var(--primary); font-weight: 500; margin-left: 0.5rem; background: rgba(37,99,235,0.1); padding: 0.1rem 0.4rem; border-radius: 4px;">📚 <?= htmlspecialchars($a['class_name'] ?? 'Unassigned') ?></span>
                                </div>
                                <div class="deadline-time">
                                    <?= $dl->format('g:i A') ?>
                                    <?php if ($isPast): ?>
                                        &nbsp;· <span class="text-muted">Deadline passed</span>
                                    <?php elseif ($isUrgent): ?>
                                        &nbsp;· <span class="text-danger fw-600">Due in <?= $diff->h ?>h <?= $diff->i ?>m</span>
                                    <?php else: ?>
                                        &nbsp;· <?= $diff->days ?> day<?= $diff->days != 1 ? 's' : '' ?> remaining
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.4rem;flex-shrink:0">
                                <?php if ($role === 'student' && $a['submitted']): ?>
                                    <span class="badge badge-submitted">✓ Done</span>
                                <?php elseif ($isUrgent): ?>
                                    <span class="deadline-urgency">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                        Urgent
                                    </span>
                                    <?php if ($role === 'student'): ?>
                                    <a href="submit.php?id=<?= (int)$a['id'] ?>" class="btn btn-danger btn-sm">Submit Now</a>
                                    <?php endif; ?>
                                <?php elseif ($role === 'student' && !$isPast): ?>
                                    <a href="submit.php?id=<?= (int)$a['id'] ?>" class="btn btn-secondary btn-sm">Submit</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>
