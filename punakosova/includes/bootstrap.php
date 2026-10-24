<?php
/**
 * Bootstrap - ngarkohet në fillim të çdo faqeje.
 * Lidh databazën, nis sesionin dhe ngarkon funksionet ndihmëse.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Nëse databaza është e vjetër ose e paplotë (mungon tabelë/kolonë), shfaq udhëzim të qartë
set_exception_handler(function (Throwable $e) {
    $schemaError = $e instanceof PDOException && (
        in_array((string) $e->getCode(), ['42S02', '42S22'], true)                        // tabelë/kolonë që mungon
        || in_array((int) ($e->errorInfo[1] ?? 0), [1054, 1146], true)
        || preg_match('/unknown column|does not have column|table .* (doesn\'t exist|not found)/i', $e->getMessage())
    );
    if ($schemaError) {
        http_response_code(500);
        $dbInUse = '?';
        try { $dbInUse = (string) $GLOBALS['pdo']->query('SELECT DATABASE()')->fetchColumn(); } catch (Throwable $x) {}
        echo '<!DOCTYPE html><html lang="sq"><head><meta charset="UTF-8"><title>Databaza nuk përputhet</title></head>'
           . '<body style="font-family:Segoe UI,Arial,sans-serif;max-width:700px;margin:60px auto;padding:20px;line-height:1.6">'
           . '<h2 style="color:#c62828">Struktura e databazës nuk përputhet me projektin</h2>'
           . '<p>Projekti është i lidhur me databazën <b>' . htmlspecialchars($dbInUse, ENT_QUOTES, 'UTF-8') . '</b>, por asaj i mungojnë tabela ose kolona '
           . '(zakonisht është një databazë e një versioni më të vjetër të projektit).</p>'
           . '<ol><li>Hapni <a href="' . BASE_URL . '/tools/db_audit.php">' . BASE_URL . '/tools/db_audit.php</a> – tregon saktësisht çfarë mungon në <b>' . htmlspecialchars($dbInUse, ENT_QUOTES, 'UTF-8') . '</b> dhe jep SQL-in rregullues (vetëm shton, nuk fshin asgjë).</li>'
           . '<li>Aplikoni rregullimet dhe rifreskoni faqen.</li></ol>'
           . (APP_DEBUG ? '<p style="color:#666"><small>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</small></p>' : '')
           . '</body></html>';
        exit;
    }
    restore_exception_handler();
    throw $e;
});

// ---- Sesioni i sigurt ----
if (session_status() === PHP_SESSION_NONE) {
    session_name('PUNAKOSOVA_SID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

// Headera bazë sigurie
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';

// Rifresko të dhënat e përdoruesit dhe çkyçe nëse është çaktivizuar
refresh_current_user($pdo);
