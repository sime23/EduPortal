<?php
/**
 * public/manage-students.php
 * Teacher Only: Creating classes and adding students to them.
 */
session_start();
require_once '../includes/db.php';
require_once '../includes/check_auth.php';

enforceRole('teacher');

$userId = $user['id'];
$error = '';
$success = '';

// --- Handle POST requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_class') {
        $className = trim($_POST['class_name'] ?? '');
        if (strlen($className) < 2) {
            $error = 'Class name must be at least 2 characters.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO classes (teacher_id, class_name) VALUES (?, ?)');
            if ($stmt->execute([$userId, $className])) {
                $success = 'Class created successfully.';
            } else {
                $error = 'Failed to create class.';
            }
        }
    } elseif ($action === 'create_student') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (strlen($fullName) < 2) {
            $error = 'Full name must be at least 2 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            // Check for duplicate
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'An account with that email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, "student")');
                $stmt->execute([$fullName, $email, $hash]);
                $success = 'Student account created successfully!';
            }
        }
    } elseif ($action === 'bulk_upload_students') {
        if (!isset($_FILES['student_csv']) || $_FILES['student_csv']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Error uploading file. Please try again.';
        } elseif ($_FILES['student_csv']['type'] !== 'text/csv' && mime_content_type($_FILES['student_csv']['tmp_name']) !== 'text/csv') {
            $error = 'Invalid file type. Please upload a CSV file.';
        } else {
            $filePath = $_FILES['student_csv']['tmp_name'];
            $handle = fopen($filePath, 'r');
            if (!$handle) {
                $error = 'Could not open CSV file.';
            } else {
                // Determine class assignment logic
                $targetClassId = (int)($_POST['csv_class_id'] ?? 0);
                if (($_POST['csv_class_id'] ?? '') === 'new') {
                    $newClassName = trim($_POST['new_class_name'] ?? '');
                    if (strlen($newClassName) > 0) {
                        $insClass = $pdo->prepare('INSERT INTO classes (teacher_id, class_name) VALUES (?, ?)');
                        $insClass->execute([$userId, $newClassName]);
                        $targetClassId = (int)$pdo->lastInsertId();
                    }
                } elseif ($targetClassId > 0) {
                    $verify = $pdo->prepare('SELECT id FROM classes WHERE id = ? AND teacher_id = ?');
                    $verify->execute([$targetClassId, $userId]);
                    if (!$verify->fetch()) {
                        $targetClassId = 0; // unauthorized
                    }
                }

                $added = 0;
                $skipped = 0;
                $rowNum = 0;
                $hasFormatError = false;

                // Process CSV row by row
                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    $rowNum++;
                    // Skip completely empty rows
                    if (empty(array_filter($data))) continue;
                    
                    if (count($data) < 3) {
                        $error = "Upload rejected: Row {$rowNum} does not contain all 3 required columns. Please check your file formatting (Name, Email, Password).";
                        $hasFormatError = true;
                        break;
                    }

                    $fullName = trim($data[0]);
                    $email    = trim($data[1]);
                    $password = trim($data[2]);

                    if (strlen($fullName) < 2) {
                        $error = "Upload rejected: Row {$rowNum} has an invalid Name (must be at least 2 characters).";
                        $hasFormatError = true;
                        break;
                    }
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $error = "Upload rejected: Row {$rowNum} contains an invalid email address ({$email}).";
                        $hasFormatError = true;
                        break;
                    }
                    if (strlen($password) < 6) {
                        $error = "Upload rejected: Row {$rowNum} password is too short (must be at least 6 characters).";
                        $hasFormatError = true;
                        break;
                    }

                    // Check duplicate
                    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
                    $stmt->execute([$email]);
                    $existing = $stmt->fetch();
                    $studentId = 0;
                    if (!$existing) {
                        $hash = password_hash($password, PASSWORD_BCRYPT);
                        $ins = $pdo->prepare('INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, "student")');
                        $ins->execute([$fullName, $email, $hash]);
                        $studentId = (int)$pdo->lastInsertId();
                        $added++;
                    } else {
                        $studentId = (int)$existing['id'];
                        $skipped++; // it's okay to skip existing accounts silently, or wait, no, they are just skipped
                    }
                    
                    if ($targetClassId > 0 && $studentId > 0) {
                        $insCs = $pdo->prepare('INSERT IGNORE INTO class_students (class_id, student_id) VALUES (?, ?)');
                        $insCs->execute([$targetClassId, $studentId]);
                    }
                }
                
                if (!$hasFormatError) {
                    if ($added > 0) {
                        $success = "Bulk upload successful! Created {$added} new student accounts. (Skipped {$skipped} existing emails).";
                    } elseif ($skipped > 0) {
                        $success = "No new accounts created. All {$skipped} emails already existed in the system.";
                    } else {
                        $error = "The uploaded CSV file was empty or contained no valid student records.";
                    }
                }
                fclose($handle);
            }
        }
    } elseif ($action === 'add_student') {
        $classId = (int)($_POST['class_id'] ?? 0);
        $studentIds = $_POST['student_ids'] ?? [];

        if ($classId && !empty($studentIds)) {
            // Verify the class belongs to this teacher
            $stmt = $pdo->prepare('SELECT id FROM classes WHERE id = ? AND teacher_id = ?');
            $stmt->execute([$classId, $userId]);
            if ($stmt->fetch()) {
                $addedCount = 0;
                $stmtIns = $pdo->prepare('INSERT IGNORE INTO class_students (class_id, student_id) VALUES (?, ?)');
                foreach ((array)$studentIds as $sId) {
                    $sId = (int)$sId;
                    if ($sId) {
                        $stmtIns->execute([$classId, $sId]);
                        if ($stmtIns->rowCount() > 0) $addedCount++;
                    }
                }
                $success = "{$addedCount} student(s) added to class.";
            } else {
                $error = 'Invalid class selected.';
            }
        } else {
            $error = 'Please select both a class and at least one student.';
        }
    } elseif ($action === 'remove_student') {
        $classId = (int)($_POST['class_id'] ?? 0);
        $studentId = (int)($_POST['student_id'] ?? 0);

        if ($classId && $studentId) {
            // Verify class belongs to teacher and remove
            $stmt = $pdo->prepare('
                DELETE cs FROM class_students cs
                JOIN classes c ON cs.class_id = c.id
                WHERE cs.class_id = ? AND cs.student_id = ? AND c.teacher_id = ?
            ');
            $stmt->execute([$classId, $studentId, $userId]);
            $success = 'Student removed from class.';
        }
    } elseif ($action === 'delete_student') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        if ($studentId) {
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ? AND role = "student"');
            $stmt->execute([$studentId]);
            $success = 'Student account permanently deleted.';
        }
    }
}

