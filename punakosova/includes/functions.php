<?php
/**
 * Funksione ndihmëse të përbashkëta për gjithë aplikacionin.
 */

// ---------------------------------------------------------------------
// Siguria e daljes (XSS) dhe URL
// ---------------------------------------------------------------------

/** Escapon tekstin për HTML (mbrojtje nga XSS). */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Ndërton URL relative me bazën e projektit. */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = ROOT_PATH . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 1;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

/** Ridrejton dhe ndalon ekzekutimin. Lejon vetëm URL të brendshme. */
function redirect(string $to): void
{
    if (preg_match('#^https?://#i', $to)) {
        $host = parse_url($to, PHP_URL_HOST);
        if ($host !== ($_SERVER['HTTP_HOST'] ?? '') && $host !== parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
            $to = url('index.php');
        }
    } elseif ($to === '' || $to[0] !== '/') {
        $to = url($to);
    }
    header('Location: ' . $to);
    exit;
}

/** Kthen në faqen e mëparshme (nëse është e brendshme) ose në $fallback. */
function redirect_back(string $fallback): void
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($ref !== '' && parse_url($ref, PHP_URL_HOST) === parse_url('http://' . $host, PHP_URL_HOST)) {
        redirect($ref);
    }
    redirect($fallback);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function is_ajax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------------
// Mesazhet flash dhe vlerat e vjetra të formave
// ---------------------------------------------------------------------

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** Ruaj vlerat e formës për t'i rimbushur pas gabimit. */
function set_old(array $data): void
{
    unset($data['password'], $data['password_confirm'], $data['_csrf']);
    $_SESSION['old'] = $data;
}

function old(string $key, $default = ''): string
{
    return e($_SESSION['old'][$key] ?? $default);
}

function clear_old(): void
{
    unset($_SESSION['old']);
}

// ---------------------------------------------------------------------
// Inputet dhe validimi
// ---------------------------------------------------------------------

function input(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function input_int(string $key, int $default = 0): int
{
    $v = $_POST[$key] ?? $_GET[$key] ?? null;
    return filter_var($v, FILTER_VALIDATE_INT) !== false ? (int) $v : $default;
}

function str_len(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

function excerpt(?string $text, int $len = 140): string
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $text)));
    if (str_len($text) <= $len) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $len, 'UTF-8')) . '…';
}

function valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function valid_phone(string $phone): bool
{
    return $phone === '' || (bool) preg_match('/^\+?[0-9\s\-()]{6,20}$/', $phone);
}

/** Fjalëkalim: min 8 karaktere, një shkronjë dhe një numër. */
function valid_password(string $p): bool
{
    return strlen($p) >= 8 && preg_match('/[A-Za-z]/', $p) && preg_match('/[0-9]/', $p);
}

function slugify(string $text): string
{
    $map = ['ë' => 'e', 'Ë' => 'e', 'ç' => 'c', 'Ç' => 'c'];
    $text = strtr($text, $map);
    $text = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $text), '-'));
    return $text !== '' ? $text : 'kategori-' . time();
}

/** Teksti me rreshta të rinj -> HTML i sigurt. */
function nl2br_e(?string $text): string
{
    return nl2br(e($text));
}

// ---------------------------------------------------------------------
// Data dhe formatimi
// ---------------------------------------------------------------------

function format_date(?string $date, bool $withTime = false): string
{
    if (!$date) {
        return '-';
    }
    $ts = strtotime($date);
    return $withTime ? date('d.m.Y H:i', $ts) : date('d.m.Y', $ts);
}

function time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    $diff = time() - strtotime($datetime);
    if ($diff < 60)      return 'tani';
    if ($diff < 3600)    return floor($diff / 60) . ' min më parë';
    if ($diff < 86400)   return floor($diff / 3600) . ' orë më parë';
    if ($diff < 604800)  return floor($diff / 86400) . ' ditë më parë';
    return format_date($datetime);
}

function days_left(string $deadline): int
{
    return (int) floor((strtotime($deadline . ' 23:59:59') - time()) / 86400);
}

function format_salary($min, $max): string
{
    $min = $min !== null && $min !== '' ? (float) $min : null;
    $max = $max !== null && $max !== '' ? (float) $max : null;
    if ($min && $max) return number_format($min, 0, ',', '.') . ' - ' . number_format($max, 0, ',', '.') . ' €';
    if ($min)         return 'Nga ' . number_format($min, 0, ',', '.') . ' €';
    if ($max)         return 'Deri ' . number_format($max, 0, ',', '.') . ' €';
    return 'Me marrëveshje';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name));
    $ini = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $ini .= mb_strtoupper(mb_substr($p, 0, 1, 'UTF-8'), 'UTF-8');
    }
    return $ini ?: '?';
}

