<?php
require __DIR__ . '/../includes/config.php';
requireRole('admin');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$count = function ($sql) {
    return (int)db()->query($sql)->fetch_assoc()['total'];
};
$recentScoresResult = db()->query('SELECT u.name, q.title AS quiz_title, s.percentage FROM scores s JOIN quizzes q ON q.id = s.quiz_id JOIN students st ON st.id = s.student_id JOIN users u ON u.id = st.user_id ORDER BY s.submitted_at DESC LIMIT 5');
$recentScores = [];
while ($score = $recentScoresResult->fetch_assoc()) {
    $recentScores[] = [
        'label' => $score['name'] . ' · ' . $score['quiz_title'],
        'detail' => number_format((float)$score['percentage'], 2) . '%',
    ];
}

echo json_encode([
    'metrics' => [
        'students' => $count('SELECT COUNT(*) AS total FROM users WHERE role = "student"'),
        'teachers' => $count('SELECT COUNT(*) AS total FROM users WHERE role = "teacher"'),
        'lessons' => $count('SELECT COUNT(*) AS total FROM lessons'),
        'quizzes' => $count('SELECT COUNT(*) AS total FROM quizzes'),
        'challenges' => $count('SELECT COUNT(*) AS total FROM coding_challenges'),
        'pending_reviews' => $count('SELECT COUNT(*) AS total FROM submissions WHERE status = "Pending Review"'),
    ],
    'lists' => ['recent_scores' => $recentScores],
], JSON_INVALID_UTF8_SUBSTITUTE);