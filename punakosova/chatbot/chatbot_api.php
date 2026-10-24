<?php
/**
 * PunaBot API endpoint (JSON)
 *
 * POST  chatbot/chatbot_api.php
 * Header: X-CSRF-Token, Content-Type: application/json
 * Body:  {"action": "message", "message": "..."}  |  {"action": "history"}  |  {"action": "clear"}
 *
 * Siguria: vetëm POST, CSRF token, sesion, PDO prepared statements,
 * rate limiting, kufizim i gjatësisë së mesazhit, të dhëna vetëm të përdoruesit aktual.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/chatbot_config.php';
require_once __DIR__ . '/PunaBot.php';

header('Cache-Control: no-store');
// Thirrja te AI (me një riprovim) duhet të përfundojë para limitit të PHP-së; në Windows ky limit mat kohën reale.
if (function_exists('set_time_limit')) {
    @set_time_limit(60);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_response(['ok' => false, 'error' => 'Metoda nuk lejohet.'], 405);
}
if (!csrf_valid()) {
    json_response(['ok' => false, 'error' => 'Sesioni ka skaduar. Rifreskoni faqen dhe provoni përsëri.'], 419);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}
$action = is_string($input['action'] ?? null) ? $input['action'] : 'message';

// ID e bisedës ruhet në sesion (mbetet edhe pas session_regenerate_id)
if (empty($_SESSION['chat_sid'])) {
    $_SESSION['chat_sid'] = bin2hex(random_bytes(16));
}
$ip = substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
$bot = new PunaBot($pdo, current_user(), $_SESSION['chat_sid'], $ip);

try {
    switch ($action) {
        case 'history':
            $ai = PunaBot::aiStatus();   // për Groq: verifikim real i çelësit + modelit (ruhet në sesion)
            json_response([
                'ok'          => true,
                'history'     => array_map(fn($m) => ['sender' => $m['sender'], 'message' => $m['message'], 'source' => $m['source'] ?? null], $bot->history(30)),
                'ai'          => $ai['state'] === 'active',
                'ai_status'   => $ai['state'],
                'ai_provider' => $ai['provider'],
            ]);
            break;

        case 'clear':
            $bot->clearHistory();
            json_response(['ok' => true]);
            break;

        case 'message':
            $message = trim((string) ($input['message'] ?? ''));
            $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message); // hiq karakteret e kontrollit
            $lang = $bot->detectLanguage($message ?: 'a');

            if ($message === '') {
                json_response(['ok' => false, 'error' => $lang === 'en' ? 'Please write a message.' : 'Ju lutem shkruani një mesazh.'], 422);
            }
            if (mb_strlen($message, 'UTF-8') > CHATBOT_MAX_MESSAGE_LENGTH) {
                json_response(['ok' => false, 'error' => ($lang === 'en' ? 'Message is too long (max ' : 'Mesazhi është shumë i gjatë (maks. ') . CHATBOT_MAX_MESSAGE_LENGTH . ($lang === 'en' ? ' characters).' : ' karaktere).')], 422);
            }
            if ($err = $bot->rateLimitError($lang)) {
                json_response(['ok' => false, 'error' => $err], 429);
            }
            $result = $bot->handle($message);
            json_response(['ok' => true] + $result);
            break;

        default:
            json_response(['ok' => false, 'error' => 'Veprim i panjohur.'], 400);
    }
} catch (Throwable $e) {
    error_log('[PunaBot] ' . PunaBot::redact($e->getMessage()));
    json_response(['ok' => false, 'error' => 'Ndodhi një gabim në server. Ju lutem provoni përsëri pas pak.'], 500);
}
