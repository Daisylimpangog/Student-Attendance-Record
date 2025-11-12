<?php
/**
 * Initialize Grading System Tables
 * This script ensures all required tables for the grading system exist
 */
session_start();
require_once __DIR__ . '/db.php';

// Only allow admin or direct access from localhost
if (!isset($_SESSION['user_id']) && $_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== 'localhost') {
    http_response_code(403);
    die('Access denied');
}

try {
    // Create subjects table
    $pdo->exec("CREATE TABLE IF NOT EXISTS subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(191) NOT NULL UNIQUE,
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Create grades table
    $pdo->exec("CREATE TABLE IF NOT EXISTS grades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        teacher_id INT DEFAULT NULL,
        subject VARCHAR(191) NOT NULL,
        schedule VARCHAR(20) NOT NULL,
        grade VARCHAR(16) NOT NULL,
        remarks VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_student_subject_schedule (student_id, subject, schedule)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Insert default subjects if none exist
    $count = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
    if ($count == 0) {
        $defaultSubjects = [
            'Anatomy & Physiology',
            'Basic Emergency Care',
            'Child Diseases',
            'Child Care',
            'Personal Care',
            'Mobilization',
            'Nursing Procedure',
            'Infection Control/Incontinence',
            'Elderly Care',
            'Hospice Care',
            'Mental Health Issues',
            'Diet & Nutrition',
            'Home Management',
            'Going Abroad',
            'Employment & Interview',
            'Medical Terminologies',
            'Medical Math & Pharmacology',
            'Legal Ethics',
            'Personality Development'
        ];
        
        $stmt = $pdo->prepare('INSERT INTO subjects (name) VALUES (?)');
        foreach ($defaultSubjects as $subject) {
            try {
                $stmt->execute([$subject]);
            } catch (PDOException $e) {
                // Skip if duplicate
            }
        }
    }

    echo "✓ Grading system tables initialized successfully!<br>";
    echo "✓ Subjects table created<br>";
    echo "✓ Grades table created<br>";
    
    if ($count == 0) {
        echo "✓ Default subjects inserted<br>";
    } else {
        echo "✓ Subjects already exist (" . $count . " found)<br>";
    }
    
    echo "<br><a href='admin_grades.php'>Go to Manage Grades</a>";
    
} catch (PDOException $e) {
    echo "✗ Error initializing grading system: " . htmlspecialchars($e->getMessage());
    exit;
}
