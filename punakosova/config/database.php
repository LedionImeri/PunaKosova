<?php
/**
 * Lidhja me databazën me PDO.
 * Ekspozon variablën globale $pdo.
 */
require_once __DIR__ . '/config.php';

try {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    // Sinkronizo zonën kohore të MySQL me atë të PHP (për NOW(), CURRENT_TIMESTAMP)
    $pdo->exec("SET time_zone = '" . date('P') . "'");
} catch (PDOException $e) {
    http_response_code(500);
    $msg = APP_DEBUG ? htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') : 'Ju lutem provoni më vonë.';
    echo '<!DOCTYPE html><html lang="sq"><head><meta charset="UTF-8"><title>Gabim në databazë</title></head>'
       . '<body style="font-family:sans-serif;max-width:640px;margin:60px auto;padding:20px">'
       . '<h2 style="color:#c62828">Nuk u arrit lidhja me databazën</h2>'
       . '<p>Sigurohuni që MySQL në XAMPP është i ndezur dhe që databaza <b>punakosova</b> është importuar.</p>'
       . '<p style="color:#666"><small>' . $msg . '</small></p></body></html>';
    exit;
}
