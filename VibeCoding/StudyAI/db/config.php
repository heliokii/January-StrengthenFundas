<?php
// XAMPP/local MySQL — edit DB_NAME, DB_USER, DB_PASS if you use a different database/user.
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'studymate_ai');
define('DB_USER', 'root');
define('DB_PASS', '');

function db() {
  static $pdo;
  if (!$pdo) {
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  }
  return $pdo;
}

function apply_migrations() {
    $pdo = db();
    // Migrations live in StudyAI/migrations/ (parent of db/)
    $migrations_dir = dirname(__DIR__) . '/migrations/';
    $migration_files = glob($migrations_dir . '*.sql');
    sort($migration_files);

    foreach ($migration_files as $migration_file) {
        // A very simple way to track executed migrations could be a file or a table.
        // For this example, we'll just run them if the file exists, which is not robust for production.
        // A more robust solution would check a `migrations` table to see if it has been run already.
        $sql = file_get_contents($migration_file);
        $pdo->exec($sql);
    }
}

// Run migrations once: in phpMyAdmin, create database "studymate_ai" then run migrations/001_initial_schema.sql and 002_add_users.sql
// apply_migrations();