// ---------------------------------------------------------------------
// Listat (enum) në shqip
// ---------------------------------------------------------------------

function cities(): array
{
    return ['Prishtinë', 'Prizren', 'Pejë', 'Gjakovë', 'Mitrovicë', 'Ferizaj', 'Gjilan', 'Vushtrri',
            'Podujevë', 'Suharekë', 'Rahovec', 'Fushë Kosovë', 'Lipjan', 'Drenas', 'Malishevë',
            'Kamenicë', 'Viti', 'Deçan', 'Istog', 'Klinë', 'Skenderaj', 'Dragash', 'Kaçanik', 'Obiliq', 'Shtime'];
}

function employment_types(): array
{
    return [
        'full_time'  => 'Me kohë të plotë',
        'part_time'  => 'Me kohë të pjesshme',
        'contract'   => 'Me kontratë',
        'internship' => 'Praktikë',
        'remote'     => 'Në distancë (Remote)',
    ];
}

function experience_levels(): array
{
    return ['entry' => 'Fillestar', 'mid' => 'Mesatar', 'senior' => 'I avancuar'];
}

function application_statuses(): array
{
    return [
        'pending'   => 'Në pritje',
        'reviewing' => 'Në shqyrtim',
        'accepted'  => 'Pranuar',
        'rejected'  => 'Refuzuar',
    ];
}

function job_statuses(): array
{
    return ['active' => 'Aktive', 'closed' => 'E mbyllur', 'blocked' => 'E bllokuar'];
}

function role_labels(): array
{
    return ['candidate' => 'Kandidat', 'employer' => 'Punëdhënës', 'admin' => 'Administrator'];
}

function label(array $list, ?string $key): string
{
    return $list[$key] ?? (string) $key;
}

function status_badge(string $status): string
{
    $labels = application_statuses() + job_statuses();
    return '<span class="badge badge-' . e($status) . '">' . e($labels[$status] ?? $status) . '</span>';
}

// ---------------------------------------------------------------------
// Punët publike
// ---------------------------------------------------------------------

/**
 * Kushti SQL për punët që shfaqen publikisht:
 * aktive, afati jo i kaluar, kompani aktive dhe punëdhënës aktiv.
 * Kërkon aliaset: j (jobs), c (companies), u (users i punëdhënësit).
 */
const PUBLIC_JOB_SQL = "j.status = 'active' AND j.deadline >= CURDATE() AND c.is_active = 1 AND u.is_active = 1";

function categories_list(PDO $pdo, bool $onlyActive = true): array
{
    $sql = 'SELECT id, name, slug, icon FROM categories' . ($onlyActive ? ' WHERE is_active = 1' : '') . ' ORDER BY name';
    return $pdo->query($sql)->fetchAll();
}

function logo_url(?string $logo): ?string
{
    return $logo ? url('uploads/logos/' . rawurlencode($logo)) : null;
}

/** HTML për logon e kompanisë ose inicialet. */
function company_logo(?string $logo, string $name, string $class = 'logo'): string
{
    if ($logo && is_file(LOGO_PATH . '/' . $logo)) {
        return '<img class="' . e($class) . '" src="' . e(logo_url($logo)) . '" alt="' . e($name) . '">';
    }
    return '<span class="' . e($class) . ' logo-fallback">' . e(initials($name)) . '</span>';
}

function saved_job_ids(PDO $pdo): array
{
    if (!has_role('candidate')) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT job_id FROM saved_jobs WHERE user_id = ?');
    $stmt->execute([current_user_id()]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

// ---------------------------------------------------------------------
// Njoftimet
// ---------------------------------------------------------------------

function notify(PDO $pdo, int $userId, string $type, string $title, string $message, ?string $link = null): void
{
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $type, mb_substr($title, 0, 150), mb_substr($message, 0, 500), $link]);
}

function notify_admins(PDO $pdo, string $type, string $title, string $message, ?string $link = null): void
{
    $ids = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        notify($pdo, (int) $id, $type, $title, $message, $link);
    }
}

