<?php
require_once __DIR__ . '/db.php';
$col = $pdo->query("SHOW COLUMNS FROM users LIKE 'face_descriptor'")->fetch();
if ($col) {
    echo "exists\n";
    echo "Field type: " . $col['Type'] . "\n";
} else {
    echo "missing\n";
}
