<?php
/**
 * PunaBot - test diagnostikues i lidhjes me Groq API
 * ---------------------------------------------------------------
 * Bën një kërkesë REALE te Groq API dhe tregon nëse PunaBot do të funksionojë me AI.
 * Çelësi nuk shfaqet asnjëherë (tregohet vetëm nëse është vendosur dhe sa karaktere ka).
 *
 * Përdorimi (nuk kërkon terminal):
 *   Hapni në browser, i kyçur si administrator:
 *     http://localhost/PunaKosova/chatbot/groq_test.php        (XAMPP)
 *     http://localhost:8888/PunaKosova/chatbot/groq_test.php   (MAMP në macOS)
 * Opsionalisht edhe nga terminali:  php chatbot/groq_test.php
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
    if (function_exists('set_time_limit')) {
        @set_time_limit(60);
    }
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
        'auth'    => 'Çelësi nuk pranohet. Kontrolloni GROQ_API_KEY në config/local.php (çelësat: console.groq.com/keys).',
        'model'   => 'Modeli nuk u gjet për këtë çelës. Hiqni GROQ_MODEL nga config/local.php ose shikoni console.groq.com/docs/models.',
        'quota'   => 'U arrit limiti i kërkesave të Groq. Prisni pak (zakonisht një minutë) dhe provoni përsëri.',
        'region'  => 'Groq API nuk lejon qasje nga ky rrjet/vend.',
        'network' => 'Nuk u arrit lidhja me api.groq.com. Kontrolloni internetin; për "SSL certificate problem" vendosni CHATBOT_CA_BUNDLE në config/local.php.',
        'server'  => 'Shërbimi i Groq është përkohësisht i mbingarkuar. Provoni përsëri pas pak.',
        'config'  => 'Konfigurimi mungon: krijoni config/local.php nga config/local.example.php dhe vendosni GROQ_API_KEY.',
    ][$kind] ?? 'Shikoni mesazhin e gabimit më sipër.';
};

echo "PunaBot × Groq API — test i lidhjes (" . date('Y-m-d H:i:s') . ', ' . ($isCli ? 'CLI' : 'web') . ")\n";
echo str_repeat('-', 70) . "\n";

// 1) Mjedisi i PHP
$line(version_compare(PHP_VERSION, '8.0', '>=') ? 'OK' : 'FAIL', 'PHP ' . PHP_VERSION, version_compare(PHP_VERSION, '8.0', '>=') ? '' : 'projekti kërkon PHP 8.0 ose më të ri');
if (function_exists('curl_version')) {
    $cv = curl_version();
    $line('OK', 'cURL ' . $cv['version'] . ' / ' . ($cv['ssl_version'] ?? 'pa SSL'));
} else {
    $line('FAIL', 'Zgjerimi cURL mungon', 'aktivizoni extension=curl në php.ini dhe rinisni serverin');
}

// 2) Konfigurimi
$line(is_file(__DIR__ . '/../config/local.php') ? 'OK' : 'INFO', 'config/local.php', is_file(__DIR__ . '/../config/local.php') ? 'ekziston' : 'mungon (çelësi mund të vijë edhe nga variabla e mjedisit)');
$line(AI_PROVIDER === 'groq' ? 'OK' : 'FAIL', 'Ofruesi i AI: ' . AI_PROVIDER, AI_PROVIDER === 'groq' ? '' : 'vendosni GROQ_API_KEY në config/local.php');
if (GROQ_API_KEY === '') {
    $line('FAIL', 'GROQ_API_KEY', 'MUNGON');
} else {
    $line('OK', 'GROQ_API_KEY', 'i vendosur (' . strlen(GROQ_API_KEY) . ' karaktere, vlera nuk shfaqet)');
}
$line('OK', 'Modeli: ' . GROQ_MODEL, GROQ_MODEL === GROQ_DEFAULT_MODEL ? 'parazgjedhja' : 'nga GROQ_MODEL');

if ($failed) {
    echo "\n        → " . $hint('config') . "\n";
    echo "\nREZULTATI: DËSHTOI — rregulloni konfigurimin më sipër. PunaBot vazhdon të punojë në modalitetin bazë.\n";
    exit($isCli ? 1 : 0);
}

// 3) Verifikimi i çelësit dhe modelit (GET /models)
try {
    $info = PunaBot::groqModelInfo();
    $line('OK', 'Çelësi u pranua dhe modeli ekziston', ($info['id'] ?? GROQ_MODEL)
        . (isset($info['context_window']) ? ', kontekst ' . number_format((int) $info['context_window']) . ' token' : ''));
} catch (PunaBotAIException $e) {
    $line('FAIL', 'Verifikimi i modelit (' . $e->kind . ')', PunaBot::redact($e->getMessage()));
    echo '        → ' . $hint($e->kind) . "\n";
}

// 4) Kërkesë reale chat/completions në shqip
if (!$failed) {
    $system = 'Ti je PunaBot, asistenti i platformës PunaKosova për kërkimin e punës në Kosovë. Përgjigju shkurt (maks. 2 fjali) në shqip standard.';
    $question = 'Pershendetje! Qysh mundem me aplikue per nje pune ne PunaKosova?';
    try {
        $r = PunaBot::groqChat($system, [['role' => 'user', 'content' => $question]]);
        $u = $r['usage'];
        $line('OK', 'Kërkesë reale chat/completions', $r['ms'] . ' ms, modeli: ' . $r['model']
            . ', token: ' . ($u['prompt_tokens'] ?? '?') . ' hyrje / ' . ($u['completion_tokens'] ?? '?') . ' dalje');
        echo "\n  Pyetja:     $question\n";
        echo '  Përgjigjja: ' . str_replace("\n", "\n              ", $r['text']) . "\n";
    } catch (PunaBotAIException $e) {
        $line('FAIL', 'Kërkesa chat/completions (' . $e->kind . ')', PunaBot::redact($e->getMessage()));
        echo '        → ' . $hint($e->kind) . "\n";
    }
}

echo "\n" . ($failed
    ? "REZULTATI: DËSHTOI — PunaBot do të përdorë modalitetin bazë derisa të rregullohet gabimi.\n"
    : "REZULTATI: SUKSES — Groq API funksionon. PunaBot do të shfaqë \"AI aktiv · Groq\".\n");
exit($isCli && $failed ? 1 : 0);