function unread_notifications(PDO $pdo): int
{
    if (!is_logged_in()) {
        return 0;
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([current_user_id()]);
    return (int) $stmt->fetchColumn();
}

// ---------------------------------------------------------------------
// Ngarkimi i sigurt i file-ve
// ---------------------------------------------------------------------

/**
 * Valida dhe ruan file-in e ngarkuar.
 * @param array  $file    Elementi nga $_FILES
 * @param string $dir     Folderi i destinacionit
 * @param array  $allowed ['mime' => 'ext']
 * @return string Emri i ri i file-it
 * @throws RuntimeException me mesazh në shqip
 */
function upload_file(array $file, string $dir, array $allowed, int $maxSize): string
{
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Ngarkim i pavlefshëm.');
    }
    switch ($file['error']) {
        case UPLOAD_ERR_OK: break;
        case UPLOAD_ERR_NO_FILE: throw new RuntimeException('Nuk është zgjedhur asnjë file.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE: throw new RuntimeException('File është shumë i madh.');
        default: throw new RuntimeException('Ndodhi një gabim gjatë ngarkimit.');
    }
    if ($file['size'] > $maxSize) {
        throw new RuntimeException('File nuk duhet të kalojë ' . round($maxSize / 1048576, 1) . ' MB.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Ngarkim i pavlefshëm.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$mime]) || !in_array($ext, (array) $allowed[$mime], true)) {
        throw new RuntimeException('Lloji i file-it nuk lejohet.');
    }
    // Për PDF kontrollo edhe nënshkrimin "%PDF"
    if ($mime === 'application/pdf') {
        $fh = fopen($file['tmp_name'], 'rb');
        $magic = fread($fh, 5);
        fclose($fh);
        if (strpos($magic, '%PDF') !== 0) {
            throw new RuntimeException('File nuk është PDF valid.');
        }
    }
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Folderi i ngarkimeve nuk ekziston.');
    }
    $saveExt = is_array($allowed[$mime]) ? $allowed[$mime][0] : $allowed[$mime];
    $name = bin2hex(random_bytes(16)) . '.' . $saveExt;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('File nuk mund të ruhej.');
    }
    return $name;
}

function upload_cv(array $file): string
{
    return upload_file($file, CV_PATH, ['application/pdf' => ['pdf']], MAX_CV_SIZE);
}

function upload_logo(array $file): string
{
    return upload_file($file, LOGO_PATH, [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/webp' => ['webp'],
    ], MAX_LOGO_SIZE);
}

function delete_upload(string $dir, ?string $name): void
{
    if ($name && basename($name) === $name && is_file($dir . '/' . $name)) {
        @unlink($dir . '/' . $name);
    }
}

/** A përdoret një file CV nga ndonjë rresht tjetër (profili ose aplikimet)? */
function cv_in_use(PDO $pdo, string $file): bool
{
    $s = $pdo->prepare('SELECT (SELECT COUNT(*) FROM user_profiles WHERE cv_file = ?) + (SELECT COUNT(*) FROM applications WHERE cv_file = ?)');
    $s->execute([$file, $file]);
    return (int) $s->fetchColumn() > 0;
}

// ---------------------------------------------------------------------
// Faqosja (pagination)
// ---------------------------------------------------------------------

function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $pages));
    return ['total' => $total, 'pages' => $pages, 'page' => $page, 'offset' => ($page - 1) * $perPage, 'per_page' => $perPage];
}

/** Renderon linket e faqosjes duke ruajtur parametrat ekzistues të GET. */
function pagination_links(array $p): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $params = $_GET;
    $html = '<nav class="pagination" aria-label="Faqosja">';
    for ($i = 1; $i <= $p['pages']; $i++) {
        if ($p['pages'] > 7 && $i > 2 && $i < $p['pages'] - 1 && abs($i - $p['page']) > 1) {
            if ($i === 3 || $i === $p['pages'] - 2) {
                $html .= '<span class="dots">…</span>';
            }
            continue;
        }
        $params['page'] = $i;
        $cls = $i === $p['page'] ? ' class="active"' : '';
        $html .= '<a' . $cls . ' href="?' . e(http_build_query($params)) . '">' . $i . '</a>';
    }
    return $html . '</nav>';
}

/** Përqindja e plotësimit të profilit të kandidatit. */
function profile_completeness(PDO $pdo, int $userId): array
{
    $s = $pdo->prepare('SELECT * FROM user_profiles WHERE user_id = ?');
    $s->execute([$userId]);
    $p = $s->fetch() ?: [];
    $edu = $pdo->prepare('SELECT COUNT(*) FROM educations WHERE user_id = ?');
    $edu->execute([$userId]);
    $exp = $pdo->prepare('SELECT COUNT(*) FROM experiences WHERE user_id = ?');
    $exp->execute([$userId]);

    $checks = [
        'Telefoni'       => !empty($p['phone']),
        'Qyteti'         => !empty($p['city']),
        'Titulli'        => !empty($p['headline']),
        'Biografia'      => !empty($p['bio']),
        'Aftësitë'       => !empty($p['skills']),
        'CV (PDF)'       => !empty($p['cv_file']),
        'Edukimi'        => (int) $edu->fetchColumn() > 0,
        'Përvoja e punës'=> (int) $exp->fetchColumn() > 0,
    ];
    $done = count(array_filter($checks));
    return [
        'percent' => (int) round($done / count($checks) * 100),
        'missing' => array_keys(array_filter($checks, fn($v) => !$v)),
    ];
}
