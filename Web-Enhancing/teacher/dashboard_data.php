
<?php
require __DIR__ . '/../includes/config.php';

requireRole('teacher');

header('Content-Type: text/html; charset=utf-8');

$conn = db();

$checks = [
    'users' => [
        'id', 'name', 'role'
    ],
    'teachers' => [
        'id', 'user_id'
    ],
    'lessons' => [
        'created_by'
    ],
    'quizzes' => [
        'id', 'title', 'teacher_id'
    ],
    'scores' => [
        'student_id', 'quiz_id', 'percentage', 'submitted_at'
    ],
    'students' => [
        'id', 'user_id'
    ],
    'coding_challenges' => [
        'id', 'teacher_id'
    ],
    'submissions' => [
        'challenge_id', 'status'
    ]
];

$missingTables = [];
$missingColumns = [];
$allPassed = true;

function escapeOutput($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teacher Dashboard Database Checker</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            margin: 0;
            padding: 25px;
            color: #1e293b;
        }

        .container {
            max-width: 900px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,.08);
        }

        h1 {
            color: #1d4ed8;
        }

        .table-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }

        .success {
            color: #15803d;
            font-weight: bold;
        }

        .error {
            color: #dc2626;
            font-weight: bold;
        }

        .warning {
            color: #b45309;
            font-weight: bold;
        }

        .summary {
            background: #eff6ff;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        a {
            color: #1d4ed8;
        }
    </style>
</head>
<body>
<div class="container">

<h1>CodeUp Teacher Dashboard</h1>
<h2>Database and Column Checker</h2>

<?php
try {
    $dbResult = $conn->query('SELECT DATABASE() AS db_name');
    $dbRow = $dbResult->fetch_assoc();
    $dbName = $dbRow['db_name'] ?? '';

    echo '<p>Database: <strong>' .
        escapeOutput($dbName ?: 'None selected') .
        '</strong></p>';

    if (!$dbName) {
        throw new RuntimeException('No database is selected.');
    }

    echo '<p class="success">Database connection successful.</p>';

    foreach ($checks as $table => $expectedColumns) {
        $stmt = $conn->prepare(
            'SELECT COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?'
        );

        $stmt->bind_param('s', $table);
        $stmt->execute();
        $result = $stmt->get_result();

        $actualColumns = [];

        while ($row = $result->fetch_assoc()) {
            $actualColumns[] = $row['COLUMN_NAME'];
        }

        $stmt->close();

        echo '<div class="table-card">';
        echo '<h3>' . escapeOutput($table) . '</h3>';

        if (empty($actualColumns)) {
            echo '<p class="error">MISSING TABLE</p>';
            $missingTables[] = $table;
            $allPassed = false;
            echo '</div>';
            continue;
        }

        echo '<p class="success">Table exists</p>';

        $missing = array_diff($expectedColumns, $actualColumns);

        if (empty($missing)) {
            echo '<p class="success">All required columns exist.</p>';
        } else {
            echo '<p class="error">Missing columns:</p><ul>';

            foreach ($missing as $column) {
                echo '<li>' . escapeOutput($column) . '</li>';
            }

            echo '</ul>';

            $missingColumns[$table] = array_values($missing);
            $allPassed = false;
        }

        echo '<details><summary>View actual columns</summary><ul>';

        foreach ($actualColumns as $column) {
            echo '<li>' . escapeOutput($column) . '</li>';
        }

        echo '</ul></details>';
        echo '</div>';
    }

    echo '<div class="summary">';
    echo '<h2>Dashboard Database Summary</h2>';
    echo '<p>Tables checked: ' . count($checks) . '</p>';
    echo '<p>Missing tables: ' . count($missingTables) . '</p>';
    echo '<p>Tables with missing columns: ' . count($missingColumns) . '</p>';

    if ($allPassed) {
        echo '<h3 class="success">PASS: Expected dashboard tables and columns exist.</h3>';
    } else {
        echo '<h3 class="error">FAIL: Some tables or columns need checking.</h3>';
    }

    echo '</div>';

} catch (Throwable $e) {
    http_response_code(500);

    echo '<p class="error">Unable to complete the database check.</p>';
    echo '<p>Check includes/config.php and the PHP error log.</p>';

    error_log('CodeUp teacher database check: ' . $e->getMessage());
}
?>

<p><a href="dashboard.php">Return to Teacher Dashboard</a></p>

</div>
</body>
</html>
