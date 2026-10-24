<?php
/**
 * PunaBot - konfigurimi i chatbot-it AI
 * ---------------------------------------------------------------
 * KUJDES: asnjë API key nuk shkruhet në këtë file. Çelësat lexohen nga
 * config/local.php (skedar privat, jashtë Git-it) ose nga variablat e mjedisit
 * të serverit. Frontend-i (JavaScript/HTML) nuk e sheh kurrë çelësin.
 * Qasja direkte nga browser-i në këtë file është e bllokuar me .htaccess.
 *
 * Ofruesi i vetëm i AI është Groq API (endpoint i përputhshëm me OpenAI, https://console.groq.com).
 *   AI_PROVIDER = 'groq' - kur GROQ_API_KEY është vendosur
 *   AI_PROVIDER = 'none' - pa çelës: PunaBot funksionon me rregulla + kërkim në databazë (modaliteti bazë)
 *
 * Vlerat (në config/local.php ose si variabla mjedisi):
 *   GROQ_API_KEY       - çelësi nga console.groq.com/keys (i detyrueshëm për Groq)
 *   GROQ_MODEL         - opsional (parazgjedhje: GROQ_DEFAULT_MODEL)
 *   CHATBOT_CA_BUNDLE  - opsional, rruga e cacert.pem kur cURL nuk i verifikon dot certifikatat
 */
require_once __DIR__ . '/../config/config.php';   // local_setting()

$__groqKey = local_setting('GROQ_API_KEY', '', true);
define('AI_PROVIDER', $__groqKey !== '' ? 'groq' : 'none');

// ---- Groq (OpenAI-compatible Chat Completions) ----
// Modeli i parazgjedhur: 'openai/gpt-oss-120b' - model "production" në Groq, jo i planifikuar për
// çaktivizim (verifikuar në console.groq.com/docs/models dhe /docs/deprecations, tetor 2026).
// Alternativë më e shpejtë: 'openai/gpt-oss-20b' (vendoseni me GROQ_MODEL).
define('GROQ_DEFAULT_MODEL', 'openai/gpt-oss-120b');
define('GROQ_API_KEY', $__groqKey);
define('GROQ_MODEL', preg_match('#^[a-z0-9][a-z0-9._/\-]{2,80}$#i', local_setting('GROQ_MODEL', '', true)) ? local_setting('GROQ_MODEL', '', true) : GROQ_DEFAULT_MODEL);
if (!defined('GROQ_API_BASE')) {
    define('GROQ_API_BASE', 'https://api.groq.com/openai/v1');   // adresa zyrtare; nuk ndryshohet nga mjedisi
}
define('GROQ_MAX_TOKENS', 1024);       // përfshin edhe arsyetimin e brendshëm të modeleve gpt-oss
define('GROQ_TEMPERATURE', 0.4);
define('GROQ_REASONING_EFFORT', 'low'); // vetëm për modelet me arsyetim (gpt-oss, qwen)
define('GROQ_STATUS_TTL', 600);         // sa sekonda ruhet në sesion verifikimi i çelësit/modelit

unset($__groqKey);

// ---- Parametrat e bisedës ----
define('CHATBOT_NAME', 'PunaBot');
define('CHATBOT_MAX_MESSAGE_LENGTH', 500);  // karaktere për mesazh nga përdoruesi
define('CHATBOT_HISTORY_LIMIT', 8);         // sa mesazhe të mëparshme i dërgohen AI-së
define('CHATBOT_MAX_JOBS', 5);              // sa punë shfaqen në rezultat
define('CHATBOT_TIMEOUT', 20);              // sekonda për thirrjen e API-së

// ---- Rate limiting ----
define('CHATBOT_RATE_PER_MINUTE', 8);       // mesazhe në minutë
define('CHATBOT_RATE_PER_DAY', 100);        // mesazhe në ditë
define('CHATBOT_MIN_INTERVAL', 2);          // sekonda minimale mes dy mesazheve

// ---- SSL ----
// Nëse merrni gabimin "SSL certificate problem" (ndodh te disa instalime XAMPP në Windows), shkarkoni
// cacert.pem nga https://curl.se/ca/cacert.pem dhe vendosni rrugën te CHATBOT_CA_BUNDLE në config/local.php.
define('CHATBOT_CA_BUNDLE', local_setting('CHATBOT_CA_BUNDLE', '', true));
