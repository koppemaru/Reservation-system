<?php
// db.php
// データベース接続設定
$host     = 'localhost';
$dbname   = ''; //type your database name
$charset  = 'utf8mb4';
$user     = ''; //type your username
$pass     = ''; //type your password

$dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $db = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log('DB Connection Error: ' . $e->getMessage());
    exit('データベース接続に失敗しました。');
}
