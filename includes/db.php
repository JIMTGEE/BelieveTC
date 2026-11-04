<?php
/**
 * Database connection for Believe Travel Consult.
 * Include this file wherever a DB connection ($pdo) is needed.
 */

$host     = 'localhost';
$dbname   = 'believet_believetc';
$username = 'believet';        // ← replace with the exact user from cPanel
$password = '0*9t9Q#8BsByKa';  // ← not empty

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("We're experiencing a technical issue. Please try again later.");
}

define('BASE_URL', 'https://www.yourdomain.com/'); // ← update this to your real domain