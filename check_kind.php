<?php
require_once __DIR__ . '/db.php';
$col = $pdo->query("SHOW COLUMNS FROM users LIKE 'kind'")->fetch();
if ($col) {
    echo "exists\n";
} else {
    echo "missing\n";
}
