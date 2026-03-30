<?php
/**
 * manage.php – Assignment Management (Teachers only)
 * ─────────────────────────────────────────────────────
 * Create new assignments and edit/delete existing ones.
 * Teachers can also view all submissions per assignment here.
 */
session_start();
require_once 'includes/db.php';
require_once 'includes/check_auth.php';

enforceRole(['teacher', 'admin']);

$userId  = $user['id'];
$role    = $user['role'];
$isAdmin = ($role === 'admin');
$error   = '';
$success = '';
$editAssignment = null;

// ── Action: Delete Assignment ───────────────────────────────
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $delStmt = $pdo->prepare('DELETE FROM assignments WHERE id = :id' . ($isAdmin ? '' : ' AND created_by = :uid'));
    $params = [':id' => $delId];
    if (!$isAdmin) $params[':uid'] = $userId;
    
    $delStmt->execute($params);
    header('Location: /manage?success=Assignment+deleted');
    exit;
}

// ── Action: Fetch Edit Mode ─────────────────────────────────
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
if ($editId > 0) {
    $eStmt = $pdo->prepare('SELECT * FROM assignments WHERE id = :id');
    $eStmt->execute([':id' => $editId]);
    $editAssignment = $eStmt->fetch();
}

// ── Action: Create / Edit Assignment (POST) ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action      = $_POST['action'];
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $deadline    = $_POST['deadline'] ?? '';
    $classId     = (int)($_POST['class_id'] ?? 0);
    $assignId    = (int)($_POST['assign_id'] ?? 0);

    // Max sizes
    $maxAssignSize = 2 * 1024 * 1024;    // 2MB for assignment handouts
    $maxMasterSize = 10 * 1024 * 1024;  // 10MB for master corrections

    // Validate fields
    if ($classId <= 0) {
        $error = 'You must select a class for this assignment.';
    } elseif (empty($title)) {
        $error = 'Title is required.';
    } elseif (empty($description)) {
        $error = 'Description is required.';
    } elseif (empty($deadline) || !strtotime($deadline)) {
        $error = 'Please enter a valid deadline date and time.';
    } elseif (strtotime($deadline) <= time()) {
        $error = 'Deadline must be in the future.';
    } else {
        // Handle files
        $attachmentName = null;
        $masterName     = null;
        $uploadDir      = __DIR__ . '/uploads/assignments/';
        $masterDir      = __DIR__ . '/uploads/master_corrections/';

        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        if (!is_dir($masterDir)) mkdir($masterDir, 0777, true);

        // Attachment?
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['attachment']['size'] <= $maxAssignSize) {
                $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
                $attachmentName = 'assign_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $attachmentName);
            } else {
                $error = 'Attachment exceeds the 2MB size limit.';
            }
        }

        // Master Correction?
        if (!$error && isset($_FILES['master_correction_file']) && $_FILES['master_correction_file']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['master_correction_file']['size'] <= $maxMasterSize) {
                $ext = pathinfo($_FILES['master_correction_file']['name'], PATHINFO_EXTENSION);
                $masterName = 'master_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                move_uploaded_file($_FILES['master_correction_file']['tmp_name'], $masterDir . $masterName);
            } else {
                $error = 'Master Correction file exceeds the 10MB size limit.';
            }
        }

        if (!$error) {
            if ($action === 'create') {
                $stmt = $pdo->prepare('
                    INSERT INTO assignments (class_id, title, description, attachment, master_correction, deadline, created_by)
                    VALUES (:cid, :t, :d, :a, :m, :dl, :cb)
                ');
                $stmt->execute([
                    ':cid' => $classId,
                    ':t'   => $title,
                    ':d'   => $description,
                    ':a'   => $attachmentName,
                    ':m'   => $masterName,
                    ':dl'  => $deadline,
                    ':cb'  => $userId
                ]);
                $success = 'Assignment created successfully!';
            } else {
                // Update
                $sql = 'UPDATE assignments SET class_id = :cid, title = :t, description = :d, deadline = :dl';
                $params = [':cid' => $classId, ':t' => $title, ':d' => $description, ':dl' => $deadline, ':id' => $assignId];
                
                if ($attachmentName) {
                    $sql .= ', attachment = :a';
                    $params[':a'] = $attachmentName;
                }
                if ($masterName) {
                    $sql .= ', master_correction = :m';
                    $params[':m'] = $masterName;
                }
                $sql .= ' WHERE id = :id';
                if (!$isAdmin) {
                    $sql .= ' AND created_by = :uid';
                    $params[':uid'] = $userId;
                }

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $success = 'Assignment updated successfully!';
                $editAssignment = null; // Exit edit mode after success
            }
        }
    }
}

