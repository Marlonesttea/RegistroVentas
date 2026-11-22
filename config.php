<?php
declare(strict_types=1);

function database_connection(): PDO
{
    $host = getenv('DB_HOST') ?: 'sql110.infinityfree.com';
    $database = getenv('DB_NAME') ?: 'if0_43501336_registro_ventas_zapatos';
    $username = getenv('DB_USER') ?: 'if0_43501336';
    $password = getenv('DB_PASSWORD') ?: 'marlonesttea';
    $dsn = 'mysql:host=' . $host . ';dbname=' . $database . ';charset=utf8mb4';

    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
