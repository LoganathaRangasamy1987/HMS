<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$environmentFile = dirname(__DIR__).'/.env';
$environment = file_get_contents($environmentFile);
if (! preg_match('/^APP_ENV=local$/m', $environment)) {
    fwrite(STDERR, "This helper only configures the local development environment.\n");
    exit(1);
}

try {
    $connection = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if ($connection->query("SELECT COUNT(*) FROM mysql.user WHERE User = 'hms_app'")->fetchColumn() > 0 || $connection->query("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = 'hms_foundation'")->fetchColumn() > 0) {
        fwrite(STDERR, "The application database or account already exists. Configure .env manually; existing credentials and data were preserved.\n");
        exit(1);
    }
    $password = bin2hex(random_bytes(24));
    $connection->exec('CREATE DATABASE hms_foundation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $connection->exec("CREATE USER 'hms_app'@'localhost' IDENTIFIED BY ".$connection->quote($password));
    $connection->exec("GRANT ALL PRIVILEGES ON hms_foundation.* TO 'hms_app'@'localhost'");
    $environment = preg_replace('/^DB_PASSWORD=.*$/m', 'DB_PASSWORD='.$password, $environment);
    file_put_contents($environmentFile, $environment);
    echo "Dedicated hms_foundation database and local application account created. Credentials saved to .env.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Local database setup failed. Check XAMPP MySQL and the documented database setup steps.\n");
    exit(1);
}