// ── Fetch current assignments for display ───────────────────
$sql = '
    SELECT a.*, c.class_name, COUNT(s.id) AS sub_count
    FROM assignments a
    LEFT JOIN classes c ON a.class_id = c.id
    LEFT JOIN submissions s ON s.assignment_id = a.id
';
if (!$isAdmin) {
    $sql .= ' WHERE a.created_by = :uid';
}
$sql .= ' GROUP BY a.id ORDER BY a.created_at DESC';

$stmt = $pdo->prepare($sql);
if (!$isAdmin) {
    $stmt->execute([':uid' => $userId]);
} else {
    $stmt->execute();
}
$assignments = $stmt->fetchAll();

// ── Fetch classes for teacher dropdown ──────────────────────
$cSql = 'SELECT id, class_name FROM classes ' . ($isAdmin ? '' : 'WHERE teacher_id = :uid ') . 'ORDER BY class_name ASC';
$cStmt = $pdo->prepare($cSql);
if ($isAdmin) {
    $cStmt->execute();
} else {
    $cStmt->execute([':uid' => $userId]);
}
$myClasses = $cStmt->fetchAll();

if (isset($_GET['success'])) $success = $_GET['success'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Assignments – EduPortal</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="app-shell">
    <?php require_once 'includes/header.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Manage Assignments</h1>
            <p>Create, edit, and delete assignments for your students</p>
        </div>

        <?php if ($error):   ?><div class="alert alert-error">  <?= htmlspecialchars($error)   ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <div style="display:grid;grid-template-columns:380px 1fr;gap:1.5rem;align-items:start">

            <!-- Create / Edit Form -->
            <div class="card">
                <div class="card-title mb-2">
                    <?= $editAssignment ? 'Edit Assignment' : 'New Assignment' ?>
                </div>

                <form method="POST" action="/manage" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?= $editAssignment ? 'edit' : 'create' ?>">
                    <?php if ($editAssignment): ?>
                        <input type="hidden" name="assign_id" value="<?= (int)$editAssignment['id'] ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-label" for="class_id">Assign to Class</label>
                        <?php if (empty($myClasses)): ?>
                            <p class="text-danger" style="font-size: 0.85rem;">You must create a class (in Manage Students) before you can create an assignment.</p>
                        <?php else: ?>
                            <select name="class_id" id="class_id" class="form-control" required>
                                <option value="" disabled <?= !$editAssignment ? 'selected' : '' ?>>-- Choose a Class --</option>
                                <?php foreach ($myClasses as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($editAssignment && $editAssignment['class_id'] == $c['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['class_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="title">Assignment Title</label>
                        <input type="text" name="title" id="title" class="form-control"
                               value="<?= htmlspecialchars($editAssignment['title'] ?? '') ?>"
                               placeholder="e.g. Research Paper – Climate Change" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="description">Description / Instructions</label>
                        <textarea name="description" id="description" class="form-control" rows="4"
                                  placeholder="Provide clear instructions for students…" required><?= htmlspecialchars($editAssignment['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="attachment">Attachment File (Optional, Max 2MB)</label>
                        <input type="file" name="attachment" id="attachment" class="form-control" style="font-size: 0.85rem;" onchange="if(this.files[0].size > 2 * 1024 * 1024) { alert('File is too large! Maximum size is 2MB.'); this.value = ''; }">
                        <?php if ($editAssignment && !empty($editAssignment['attachment'])): ?>
                            <span class="form-hint">Current: <a href="/download?file=<?= urlencode($editAssignment['attachment']) ?>&type=assignment" target="_blank">Download</a>. Uploading a new file will overwrite it.</span>
                        <?php else: ?>
                            <span class="form-hint">Provide any PDFs, documents, or zips needed for the assignment.</span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="master_correction_file">Master Correction File (Optional)</label>
                        <input type="file" name="master_correction_file" id="master_correction_file" class="form-control" style="font-size: 0.85rem;" onchange="if(this.files[0].size > 10 * 1024 * 1024) { alert('File is too large! Maximum size is 10MB.'); this.value = ''; }">
                        <?php if ($editAssignment && !empty($editAssignment['master_correction'])): ?>
                            <span class="form-hint">Current: <a href="/download?file=<?= urlencode($editAssignment['master_correction']) ?>&type=master_correction" target="_blank">Download Master Correction</a>. Uploading a new file will overwrite it.</span>
                        <?php else: ?>
                            <span class="form-hint">Visible to all students ONLY after the deadline has passed.</span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="deadline">Deadline</label>
                        <input type="datetime-local" name="deadline" id="deadline" class="form-control"
                               value="<?= $editAssignment ? date('Y-m-d\TH:i', strtotime($editAssignment['deadline'])) : '' ?>"
                               required>
                        <span class="form-hint">Must be a future date and time</span>
                    </div>

                    <div style="display:flex;gap:.75rem">
                        <?php if ($editAssignment): ?>
                            <a href="/manage" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary" style="flex:1">
                            <?= $editAssignment ? 'Update Assignment' : '+ Create Assignment' ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Assignments List -->
            <div>
                <?php if (empty($assignments)): ?>
                    <div class="card">
                        <div class="empty-state">
                            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
                            <h3>No assignments yet</h3>
                            <p>Create your first assignment using the form on the left.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:.75rem">
                    <?php foreach ($assignments as $i => $a):
                        $dl = new DateTime($a['deadline']);
                        $isPast = $dl < new DateTime();
                        $style = "animation-delay:" . ($i * 0.06) . "s";
                    ?>
                    <div class="card" style="<?= $style ?>;padding:1rem">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem">
                            <div style="flex:1">
                                <div style="font-weight:600;margin-bottom:.2rem"><?= htmlspecialchars($a['title']) ?></div>
                                <div style="font-size:.8rem;color:var(--text-muted);display:flex;gap:.75rem;flex-wrap:wrap">
                                    <span style="background: rgba(37,99,235,0.1); padding: 0.1rem 0.4rem; border-radius: 4px; color: var(--primary);">📚 <?= htmlspecialchars($a['class_name'] ?? 'Unassigned') ?></span>
                                    <span>📅 Due: <?= $dl->format('M j, Y · g:i A') ?></span>
                                    <span>📩 <?= (int)$a['sub_count'] ?> submission<?= $a['sub_count'] != 1 ? 's' : '' ?></span>
                                    <?php if ($isPast): ?><span style="color:var(--danger)">⚠ Deadline passed</span><?php endif; ?>
                                </div>
                                <p style="font-size:.8rem;color:var(--text-secondary);margin-top:.4rem;overflow:hidden;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical">
                                    <?= htmlspecialchars($a['description']) ?>
                                </p>
                                <?php if (!empty($a['attachment'])): ?>
                                    <div style="margin-top: 0.5rem; font-size: 0.8rem;">
                                        📄 <a href="/download?file=<?= urlencode($a['attachment']) ?>&type=assignment" target="_blank" style="color: var(--primary); font-weight: 500;">View Attachment</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div style="display:flex;gap:.5rem;flex-shrink:0">
                                <a href="/manage?edit=<?= (int)$a['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                <a href="/manage?delete=<?= (int)$a['id'] ?>" class="btn btn-danger btn-sm"
                                   onclick="return confirm('Delete this assignment and all its submissions?')">Delete</a>
                            </div>
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
