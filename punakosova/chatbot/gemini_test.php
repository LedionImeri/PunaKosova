<?php
/**
 * PunaBot - test i integrimit me Google Gemini API
 * ---------------------------------------------------------------
 * Bën një kërkesë REALE te Gemini API dhe tregon nëse funksionon.
 * API key nuk shfaqet asnjëherë (vetëm nëse është vendosur dhe gjatësia).
 *
 * Përdorimi:
 *   1) Nga Command Prompt (CLI):
 *        C:\xampp\php\php.exe chatbot\gemini_test.php
 *   2) Nga browser-i, VETËM si administrator i kyçur:
 *        http://localhost/PunaKosova/chatbot/gemini_test.php
 *      (teston variablat ashtu siç i sheh Apache/XAMPP)
 */

$isCli = PHP_SAPI === 'cli';

if ($isCli) {
    require_once __DIR__ . '/chatbot_config.php';
} else {
    require_once __DIR__ . '/../includes/bootstrap.php';
    require_once __DIR__ . '/chatbot_config.php';
    if (!has_role('admin')) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "403 - Ky test është i disponueshëm vetëm për administratorin e kyçur.\n";
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
}
require_once __DIR__ . '/PunaBot.php';

$failed = false;
$line = function (string $status, string $label, string $detail = '') use (&$failed) {
    if ($status === 'FAIL') {
        $failed = true;
    }
    echo str_pad("[$status]", 7) . ' ' . $label . ($detail !== '' ? ' — ' . $detail : '') . "\n";
};
$hint = function (string $kind): string {
    return [
        'auth'    => 'Çelësi nuk pranohet. Kontrolloni GEMINI_API_KEY në Google AI Studio (aistudio.google.com/apikey).',
        'model'   => 'Modeli nuk u gjet për këtë çelës. Provoni GEMINI_MODEL=' . GEMINI_DEFAULT_MODEL . ' ose shikoni ai.google.dev/gemini-api/docs/models.',
        'quota'   => 'U arrit limiti i planit falas (RPM/RPD). Prisni pak ose shikoni limitet në aistudio.google.com/rate-limit.',
        'region'  => 'Gemini API nuk lejohet nga vendndodhja aktuale.',
        'network' => 'Nuk u arrit lidhja me Google. Kontrolloni internetin; për "SSL certificate problem" vendosni CHATBOT_CA_BUNDLE në chatbot_config.php.',
        'server'  => 'Shërbimi i Gemini është i mbingarkuar. Provoni përsëri pas pak.',
        'config'  => 'Konfigurimi i PHP/variablave mungon.',
    ][$kind] ?? 'Shikoni mesazhin e gabimit më sipër.';
};

echo "PunaBot × Gemini API — test i integrimit (" . date('Y-m-d H:i:s') . ', ' . ($isCli ? 'CLI' : 'Apache/web') . ")\n";
echo str_repeat('-', 70) . "\n";

// 1) Mjedisi i PHP
$line(version_compare(PHP_VERSION, '7.4', '>=') ? 'OK' : 'FAIL', 'PHP ' . PHP_VERSION);
if (function_exists('curl_version')) {
    $cv = curl_version();
    $line('OK', 'cURL ' . $cv['version'] . ' / ' . ($cv['ssl_version'] ?? 'pa SSL'));
} else {
    $line('FAIL', 'Zgjerimi cURL mungon', 'aktivizoni extension=curl në php.ini dhe rinisni Apache');
}

// 2) Konfigurimi
$line(AI_PROVIDER === 'gemini' ? 'OK' : 'FAIL', 'AI_PROVIDER = ' . AI_PROVIDER,
    AI_PROVIDER === 'gemini' ? '' : 'vendosni GEMINI_API_KEY (ose PUNABOT_PROVIDER=gemini)');
if (GEMINI_API_KEY === '') {
    $line('FAIL', 'GEMINI_API_KEY', 'MUNGON — ky proces nuk e sheh variablën e mjedisit');
} else {
    $line('OK', 'GEMINI_API_KEY', 'i vendosur (' . strlen(GEMINI_API_KEY) . ' karaktere, vlera nuk shfaqet)');
}
$line('OK', 'Modeli: ' . GEMINI_MODEL, GEMINI_MODEL === GEMINI_DEFAULT_MODEL ? 'parazgjedhja' : 'nga GEMINI_MODEL');

if ($failed) {
    echo "\nREZULTATI: DËSHTOI — rregulloni konfigurimin më sipër. PunaBot vazhdon të punojë në modalitetin bazë.\n";
    exit($isCli ? 1 : 0);
}

// 3) Verifikimi i çelësit dhe modelit (GET models/{model})
try {
    $info = PunaBot::geminiModelInfo();
    $line('OK', 'Çelësi u pranua dhe modeli ekziston', ($info['displayName'] ?? GEMINI_MODEL)
        . (isset($info['inputTokenLimit']) ? ', input ' . number_format((int) $info['inputTokenLimit']) . ' token' : ''));
} catch (PunaBotAIException $e) {
    $line('FAIL', 'Verifikimi i modelit (' . $e->kind . ')', PunaBot::redact($e->getMessage()));
    echo '        → ' . $hint($e->kind) . "\n";
}

// 4) Kërkesë reale generateContent në shqip
if (!$failed) {
    $system = 'Ti je PunaBot, asistenti i platformës PunaKosova për kërkimin e punës në Kosovë. Përgjigju shkurt (maks. 2 fjali) në shqip standard.';
    $question = 'Pershendetje! Qysh mundem me aplikue per nje pune ne PunaKosova?';
    try {
        $r = PunaBot::geminiGenerate($system, [['role' => 'user', 'content' => $question]], 1024);
        $u = $r['usage'];
        $line('OK', 'Kërkesë reale generateContent', $r['ms'] . ' ms, modelVersion: ' . $r['model']
            . ', token: ' . ($u['promptTokenCount'] ?? '?') . ' hyrje / ' . ($u['candidatesTokenCount'] ?? '?') . ' dalje'
            . (isset($u['thoughtsTokenCount']) ? ' / ' . $u['thoughtsTokenCount'] . ' thinking' : ''));
        echo "\n  Pyetja:     $question\n";
        echo '  Përgjigjja: ' . str_replace("\n", "\n              ", $r['text']) . "\n";
    } catch (PunaBotAIException $e) {
        $line('FAIL', 'Kërkesa generateContent (' . $e->kind . ')', PunaBot::redact($e->getMessage()));
        echo '        → ' . $hint($e->kind) . "\n";
    }
}

echo "\n" . ($failed
    ? "REZULTATI: DËSHTOI — PunaBot do të përdorë modalitetin bazë derisa të rregullohet gabimi.\n"
    : "REZULTATI: SUKSES — Gemini API funksionon. PunaBot do të shfaqë \"AI aktiv · Gemini\".\n");
exit($isCli && $failed ? 1 : 0);
