<?php
// Database settings - change as needed for your environment
return [
    'db_host' => '127.0.0.1',
    'db_name' => 'attendance_db',
    'db_user' => 'root',
    'db_pass' => '',
    'db_charset' => 'utf8mb4',
    
    // Email settings for password reset
    'email_from' => 'noreply@chpcebu.edu.ph',
    'email_from_name' => 'CHPCEBU Attendance System',
    'smtp_enabled' => false, // Set to true to use SMTP
    'smtp_host' => 'smtp.mailtrap.io', // or your SMTP server
    'smtp_port' => 2525,
    'smtp_user' => '',
    'smtp_pass' => '',
    'use_php_mail' => true, // Use PHP's built-in mail() function
];

