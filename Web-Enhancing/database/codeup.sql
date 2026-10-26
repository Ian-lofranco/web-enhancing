CREATE DATABASE IF NOT EXISTS codeup;
USE codeup;

DROP TABLE IF EXISTS progress;
DROP TABLE IF EXISTS badges;
DROP TABLE IF EXISTS password_recovery_codes;
DROP TABLE IF EXISTS scores;
DROP TABLE IF EXISTS submissions;
DROP TABLE IF EXISTS coding_challenges;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS quizzes;
DROP TABLE IF EXISTS lessons;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS teachers;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','teacher','student') NOT NULL,
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE password_recovery_codes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  code_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_password_recovery_code_hash (code_hash),
  KEY idx_password_recovery_user (user_id, used_at, expires_at),
  CONSTRAINT fk_password_recovery_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  student_id VARCHAR(50) DEFAULT NULL,
  course VARCHAR(100) DEFAULT 'Computer Science',
  phone VARCHAR(50) DEFAULT NULL,
  dob DATE DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE teachers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  employee_id VARCHAR(50) DEFAULT NULL,
  specialization VARCHAR(100) DEFAULT 'Programming',
  bio TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE lessons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  topic VARCHAR(150) NOT NULL,
  content TEXT NOT NULL,
  objectives TEXT,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE quizzes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  lesson_id INT DEFAULT NULL,
  teacher_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE SET NULL,
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
);

CREATE TABLE questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT NOT NULL,
  question_text TEXT NOT NULL,
  option_a VARCHAR(255) NOT NULL,
  option_b VARCHAR(255) NOT NULL,
  option_c VARCHAR(255) NOT NULL,
  option_d VARCHAR(255) NOT NULL,
  correct_option CHAR(1) NOT NULL,
  explanation TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

CREATE TABLE coding_challenges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  difficulty VARCHAR(50) DEFAULT 'Beginner',
  lesson_id INT DEFAULT NULL,
  teacher_id INT DEFAULT NULL,
  starter_code TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE SET NULL,
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
);

CREATE TABLE submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  challenge_id INT NOT NULL,
  code TEXT NOT NULL,
  status VARCHAR(50) DEFAULT 'Submitted',
  score INT DEFAULT 0,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (challenge_id) REFERENCES coding_challenges(id) ON DELETE CASCADE
);

CREATE TABLE scores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  quiz_id INT NOT NULL,
  score INT NOT NULL,
  total_questions INT NOT NULL,
  percentage DECIMAL(5,2) NOT NULL,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

CREATE TABLE badges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE progress (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  lesson_id INT NOT NULL,
  status VARCHAR(50) DEFAULT 'in_progress',
  completion_percent INT DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
);

INSERT INTO users (name, email, password, role, created_at) VALUES
('Admin User', 'admin@codeup.com', '$2y$10$zOzhakJNWudVCw6vdOB9Hu5fpuz2jKn5/BdWMedxwKW6UE55yfFUa', 'admin', NOW()),
('Teacher User', 'teacher@codeup.com', '$2y$10$3k9.H9hakEYbiMM4X0IVKuIamURVlwMNKyf.GepZgQ5FYl71M.e9C', 'teacher', NOW()),
('Student User', 'student@codeup.com', '$2y$10$iwyb0mv8nPI5IV4MGH1l5u6zxB074sW5diVE4.avE.K5RQYlM2B/i', 'student', NOW());

INSERT INTO students (user_id, student_id, course, created_at) VALUES
(3, 'SR-0003', 'Computer Science', NOW());

INSERT INTO teachers (user_id, employee_id, specialization, created_at) VALUES
(2, 'TR-0002', 'Programming', NOW());

INSERT INTO lessons (title, topic, content, objectives, created_by, created_at) VALUES
('Variables and Data Types', 'Variables & Data Types', 'Variables store data in memory. In PHP, variables start with $. Data types include strings, integers, floats, booleans, arrays, and objects.', 'Understand how variables work and what data types are used in programming.', 2, NOW()),
('Input and Output', 'Input/Output', 'Output is printed to the screen using echo/print. Input is read using forms and PHP superglobals such as $_POST and $_GET.', 'Learn how to display output and collect input from users.', 2, NOW()),
('Operators', 'Operators', 'Operators perform actions such as arithmetic, comparison, logical checks, and assignment. Examples include +, -, ==, &&, and ||.', 'Identify common operators and know when to use each type.', 2, NOW()),
('Conditional Statements', 'Conditional Statements', 'If/else blocks let a program decide between different actions based on conditions.', 'Use if, else if, and switch logic in programs.', 2, NOW()),
('Loops', 'Loops', 'Loops repeat instructions. Common examples are for, while, and foreach loops.', 'Practice writing loops and understand iteration.', 2, NOW()),
('Functions', 'Functions', 'Functions let developers reuse code blocks. They can accept parameters and return values.', 'Build modular code using functions.', 2, NOW()),
('Arrays', 'Arrays', 'Arrays store multiple values in one variable. PHP supports indexed and associative arrays.', 'Work with arrays for organized data management.', 2, NOW());

INSERT INTO quizzes (title, lesson_id, teacher_id, created_at) VALUES
('Variables Quiz', 1, 1, NOW()),
('Loop Basics Quiz', 5, 1, NOW());

INSERT INTO questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation, created_at) VALUES
(1, 'Which symbol is used to declare a PHP variable?', '$', '#', '@', '&', 'A', 'PHP variables begin with the $ symbol.', NOW()),
(1, 'Which data type stores true or false values?', 'String', 'Boolean', 'Integer', 'Array', 'B', 'Boolean stores logical true/false values.', NOW()),
(2, 'Which loop repeats a block of code a fixed number of times?', 'while', 'for', 'if', 'switch', 'B', 'The for loop is commonly used when the number of iterations is known.', NOW()),
(2, 'What does foreach loop through?', 'A file', 'An array', 'A database', 'A string literal', 'B', 'foreach is designed to iterate over array values and keys.', NOW());

INSERT INTO coding_challenges (title, description, difficulty, lesson_id, teacher_id, starter_code, created_at) VALUES
('Simple Calculator', 'Create a PHP function that adds two numbers and prints the result.', 'Beginner', 3, 1, 'function addNumbers($a, $b) {\n    // write your code here\n}\n\necho addNumbers(5, 7);', NOW()),
('Grade Checker', 'Write a function that returns "Pass" if the score is 60 or more, otherwise "Fail".', 'Intermediate', 4, 1, 'function checkGrade($score) {\n    // write your code here\n}\n\necho checkGrade(75);', NOW());

INSERT INTO badges (student_id, name, description, earned_at) VALUES
(1, 'First Lesson', 'Completed the first learning module.', NOW()),
(1, 'Quiz Starter', 'Finished an introductory quiz successfully.', NOW());

INSERT INTO progress (student_id, lesson_id, status, completion_percent, updated_at) VALUES
(1, 1, 'completed', 100, NOW()),
(1, 2, 'in_progress', 60, NOW());

INSERT INTO scores (student_id, quiz_id, score, total_questions, percentage, submitted_at) VALUES
(1, 1, 2, 2, 100.00, NOW());
