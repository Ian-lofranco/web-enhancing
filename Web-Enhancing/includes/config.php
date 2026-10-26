<?php
session_start();
date_default_timezone_set('Asia/Manila');

if (isset($_SESSION['user_id'])) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// Database configuration
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'codeup';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
if (in_array(basename($scriptDir), ['admin', 'student', 'teacher'], true)) {
    $scriptDir = dirname($scriptDir);
}
$scriptDir = $scriptDir === '/' || $scriptDir === '.' ? '' : $scriptDir;

define('BASE_URL', rtrim($scheme . '://' . $httpHost . $scriptDir, '/'));

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Database connection failed. Check that MySQL is running, the credentials are correct, and the codeup database has been imported.');
}

function db()
{
    global $conn;
    return $conn;
}

function redirect($path)
{
    header('Location: ' . BASE_URL . '/' . ltrim($path, '/'));
    exit;
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function currentUser()
{
    if (!isLoggedIn()) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function getStudentData($userId)
{
    if (!$userId) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM students WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function getTeacherData($userId)
{
    if (!$userId) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM teachers WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function requireLogin()
{
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function requireRole($roles)
{
    requireLogin();

    $user = currentUser();
    if (!$user || !in_array($user['role'], (array)$roles, true)) {
        redirect('login.php');
    }
}

function getRoleLabel($role)
{
    $roles = [
        'admin' => 'Administrator',
        'teacher' => 'Teacher',
        'student' => 'Student'
    ];

    return $roles[$role] ?? ucfirst($role);
}

function getProgressSummary($studentId)
{
    $stmt = db()->prepare(
        'SELECT COUNT(l.id) AS total_lessons, COALESCE(SUM(CASE WHEN COALESCE(p.completion_percent, 0) >= 100 THEN 1 ELSE 0 END), 0) AS completed_lessons, COALESCE(ROUND(AVG(COALESCE(p.completion_percent, 0)), 2), 0) AS average_progress FROM lessons l LEFT JOIN (SELECT lesson_id, MAX(completion_percent) AS completion_percent FROM progress WHERE student_id = ? GROUP BY lesson_id) p ON p.lesson_id = l.id'
    );
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function awardBadgeIfMissing($studentId, $name, $description)
{
    $studentId = (int)$studentId;
    if ($studentId < 1 || trim($name) === '') {
        return false;
    }

    $check = db()->prepare('SELECT id FROM badges WHERE student_id = ? AND name = ? LIMIT 1');
    $check->bind_param('is', $studentId, $name);
    $check->execute();
    if ($check->get_result()->fetch_assoc()) {
        return false;
    }

    $insert = db()->prepare('INSERT INTO badges (student_id, name, description, earned_at) VALUES (?, ?, ?, NOW())');
    $insert->bind_param('iss', $studentId, $name, $description);
    $insert->execute();
    return true;
}

function getBadgeCount($studentId)
{
    $stmt = db()->prepare('SELECT COUNT(*) AS total FROM badges WHERE student_id = ?');
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return (int)($row['total'] ?? 0);
}

function getStudentScoreSummary($studentId)
{
    $stmt = db()->prepare('SELECT COUNT(*) AS total_attempts, ROUND(AVG(percentage), 2) AS average_score FROM scores WHERE student_id = ?');
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function formatDate($date)
{
    if (empty($date)) {
        return 'N/A';
    }

    return date('M d, Y', strtotime($date));
}

function csrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

require_once __DIR__ . '/functions.php';
