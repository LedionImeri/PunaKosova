<?php
/**
 * PunaKosova - Konfigurimi kryesor i aplikacionit
 * Ndryshoni vlerat sipas instalimit tuaj të XAMPP.
 */

// ---- Konfigurimi privat i kompjuterit (opsional): config/local.php ----
// Krijohet duke kopjuar config/local.example.php në config/local.php. Aty vendosen çelësi i
// Groq API dhe, nëse ndryshojnë në një kompjuter tjetër (p.sh. MAMP në macOS), të dhënat e databazës.
// Skedari nuk shkon në Git (.gitignore) dhe, si skedar PHP që vetëm kthen një varg, nuk shfaq asgjë në browser.
if (!function_exists('local_setting')) {
    /**
     * Kthen një vlerë nga config/local.php; për çelësat API lejohet edhe variabla e mjedisit.
     * Nëse vlera mungon, kthehet $default (sjellja e mëparshme e projektit).
     */
    function local_setting(string $name, string $default = '', bool $allowEnv = false): string
    {
        static $local = null;
        if ($local === null) {
            $file = __DIR__ . '/local.php';
            $local = is_file($file) ? include $file : [];
            if (!is_array($local)) {
                $local = [];
            }
        }
        if (isset($local[$name]) && is_scalar($local[$name]) && trim((string) $local[$name]) !== '') {
            return trim((string) $local[$name]);
        }
        if ($allowEnv) {
            $env = getenv($name);
            if (is_string($env) && trim($env) !== '') {
                return trim($env);
            }
        }
        return $default;
    }
}

// ---- Aplikacioni ----
define('APP_NAME', 'PunaKosova');
define('APP_DEBUG', true);                 // Vendoseni false në prodhim
// URL bazë (pa "/" në fund). Nëse projekti është në htdocs/PunaKosova => '/PunaKosova'
define('BASE_URL', local_setting('BASE_URL', '/PunaKosova'));

// ---- Databaza ----
define('DB_HOST', local_setting('DB_HOST', '127.0.0.1'));
define('DB_PORT', local_setting('DB_PORT', '3306'));
define('DB_NAME', local_setting('DB_NAME', 'punakosova2'));      // databaza e versionit aktual (punakosova = backup i vjetër, nuk përdoret)
define('DB_USER', local_setting('DB_USER', 'root'));
define('DB_PASS', local_setting('DB_PASS', ''));                 // XAMPP si parazgjedhje nuk ka fjalëkalim

// ---- Rrugët (paths) ----
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('CV_PATH', UPLOAD_PATH . '/cv');
define('LOGO_PATH', UPLOAD_PATH . '/logos');

// ---- Ngarkimi i file-ve ----
define('MAX_CV_SIZE', 5 * 1024 * 1024);   // 5 MB
define('MAX_LOGO_SIZE', 2 * 1024 * 1024); // 2 MB

// ---- Faqosja ----
define('JOBS_PER_PAGE', 9);
define('ADMIN_PER_PAGE', 15);

date_default_timezone_set('Europe/Belgrade');

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}