// --- Fetch Data ---
// 1. Get all classes for this teacher
$stmt = $pdo->prepare('SELECT id, class_name, created_at FROM classes WHERE teacher_id = ? ORDER BY created_at DESC');
$stmt->execute([$userId]);
$myClasses = $stmt->fetchAll();

// 2. Get students explicitly (role = student)
$allStudents = $pdo->query('SELECT id, full_name, email FROM users WHERE role = "student" ORDER BY full_name')->fetchAll();

// 3. Get students currently in their classes
$classStudents = [];
if ($myClasses) {
    $classIds = array_column($myClasses, 'id');
    $inClause = str_repeat('?,', count($classIds) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT cs.class_id, u.id as student_id, u.full_name, u.email 
        FROM class_students cs
        JOIN users u ON cs.student_id = u.id
        WHERE cs.class_id IN ($inClause)
        ORDER BY u.full_name
    ");
    $stmt->execute($classIds);
    $results = $stmt->fetchAll();
    
    // Group by class_id
    foreach ($results as $row) {
        $classStudents[$row['class_id']][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - EduPortal</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .class-card {
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-light);
            margin-bottom: 2rem;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        .class-card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            background: var(--bg-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .class-card-body {
            padding: 1.5rem;
        }
        /* Accordion Styles */
        details.accordion {
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-light);
            margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: all 0.2s ease;
        }
        details.accordion summary {
            padding: 1rem 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            cursor: pointer;
            list-style: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            transition: background 0.15s;
            margin: 0;
            border-bottom: 1px solid transparent;
            font-size: 0.95rem;
        }
        details.accordion summary:hover {
            background: var(--bg-hover);
        }
        details.accordion summary::-webkit-details-marker {
            display: none;
        }
        details.accordion summary::after {
            content: "▼";
            font-size: 0.75rem;
            color: var(--text-muted);
            transition: transform 0.2s;
        }
        details.accordion[open] summary {
            border-bottom-color: var(--border-light);
            background: var(--bg-light);
        }
        details.accordion[open] summary::after {
            transform: rotate(180deg);
        }
        details.accordion .accordion-body {
            padding: 1.25rem;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require_once '../includes/header.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Manage Students & Classes</h1>
            <p>Create classes and assign your students.</p>
        </div>

        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; align-items: start;">
            
            <!-- Sidebar actions -->
            <div style="display: flex; flex-direction: column;">
                
                <!-- Create Class Form -->
                <details class="accordion" open>
                    <summary>📚 Create New Class</summary>
                    <div class="accordion-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="create_class">
                            <div class="form-group mb-3">
                                <label class="form-label" for="class_name">Class Name</label>
                                <input type="text" name="class_name" id="class_name" class="form-control" placeholder="e.g. CS101 Fall 2026" required>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width: 100%">Create Class</button>
                        </form>
                    </div>
                </details>

                <!-- CSV Upload Form -->
                <details class="accordion">
                    <summary>📄 CSV Bulk Upload</summary>
                    <div class="accordion-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="bulk_upload_students">
                            <div class="form-group mb-2">
                                <label class="form-label" for="csv_class_id" style="font-weight: 600;">Assign to Class (Optional)</label>
                                <select name="csv_class_id" id="csv_class_id" class="form-control mb-2">
                                    <option value="">-- Do not assign to any class yet --</option>
                                    <?php foreach ($myClasses as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_name']) ?></option>
                                    <?php endforeach; ?>
                                    <option value="new" style="font-weight: bold; color: var(--primary);">+ Create New Class explicitly</option>
                                </select>
                                <input type="text" name="new_class_name" id="new_class_name" class="form-control mt-2" placeholder="Enter new class name..." style="display:none; border-color: var(--primary);">
                                <script>
                                document.getElementById('csv_class_id').addEventListener('change', function() {
                                    document.getElementById('new_class_name').style.display = (this.value === 'new') ? 'block' : 'none';
                                    document.getElementById('new_class_name').required = (this.value === 'new');
                                });
                                </script>
                            </div>
                            <div class="form-group mb-2">
                                <label class="form-label" style="font-weight: 600;">CSV File</label>
                                <input type="file" name="student_csv" id="student_csv" class="form-control" accept=".csv" required style="font-size: 0.85rem">
                            </div>
                            <div class="text-muted" style="font-size: 0.8rem; margin-top: 0.5rem; margin-bottom: 1.5rem; line-height: 1.4;">
                                <strong>Important:</strong> Upload a <code>.csv</code> file, not Excel.<br>
                                <strong>Required Columns:</strong><br>
                                1. Full Name<br>
                                2. Email Address<br>
                                3. Password (Min 6 chars)
                            </div>
                            <button type="submit" class="btn btn-secondary" style="width: 100%; padding: 0.6rem">Upload CSV</button>
                        </form>
                    </div>
                </details>

                <!-- Create Student Form -->
                <details class="accordion">
                    <summary>🎓 Create Student Account</summary>
                    <div class="accordion-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="create_student">
                            <div class="form-group">
                                <label class="form-label" for="full_name">Student Name</label>
                                <input type="text" name="full_name" id="full_name" class="form-control" placeholder="e.g. John Smith" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="email">Email Address</label>
                                <input type="email" name="email" id="email" class="form-control" placeholder="john@example.com" required>
                            </div>
                            <div class="form-group" style="margin-bottom: 1.5rem;">
                                <label class="form-label" for="password">Password</label>
                                <input type="password" name="password" id="password" class="form-control" placeholder="Min 6 characters" required>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width: 100%; background: var(--success); border-color: var(--success);">Create Student</button>
                        </form>
                    </div>
                </details>

                <!-- Add Student to Class Form -->
                <?php if ($myClasses): ?>
                <details class="accordion">
                    <summary>➕ Add Student to Class</summary>
                    <div class="accordion-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_student">
                            <div class="form-group">
                                <label class="form-label" for="class_id">Select Class</label>
                                <select name="class_id" id="class_id" class="form-control" required>
                                    <option value="" disabled selected>-- Choose Class --</option>
                                    <?php foreach ($myClasses as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 1.5rem;">
                                <label class="form-label">Select Student(s)</label>
                                <div style="border: 1px solid var(--border); border-radius: var(--radius-md); max-height: 200px; overflow-y: auto; padding: 0.5rem; background: var(--white);">
                                    <?php if (empty($allStudents)): ?>
                                        <div class="text-muted" style="font-size: 0.85rem; padding: 0.5rem;">No students available in the system yet.</div>
                                    <?php else: ?>
                                        <?php foreach ($allStudents as $s): ?>
                                        <label style="display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.5rem; border-radius: 4px; cursor: pointer; transition: background 0.15s; font-size: 0.85rem; border-bottom: 1px solid var(--border-light); margin: 0;" onmouseover="this.style.background='var(--bg-light)'" onmouseout="this.style.background='transparent'">
                                            <input type="checkbox" name="student_ids[]" value="<?= $s['id'] ?>" style="width: 16px; height: 16px; accent-color: var(--primary); cursor: pointer; margin: 0;">
                                            <span style="display: flex; flex-direction: column; line-height: 1.2;">
                                                <span style="font-weight: 500; color: var(--text-primary);"><?= htmlspecialchars($s['full_name']) ?></span>
                                                <span class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($s['email']) ?></span>
                                            </span>
                                        </label>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-secondary" style="width: 100%">Add Student</button>
                        </form>
                    </div>
                </details>
                <?php endif; ?>
            </div>

            <!-- Main classes view -->
            <div>
                <h3 class="mb-4 text-primary fw-600">Your Classes</h3>
                <?php if (empty($myClasses)): ?>
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 48px; height: 48px; opacity: 0.5; margin-bottom: 1rem;"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/><path d="m9 16 2 2 4-4"/></svg>
                        <p>You haven't created any classes yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($myClasses as $c): ?>
                        <div class="class-card">
                            <div class="class-card-header">
                                <h4 style="margin: 0; font-size: 1.1rem; color: var(--text-primary);"><?= htmlspecialchars($c['class_name']) ?></h4>
                                <span class="badge" style="background: var(--bg-hover); color: var(--text-secondary);">
                                    <?= count($classStudents[$c['id']] ?? []) ?> Students
                                </span>
                            </div>
                            <div class="class-card-body">
                                <?php if (empty($classStudents[$c['id']])): ?>
                                    <p class="text-muted" style="font-size: 0.9rem;">No students assigned to this class.</p>
                                <?php else: ?>
                                    <div class="table-wrapper" style="max-height: 300px; overflow-y: auto;">
                                        <table style="margin: 0;">
                                            <thead style="position: sticky; top: 0; z-index: 10;">
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th style="text-align: right">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($classStudents[$c['id']] as $student): ?>
                                                    <tr>
                                                        <td class="fw-600" style="font-size: 0.9rem;"><?= htmlspecialchars($student['full_name']) ?></td>
                                                        <td class="text-muted" style="font-size: 0.85rem;"><?= htmlspecialchars($student['email']) ?></td>
                                                        <td style="text-align: right; white-space: nowrap;">
                                                            <form method="POST" style="display:inline" onsubmit="return confirm('Remove student from this class?');">
                                                                <input type="hidden" name="action" value="remove_student">
                                                                <input type="hidden" name="class_id" value="<?= $c['id'] ?>">
                                                                <input type="hidden" name="student_id" value="<?= $student['student_id'] ?>">
                                                                <button type="submit" class="btn btn-sm" style="background: #fee2e2; color: #dc2626; border: none; padding: 0.25rem 0.5rem;" title="Remove from class">Remove</button>
                                                            </form>
                                                            <form method="POST" style="display:inline" onsubmit="return confirm('WARNING: This will permanently delete this student account and all their submissions. Are you sure?');">
                                                                <input type="hidden" name="action" value="delete_student">
                                                                <input type="hidden" name="student_id" value="<?= $student['student_id'] ?>">
                                                                <button type="submit" class="btn btn-sm" style="background: #ef4444; color: white; border: none; padding: 0.25rem 0.5rem; margin-left: 0.25rem;" title="Delete account completely">Delete</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>
</body>
</html>
