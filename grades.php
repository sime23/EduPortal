<?php
/**
 * grades.php – Grades & Feedback View
 * ─────────────────────────────────────
 * Students: View their own grades and teacher feedback.
 * Teachers: Grade submissions; add/edit grade + feedback.
 */
session_start();
require_once 'includes/db.php';
require_once 'includes/check_auth.php';

$role   = $user['role'];
$userId = $user['id'];
$isAdmin = ($role === 'admin');
$error  = '';
$success = '';

// ── Teacher POST: Save grade & feedback ─────────────────────
if (($role === 'teacher' || $isAdmin) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action       = $_POST['action'] ?? 'grade';
    $submissionId = (int)($_POST['submission_id'] ?? 0);

    // ── DELETE a graded submission ────────────────────────────
    if ($action === 'delete' && $submissionId > 0) {
        // Verify the submission is graded and belongs to one of this teacher's assignments
        $check = $pdo->prepare('
            SELECT s.id, s.file_path, s.correction_path
            FROM   submissions s
            JOIN   assignments a ON a.id = s.assignment_id
            WHERE  s.id = :id
              AND  s.grade IS NOT NULL AND s.grade != ""
              ' . ($isAdmin ? '' : 'AND a.created_by = :uid') . '
            LIMIT 1
        ');
        $checkParams = [':id' => $submissionId];
        if (!$isAdmin) $checkParams[':uid'] = $userId;
        $check->execute($checkParams);
        $target = $check->fetch();

        if ($target) {
            // Remove physical files from disk
            $subFile  = __DIR__ . '/uploads/submissions/' . $target['file_path'];
            $corrFile = $target['correction_path']
                        ? __DIR__ . '/uploads/corrections/' . $target['correction_path']
                        : null;
            if (file_exists($subFile))  @unlink($subFile);
            if ($corrFile && file_exists($corrFile)) @unlink($corrFile);

            // Soft-delete: clear the file paths but KEEP the grade & feedback
            // so students can still see their result in the Grades page.
            $pdo->prepare('
                UPDATE submissions
                SET file_path = NULL, correction_path = NULL
                WHERE id = :id
            ')->execute([':id' => $submissionId]);
            $success = 'Submission files removed. The student\'s grade and feedback are still visible to them.';
        } else {
            $error = 'Cannot delete: submission not found, not graded, or not yours.';
        }

    // ── DELETE only the correction file ────────────────────────
    } elseif ($action === 'delete_correction' && $submissionId > 0) {
        $check = $pdo->prepare('
            SELECT s.id, s.correction_path
            FROM   submissions s
            JOIN   assignments a ON a.id = s.assignment_id
            WHERE  s.id = :id
              AND  s.correction_path IS NOT NULL
              ' . ($isAdmin ? '' : 'AND a.created_by = :uid') . '
            LIMIT 1
        ');
        $checkParams = [':id' => $submissionId];
        if (!$isAdmin) $checkParams[':uid'] = $userId;
        $check->execute($checkParams);
        $target = $check->fetch();

        if ($target) {
            $corrFile = __DIR__ . '/uploads/corrections/' . $target['correction_path'];
            if (file_exists($corrFile)) @unlink($corrFile);
            $pdo->prepare('UPDATE submissions SET correction_path = NULL WHERE id = :id')
                ->execute([':id' => $submissionId]);
            $success = 'Correction file removed successfully.';
        } else {
            $error = 'Correction not found or not yours.';
        }

    // ── GRADE / update a submission ───────────────────────────
    } else {
        $grade    = trim($_POST['grade']    ?? '');
        $feedback = trim($_POST['feedback'] ?? '');

        if ($submissionId > 0 && $grade !== '') {
            $correctionPath = null;

            // Handle correction file upload
            if (isset($_FILES['correction_file']) && $_FILES['correction_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['correction_file'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['pdf', 'docx', 'doc', 'zip'])) {
                    $newName = 'corr_' . $submissionId . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $targetDir = __DIR__ . '/uploads/corrections/';
                    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
                    if (move_uploaded_file($file['tmp_name'], $targetDir . $newName)) {
                        $correctionPath = $newName;
                    }
                }
            }

            // Update grade, feedback, and correction_path for this submission
            $sql = 'UPDATE submissions SET grade = :grade, feedback = :feedback';
            $params = [':grade' => $grade, ':feedback' => $feedback, ':id' => $submissionId];

            if ($correctionPath) {
                $sql .= ', correction_path = :corr';
                $params[':corr'] = $correctionPath;
            }
            $sql .= ' WHERE id = :id';

            $upd = $pdo->prepare($sql);
            $upd->execute($params);
            $success = 'Grade and correction saved successfully!';
        } else {
            $error = 'Please select a submission and enter a grade.';
        }
    }
}

// ── Fetch records depending on role ─────────────────────────
if ($role === 'student') {
    // Student: see only their own grades
    $stmt = $pdo->prepare('
        SELECT  a.title, s.grade, s.feedback, s.submitted_at, a.deadline, s.correction_path
        FROM    submissions s
        JOIN    assignments a ON a.id = s.assignment_id
        WHERE   s.user_id = :uid
        ORDER BY s.submitted_at DESC
    ');
    $stmt->execute([':uid' => $userId]);
    $records = $stmt->fetchAll();
    // For student view, results is used below
    $results = $records;

} else { // Teacher or Admin
    // Teacher: Group submissions by assignment
    $stmt = $pdo->prepare('
        SELECT  a.id AS assignment_id, a.title AS assignment_title, c.class_name,
                COUNT(s.id) AS sub_count,
                SUM(CASE WHEN s.grade IS NULL OR s.grade = "" THEN 1 ELSE 0 END) AS pending_count
        FROM    assignments a
        LEFT JOIN classes c ON a.class_id = c.id
        LEFT JOIN submissions s ON s.assignment_id = a.id
        ' . ($isAdmin ? '' : 'WHERE a.created_by = :uid') . '
        GROUP BY a.id
        ORDER BY a.deadline DESC
    ');
    
    if ($isAdmin) {
        $stmt->execute();
    } else {
        $stmt->execute([':uid' => $userId]);
    }
    $assignments = $stmt->fetchAll();

    // Fetch all submissions for the teacher (will be grouped in PHP)
    $sStmt = $pdo->prepare('
        SELECT  s.*, u.full_name AS student_name, u.email AS student_email
        FROM    submissions s
        JOIN    users u ON u.id = s.user_id
        JOIN    assignments a ON a.id = s.assignment_id
        ' . ($isAdmin ? '' : 'WHERE a.created_by = :uid') . '
        ORDER BY s.submitted_at DESC
    ');
    if ($isAdmin) {
        $sStmt->execute();
    } else {
        $sStmt->execute([':uid' => $userId]);
    }
    $allSubmissions = $sStmt->fetchAll();
    
    // Group submissions by assignment_id
    $groupedSubmissions = [];
    foreach ($allSubmissions as $sub) {
        $groupedSubmissions[$sub['assignment_id']][] = $sub;
    }
    // Set records to assignments for the empty state check
    $records = $assignments;
}

/**
 * gradeClass() – Returns a CSS class based on letter grade.
 */
function gradeClass(?string $grade): string {
    if (!$grade) return 'grade-pending';
    $g = strtoupper(trim($grade));
    if (str_starts_with($g, 'A')) return 'grade-a';
    if (str_starts_with($g, 'B')) return 'grade-b';
    if (str_starts_with($g, 'C')) return 'grade-c';
    return 'grade-d';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $role === 'student' ? 'My Grades' : 'Grade Work' ?> – EduPortal</title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .grade-modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:2000; align-items:center; justify-content:center; }
        .grade-modal-backdrop.open { display:flex; }
        .grade-modal { background:white; border-radius:var(--radius-lg); padding:1.75rem; max-width:480px; width:90%; box-shadow:var(--shadow-lg); animation:fadeSlideUp .2s ease both; }
        .grade-modal h3 { font-size:1.05rem; margin-bottom:1.25rem; }
        .modal-close { float:right; background:none; border:none; cursor:pointer; font-size:1.25rem; color:var(--text-muted); }
        .assignment-group summary::-webkit-details-marker { display: none; }
        .assignment-group summary::marker { display: none; }
        .assignment-group[open] summary svg { transform: rotate(180deg); }
        .assignment-group summary svg { transition: transform .2s ease; }

        /* Graded Highlighting */
        .row-graded { background-color: rgba(34, 197, 94, 0.05) !important; transition: background 0.3s ease; }
        .row-graded:hover { background-color: rgba(34, 197, 94, 0.08) !important; }
        .badge-success-lite { background: #dcfce7; color: #166534; font-weight: 600; border: 1px solid #bbf7d0; }
        .badge-pending-lite { background: #f1f5f9; color: #64748b; font-weight: 500; border: 1px solid #e2e8f0; }

        /* Delete confirmation modal */
        .delete-modal { max-width: 420px; }
        .delete-modal .modal-icon { width:52px; height:52px; border-radius:50%; background:#fef2f2; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem; font-size:1.5rem; }
        .delete-modal p { text-align:center; color:var(--text-muted); font-size:.9rem; line-height:1.5; margin-bottom:1.5rem; }
        .delete-modal h3 { text-align:center; }
        .btn-danger { background: #dc2626; color: #fff; border: none; }
        .btn-danger:hover { background: #b91c1c; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require_once 'includes/header.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1><?= $role === 'student' ? 'My Grades' : 'Grade Student Work' ?></h1>
            <p><?= $role === 'student' ? 'Track your performance and read teacher feedback' : 'Review submissions and assign grades' ?></p>
        </div>

        <?php if ($error):   ?><div class="alert alert-error">  <?= htmlspecialchars($error)   ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <?php if ($role === 'student'): ?>
            <!-- ── Student view: List of their grades ── -->
            <?php if (empty($results)): ?>
                <div class="card p-4 text-center">
                    <p class="text-muted">You haven't submitted any work yet. Check your <a href="dashboard.php">Dashboard</a> for active assignments.</p>
                </div>
            <?php else: ?>
                <div class="card p-0" style="overflow:hidden">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Assignment</th>
                                <th>Submitted</th>
                                <th>Grade</th>
                                <th>Feedback / Correction</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $r): ?>
                                <tr>
                                    <td class="fw-600"><?= htmlspecialchars($r['title']) ?></td>
                                    <td class="text-muted" style="font-size:.825rem"><?= date('M j, Y', strtotime($r['submitted_at'])) ?></td>
                                    <td>
                                        <?php if ($r['grade']): ?>
                                            <span class="badge badge-success-lite" style="font-size:0.9rem; padding: 0.35rem 0.75rem;"><?= htmlspecialchars($r['grade']) ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-pending-lite">Awaiting Grade</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:.85rem; max-width:300px">
                                        <?php if ($r['feedback']): ?>
                                            <div style="line-height:1.5; background:rgba(0,0,0,0.02); padding:0.75rem; border-radius:8px; border-left:3px solid var(--primary); margin-bottom:0.5rem;">
                                                <?= nl2br(htmlspecialchars($r['feedback'])) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($r['correction_path']): ?>
                                            <div style="margin-top: 0.5rem">
                                                <a href="/download?file=<?= urlencode($r['correction_path']) ?>&type=correction" class="btn btn-secondary btn-xs" style="background:var(--success-bg); color:var(--success); border-color:var(--success);">
                                                    📥 Download Teacher's Correction
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!$r['feedback'] && !$r['correction_path']): ?>
                                            <span class="text-muted" style="font-style:italic">No feedback provided yet.</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- ── Teacher view: Grouped by Assignment ── -->
            <div style="display:flex;flex-direction:column;gap:1rem">
                <?php if (empty($assignments)): ?>
                    <div class="card p-4 text-center">
                        <p class="text-muted">No assignments found. Start by creating one in <a href="manage.php">Manage Assignments</a>.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($assignments as $a): ?>
                        <div class="card p-0" style="overflow:hidden">
                            <details class="assignment-group">
                                <summary style="padding:1rem; cursor:pointer; list-style:none; display:flex; justify-content:space-between; align-items:center; background:rgba(0,0,0,0.02)">
                                    <div style="display:flex; align-items:center; gap:.75rem">
                                        <div style="background:var(--primary); color:white; width:34px; height:34px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:.9rem">
                                            <?= $a['sub_count'] ?>
                                        </div>
                                        <div>
                                            <div style="font-weight:600; font-size:.95rem"><?= htmlspecialchars($a['assignment_title']) ?></div>
                                            <div style="font-size:.75rem; color:var(--text-muted)">
                                                Class: <?= htmlspecialchars($a['class_name'] ?? 'General') ?> 
                                                <?php if ($a['pending_count'] > 0): ?>
                                                    · <span style="color:var(--danger); font-weight:600">⚠ <?= $a['pending_count'] ?> pending</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:.5rem; font-size:.75rem; color:var(--primary); font-weight:600">
                                        View Submissions
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px; height:14px"><path d="M19 9l-7 7-7-7"/></svg>
                                    </div>
                                </summary>

                                <div style="padding:1.25rem; border-top: 1px solid var(--border-light)">
                                    <?php if (empty($groupedSubmissions[$a['assignment_id']])): ?>
                                        <p class="text-muted text-center py-3" style="font-size:.85rem">No submissions yet for this assignment.</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table" style="font-size: .85rem">
                                                <thead>
                                                    <tr>
                                                        <th>Student</th>
                                                        <th>Submitted</th>
                                                        <th>Work</th>
                                                        <th>Grade</th>
                                                        <th>Feedback</th>
                                                        <th class="text-right">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($groupedSubmissions[$a['assignment_id']] as $sub): 
                                                        $isGraded = !empty($sub['grade']);
                                                        $rowClass = $isGraded ? 'row-graded' : '';
                                                    ?>
                                                        <tr class="<?= $rowClass ?>">
                                                            <td>
                                                                <div class="fw-600"><?= htmlspecialchars($sub['student_name']) ?></div>
                                                                <div style="font-size:.7rem;color:var(--text-muted)">Attempt: <?= (int)$sub['attempts'] ?>/3</div>
                                                            </td>
                                                            <td class="text-muted"><?= date('M j, Y', strtotime($sub['submitted_at'])) ?></td>
                                                            <td>
                                                                <a href="/download?file=<?= urlencode($sub['file_path']) ?>&type=submission" class="btn btn-secondary btn-xs" target="_blank">Download</a>
                                                            </td>
                                                            <td>
                                                                <?php if ($isGraded): ?>
                                                                    <span class="badge badge-success-lite"><?= htmlspecialchars($sub['grade']) ?></span>
                                                                <?php else: ?>
                                                                    <span class="badge badge-pending-lite">Ungraded</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td style="max-width:220px">
                                                                <?php if ($sub['feedback']): ?>
                                                                    <div style="font-size:.75rem; line-height:1.2; overflow:hidden; display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">
                                                                        <?= htmlspecialchars($sub['feedback']) ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if ($sub['correction_path']): ?>
                                                                    <div style="margin-top:4px;display:flex;align-items:center;gap:.35rem;flex-wrap:wrap">
                                                                        <span style="color:var(--success);font-size:.65rem;font-weight:600">✓ Correction Uploaded</span>
                                                                        <button type="button"
                                                                                class="btn btn-xs"
                                                                                style="font-size:.6rem;padding:1px 6px;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;line-height:1.4"
                                                                                onclick="openDeleteCorrModal(<?= (int)$sub['id'] ?>, '<?= addslashes($sub['student_name']) ?>')">✕ Remove</button>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-right" style="white-space:nowrap">
                                                                <button class="btn btn-primary btn-xs"
                                                                        onclick="openModal(<?= (int)$sub['id'] ?>, '<?= addslashes($sub['student_name']) ?>', '<?= addslashes($a['assignment_title']) ?>', '<?= addslashes($sub['grade'] ?? '') ?>', '<?= addslashes($sub['feedback'] ?? '') ?>')">
                                                                    <?= $isGraded ? 'Edit' : 'Grade' ?>
                                                                </button>
                                                                <?php if ($isGraded): ?>
                                                                <button class="btn btn-danger btn-xs" style="margin-left:.35rem"
                                                                        onclick="openDeleteModal(<?= (int)$sub['id'] ?>, '<?= addslashes($sub['student_name']) ?>')">
                                                                    Delete
                                                                </button>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </details>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Grade Modal -->
            <div class="grade-modal-backdrop" id="gradeModal">
                <div class="grade-modal">
                    <button class="modal-close" onclick="closeModal()" aria-label="Close">✕</button>
                    <h3 id="modalTitle">Grade Submission</h3>
                    <form method="POST" action="/grades" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="grade">
                        <input type="hidden" name="submission_id" id="modalSubId">
                        <div class="form-group">
                            <label class="form-label">Grade (e.g. A+, B, 85%)</label>
                            <input type="text" name="grade" id="modalGrade" class="form-control" maxlength="10" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Feedback (optional)</label>
                            <textarea name="feedback" id="modalFeedback" class="form-control" placeholder="Write constructive feedback…"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Upload Corrected File (Optional)</label>
                            <input type="file" name="correction_file" class="form-control" style="font-size: .85rem">
                        </div>
                        <div style="display:flex;gap:.75rem;justify-content:flex-end">
                            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Grade</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Delete Submission Modal -->
            <div class="grade-modal-backdrop" id="deleteModal">
                <div class="grade-modal delete-modal">
                    <div class="modal-icon">🗑️</div>
                    <h3>Delete Submission?</h3>
                    <p id="deleteModalMsg">This will permanently remove the student's submitted file and all associated data. This action cannot be undone.</p>
                    <form method="POST" action="/grades" id="deleteForm">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="submission_id" id="deleteSubId">
                        <div style="display:flex;gap:.75rem;justify-content:center">
                            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                            <button type="submit" class="btn btn-danger">Yes, Delete</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Delete Correction Modal -->
            <div class="grade-modal-backdrop" id="deleteCorrModal">
                <div class="grade-modal delete-modal">
                    <div class="modal-icon">📄</div>
                    <h3>Remove Correction File?</h3>
                    <p id="deleteCorrModalMsg">This will permanently delete the uploaded correction file for this student. The grade and feedback will stay. This action cannot be undone.</p>
                    <form method="POST" action="/grades">
                        <input type="hidden" name="action" value="delete_correction">
                        <input type="hidden" name="submission_id" id="deleteCorrSubId">
                        <div style="display:flex;gap:.75rem;justify-content:center">
                            <button type="button" class="btn btn-secondary" onclick="closeDeleteCorrModal()">Cancel</button>
                            <button type="submit" class="btn btn-danger">Yes, Remove</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
function openModal(id, student, assignment, grade, feedback) {
    document.getElementById('modalSubId').value    = id;
    document.getElementById('modalGrade').value    = grade;
    document.getElementById('modalFeedback').value = feedback;
    document.getElementById('modalTitle').textContent = `Grade: ${student} — ${assignment}`;
    document.getElementById('gradeModal').classList.add('open');
}

function closeModal() {
    document.getElementById('gradeModal').classList.remove('open');
}

document.getElementById('gradeModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// ── Delete modal ──────────────────────────────────────────────
function openDeleteModal(id, student) {
    document.getElementById('deleteSubId').value = id;
    document.getElementById('deleteModalMsg').textContent =
        `Delete ${student}'s submission? This will permanently remove the submitted file and all grading data. This action cannot be undone.`;
    document.getElementById('deleteModal').classList.add('open');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('open');
}

document.getElementById('deleteModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

// ── Delete correction modal ───────────────────────────────────
function openDeleteCorrModal(id, student) {
    document.getElementById('deleteCorrSubId').value = id;
    document.getElementById('deleteCorrModalMsg').textContent =
        `Remove the correction file for ${student}? The grade and feedback will be kept. This action cannot be undone.`;
    document.getElementById('deleteCorrModal').classList.add('open');
}

function closeDeleteCorrModal() {
    document.getElementById('deleteCorrModal').classList.remove('open');
}

document.getElementById('deleteCorrModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeDeleteCorrModal();
});
</script>
</body>
</html>
