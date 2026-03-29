<?php
/**
 * submit.php – File Submission Page (Students only)
 * ──────────────────────────────────────────────────
 * Supports drag-and-drop or click-to-browse.
 * Validates file type (PDF/DOCX) and size (max 10 MB) in PHP.
 * Uses PDO prepared statements throughout.
 */
session_start();
require_once 'includes/db.php';
require_once 'includes/check_auth.php';

// Only students can submit work
enforceRole('student');

$userId = $user['id'];
$error  = '';
$success = '';

// Pre-select assignment if ID is passed via query string
$preselectedId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ── Fetch available (non-expired or all) assignments ─────────
$stmt = $pdo->prepare('
    SELECT a.id, a.title, a.deadline, c.class_name,
           s.id AS already_submitted,
           COALESCE(s.attempts, 0) AS attempts
    FROM   assignments a
    JOIN   class_students cs ON a.class_id = cs.class_id AND cs.student_id = :u1
    LEFT JOIN classes c ON a.class_id = c.id
    LEFT JOIN submissions s ON s.assignment_id = a.id AND s.user_id = :u2
    WHERE  a.deadline >= NOW()
    ORDER BY a.deadline ASC
');
$stmt->execute([':u1' => $userId, ':u2' => $userId]);
$assignments = $stmt->fetchAll();

// ── POST: Process file upload ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assignmentId = (int)($_POST['assignment_id'] ?? 0);

    // Validate assignment exists and isn't past deadline
    $aStmt = $pdo->prepare('SELECT * FROM assignments WHERE id = :id AND deadline >= NOW()');
    $aStmt->execute([':id' => $assignmentId]);
    $assignment = $aStmt->fetch();

    if (!$assignment) {
        $error = 'Invalid assignment or the deadline has passed.';
    } elseif (!isset($_FILES['submission_file']) || $_FILES['submission_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please select a valid file to upload.';
    } else {
        $file     = $_FILES['submission_file'];
        $maxSize  = 10 * 1024 * 1024; // 10 MB in bytes

        // File size validation
        if ($file['size'] > $maxSize) {
            $error = 'File size exceeds 10 MB limit. Please choose a smaller file.';
        } else {
            // Determine MIME type using finfo for accuracy (not $_FILES['type'])
            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);

            $allowedMimes = [
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/msword',
            ];
            $allowedExts  = ['pdf', 'docx', 'doc'];
            $fileExt      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($mimeType, $allowedMimes) || !in_array($fileExt, $allowedExts)) {
                $error = 'Only PDF and DOCX files are accepted.';
            } elseif (strtotime($assignment['deadline']) < time()) {
                $error = 'The deadline for this assignment has already passed. Submissions are closed.';
            } else {
                    // Check if student already submitted for this assignment
                    $dupCheck = $pdo->prepare('SELECT id, file_path, attempts FROM submissions WHERE assignment_id = :aid AND user_id = :uid');
                    $dupCheck->execute([':aid' => $assignmentId, ':uid' => $userId]);
                    $existing = $dupCheck->fetch();

                    $uploadDir  = '../uploads/submissions/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    $safeName   = 'sub_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
                    $uploadPath = $uploadDir . $safeName;

                    if ($existing && (int)$existing['attempts'] >= 3) {
                        $error = 'You have reached the maximum of 3 submission attempts for this assignment.';
                    } elseif (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        if ($existing) {
                            // Delete old file if it exists
                            $oldFile = $uploadDir . $existing['file_path'];
                            if (file_exists($oldFile)) @unlink($oldFile);

                            // Update existing record
                            $upd = $pdo->prepare('
                                UPDATE submissions SET file_path = :fp, attempts = attempts + 1, submitted_at = NOW()
                                WHERE id = :id
                            ');
                            $upd->execute([':fp' => $safeName, ':id' => $existing['id']]);
                            $success = 'Your submission has been updated successfully! (Attempt ' . ((int)$existing['attempts'] + 1) . '/3)';
                        } else {
                            // Record new submission
                            $ins = $pdo->prepare('
                                INSERT INTO submissions (assignment_id, user_id, file_path, attempts)
                                VALUES (:aid, :uid, :fp, 1)
                            ');
                            $ins->execute([
                                ':aid' => $assignmentId,
                                ':uid' => $userId,
                                ':fp'  => $safeName,
                            ]);
                            $success = 'Your assignment has been submitted successfully! (Attempt 1/3)';
                        }
                        // Refresh assignment list
                        $stmt->execute([':u1' => $userId, ':u2' => $userId]);
                        $assignments = $stmt->fetchAll();
                    } else {
                        $error = 'Upload failed. Please check server permissions and try again.';
                    }
                }
            }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Work – EduPortal</title>
    <link rel="stylesheet" href="/ass/assets/style.css">
</head>
<body>
<div class="app-shell">
    <?php require_once 'includes/header.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Submit Work</h1>
            <p>Upload your completed assignment (PDF or DOCX, max 10 MB)</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 12 2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <div class="card" style="max-width:640px;">
            <form method="POST" action="submit.php" enctype="multipart/form-data" novalidate id="submitForm">

                <!-- Assignment Selector -->
                <div class="form-group">
                    <label class="form-label" for="assignment_id">Select Assignment</label>
                    <?php if (empty($assignments)): ?>
                        <p class="text-muted" style="font-size:.875rem">No open assignments available for submission.</p>
                    <?php else: ?>
                    <select name="assignment_id" id="assignment_id" class="form-control" required>
                        <option value="">— Choose an assignment —</option>
                        <?php foreach ($assignments as $a): ?>
                            <?php $rem = 3 - (int)$a['attempts']; ?>
                            <option value="<?= (int)$a['id'] ?>"
                                <?= $preselectedId === (int)$a['id'] ? 'selected' : '' ?>
                                <?= $rem <= 0 ? 'disabled' : '' ?>>
                                <?= htmlspecialchars($a['title']) ?>
                                (<?= htmlspecialchars($a['class_name']) ?> — 
                                <?= $rem <= 0 ? 'Limit Reached' : ($a['already_submitted'] ? "RE-SUBMIT ($rem left)" : 'Due: ' . date('M j', strtotime($a['deadline']))) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </div>

                <!-- Drag-and-Drop Zone -->
                <div class="form-group">
                    <label class="form-label">Upload File</label>
                    <div class="drop-zone" id="dropZone">
                        <input type="file" name="submission_file" id="fileInput"
                               accept=".pdf,.doc,.docx" required
                               onchange="handleFileSelect(this)">
                        <div class="drop-zone-icon">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="17 8 12 3 7 8"/>
                                <line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                        </div>
                        <h3>Drag & drop your file here</h3>
                        <p>or click to browse · PDF / DOCX · Max 10 MB</p>
                        <div class="file-chosen" id="fileChosen" style="display:none;">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            <span id="fileNameDisplay"></span>
                            <span id="fileSizeDisplay" style="margin-left:auto;opacity:.6"></span>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Upload / Update Submission
                </button>
            </form>
        </div>

        <!-- Already submitted list -->
        <?php
        $submitted = array_filter($assignments, fn($a) => $a['already_submitted']);
        if (!empty($submitted)): ?>
        <div class="card mt-3">
            <div class="card-title mb-2">Already Submitted</div>
            <?php foreach ($submitted as $a): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--border-light)">
                <span style="font-size:.875rem;font-weight:500"><?= htmlspecialchars($a['title']) ?></span>
                <span class="badge badge-submitted">✓ Submitted</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<script>
const dropZone   = document.getElementById('dropZone');
const fileInput  = document.getElementById('fileInput');
const fileChosen = document.getElementById('fileChosen');
const submitBtn  = document.getElementById('submitBtn');

function handleFileSelect(input) {
    if (input.files && input.files[0]) {
        showFile(input.files[0]);
    }
}

function showFile(file) {
    document.getElementById('fileNameDisplay').textContent = file.name;
    const mb = (file.size / 1048576).toFixed(2);
    document.getElementById('fileSizeDisplay').textContent = mb + ' MB';
    fileChosen.style.display = 'flex';
    submitBtn.disabled = false;
}

// Drag and drop events
['dragenter','dragover'].forEach(evt => {
    dropZone.addEventListener(evt, e => { e.preventDefault(); dropZone.classList.add('dragover'); });
});
['dragleave','drop'].forEach(evt => {
    dropZone.addEventListener(evt, e => { e.preventDefault(); dropZone.classList.remove('dragover'); });
});
dropZone.addEventListener('drop', e => {
    const file = e.dataTransfer.files[0];
    if (file) {
        // Transfer to the real file input so the form can POST it
        const dt = new DataTransfer();
        dt.items.add(file);
        fileInput.files = dt.files;
        showFile(file);
    }
});

// Client-side size pre-check (PHP is the real validation)
fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (file && file.size > 10 * 1024 * 1024) {
        alert('File exceeds 10 MB. Please choose a smaller file.');
        fileInput.value = '';
        fileChosen.style.display = 'none';
        submitBtn.disabled = true;
    }
});
</script>
</body>
</html>
