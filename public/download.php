<?php
/**
 * download.php – Secure File Downloader
 * ──────────────────────────────────────
 * Proxies downloads so the uploads folder can stay completely protected
 * by `.htaccess` (Require all denied) to prevent unauthorized access.
 */
session_start();
require_once '../includes/db.php';
require_once '../includes/check_auth.php';

$file = $_GET['file'] ?? '';
$type = $_GET['type'] ?? '';

// Basic sanitization to prevent directory traversal
if (empty($file) || strpos($file, '..') !== false || strpos($file, '/') !== false || strpos($file, '\\') !== false) {
    die("Invalid file request.");
}

$baseDir = '';
if ($type === 'assignment') {
    $baseDir = '../uploads/assignments/';
} elseif ($type === 'submission') {
    $baseDir = '../uploads/submissions/'; 
} elseif ($type === 'correction') {
    $baseDir = '../uploads/corrections/'; 
} elseif ($type === 'master_correction') {
    $baseDir = '../uploads/master_corrections/'; 
} else {
    die("Invalid file type.");
}

$path = __DIR__ . '/' . $baseDir . $file;

if (!file_exists($path)) {
    die("File not found or was deleted.");
}

// Send standard download headers
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($path) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($path));

readfile($path);
exit;
