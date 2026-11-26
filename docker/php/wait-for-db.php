<?php

declare(strict_types=1);

$host = getenv('DB_HOST') ?: 'mysql';
$port = (int) (getenv('DB_PORT') ?: 3306);
$database = getenv('DB_DATABASE') ?: 'ritm';
$username = getenv('DB_USERNAME') ?: 'ritm';
$password = getenv('DB_PASSWORD') ?: '';

$attempts = 30;

for ($attempt = 1; $attempt <= $attempts; ++$attempt) {
    try {
        new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database),
            $username,
            $password,
            [PDO::ATTR_TIMEOUT => 3],
        );

        fwrite(STDOUT, "Database is reachable.\n");

        exit(0);
    } catch (Throwable $exception) {
        fwrite(STDERR, sprintf("Waiting for database (%d/%d): %s\n", $attempt, $attempts, $exception->getMessage()));
        sleep(2);
    }
}

fwrite(STDERR, "Database did not become reachable in time.\n");

exit(1);
