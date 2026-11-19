<?php
declare(strict_types=1);

session_start();
require __DIR__ . '/config.php';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function redirect_to(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function money_to_cents(string $value): int
{
    if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $value)) {
        throw new RuntimeException('Escribe un importe válido, con máximo dos decimales.');
    }

    $parts = explode('.', $value, 2);
    $decimals = isset($parts[1]) ? str_pad($parts[1], 2, '0') : '00';
    return ((int) $parts[0] * 100) + (int) $decimals;
}

function cents_to_decimal(int $cents): string
{
    $sign = $cents < 0 ? '-' : '';
    $absolute = abs($cents);
    return $sign . intdiv($absolute, 100) . '.' . str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
}

function prepare_photo_uploads(array $files): array
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        return [];
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $selected = [];
    foreach ($files['name'] as $index => $name) {
        if ($files['error'][$index] !== UPLOAD_ERR_NO_FILE) {
            $selected[] = $index;
        }
    }
    if (count($selected) > 8) {
        throw new RuntimeException('Puedes agregar hasta 8 fotos por operación.');
    }

    $directory = __DIR__ . '/uploads';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('No se pudo crear la carpeta de fotos uploads/.');
    }

    $saved = [];
    try {
        foreach ($selected as $index) {
            if ($files['error'][$index] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Una de las fotos no se pudo cargar.');
            }
            if ($files['size'][$index] > 5 * 1024 * 1024) {
                throw new RuntimeException('Cada foto debe pesar 5 MB o menos.');
            }

            $temporaryPath = $files['tmp_name'][$index];
            $imageInfo = @getimagesize($temporaryPath);
            $mime = $imageInfo ? $imageInfo['mime'] : '';
            if (!isset($allowedTypes[$mime])) {
                throw new RuntimeException('Solo se permiten fotos JPG, PNG, WEBP o GIF.');
            }

            $filename = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mime];
            $relativePath = 'uploads/' . $filename;
            if (!move_uploaded_file($temporaryPath, $directory . '/' . $filename)) {
                throw new RuntimeException('No se pudo guardar una de las fotos.');
            }
            $saved[] = $relativePath;
        }
    } catch (Throwable $exception) {
        remove_photo_files($saved);
        throw $exception;
    }

    return $saved;
}

function remove_photo_files(array $paths): void
{
    foreach ($paths as $path) {
        if (strpos($path, 'uploads/') === 0) {
            $fullPath = __DIR__ . '/uploads/' . basename($path);
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        }
    }
}

$pdo = null;
$databaseError = '';
try {
    $pdo = database_connection();
} catch (PDOException $exception) {
    $databaseError = $exception->getMessage();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
