
<?php
require __DIR__ . '/../includes/config.php';

requireRole('student');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function sendJsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);

    echo json_encode(
        $data,
        JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
    );

    exit;
}

try {
    // Get the logged-in student's profile
    $student = getStudentData((int) ($_SESSION['user_id'] ?? 0));

    if (!$student || empty($student['id'])) {
        sendJsonResponse([
            'error' => 'Student profile not found.'
        ], 404);
    }

    $studentId = (int) $student['id'];

    // Get student progress and score summaries
    $progress = getProgressSummary($studentId);
    $scores = getStudentScoreSummary($studentId);

    // Get the latest five lessons
    $lessonsResult = db()->query(
        'SELECT title, topic
         FROM lessons
         ORDER BY created_at DESC
         LIMIT 5'
    );

    $lessons = [];

    while ($lesson = $lessonsResult->fetch_assoc()) {
        $lessons[] = [
            'title' => $lesson['title'] ?? '',
            'topic' => $lesson['topic'] ?? ''
        ];
    }

    // Get the student's latest five badges
    $badgesStmt = db()->prepare(
        'SELECT name, description
         FROM badges
         WHERE student_id = ?
         ORDER BY earned_at DESC
         LIMIT 5'
    );

    $badgesStmt->bind_param('i', $studentId);
    $badgesStmt->execute();

    $badgesResult = $badgesStmt->get_result();
    $badges = [];

    while ($badge = $badgesResult->fetch_assoc()) {
        $badges[] = [
            'name' => $badge['name'] ?? '',
            'description' => $badge['description'] ?? ''
        ];
    }

    $badgesStmt->close();

    // Get the student's latest five quiz scores
    $scoresStmt = db()->prepare(
        'SELECT
            q.title AS quiz_title,
            s.score,
            s.total_questions,
            s.percentage
         FROM scores s
         INNER JOIN quizzes q ON q.id = s.quiz_id
         WHERE s.student_id = ?
         ORDER BY s.submitted_at DESC
         LIMIT 5'
    );

    $scoresStmt->bind_param('i', $studentId);
    $scoresStmt->execute();

    $scoresResult = $scoresStmt->get_result();
    $recentScores = [];

    while ($score = $scoresResult->fetch_assoc()) {
        $recentScores[] = [
            'quiz_title' => $score['quiz_title'] ?? '',
            'detail' =>
                (int) ($score['score'] ?? 0) .
                ' / ' .
                (int) ($score['total_questions'] ?? 0) .
                ' · ' .
                number_format((float) ($score['percentage'] ?? 0), 2) .
                '%'
        ];
    }

    $scoresStmt->close();

    // Count all badges earned by the student
    $badgesCount = (int) getBadgeCount($studentId);

    // Return the dashboard data
    sendJsonResponse([
        'success' => true,
        'progress' => (float) ($progress['average_progress'] ?? 0),
        'completed' => (int) ($progress['completed_lessons'] ?? 0),
        'badges_count' => $badgesCount,
        'average_score' => (float) ($scores['average_score'] ?? 0),
        'lessons' => $lessons,
        'badges' => $badges,
        'recent_scores' => $recentScores
    ]);

} catch (Throwable $exception) {
    error_log(
        'Student dashboard API error: ' . $exception->getMessage()
    );

    sendJsonResponse([
        'success' => false,
        'error' => 'Unable to load dashboard data. Please try again.'
    ], 500);
}
