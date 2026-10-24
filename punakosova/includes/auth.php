<?php
/**
 * Autentikimi dhe autorizimi sipas roleve.
 */

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user_id(): ?int
{
    return is_logged_in() ? (int) $_SESSION['user_id'] : null;
}

function current_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

function current_user(): ?array
{
    return $GLOBALS['CURRENT_USER'] ?? null;
}

function has_role(string ...$roles): bool
{
    return is_logged_in() && in_array(current_role(), $roles, true);
}

/** Ngarkon përdoruesin nga databaza; nëse është fshirë/çaktivizuar e çkyç. */
function refresh_current_user(PDO $pdo): void
{
    $GLOBALS['CURRENT_USER'] = null;
    if (!is_logged_in()) {
        return;
    }
    $stmt = $pdo->prepare('SELECT id, full_name, email, role, is_active FROM users WHERE id = ?');
    $stmt->execute([current_user_id()]);
    $user = $stmt->fetch();

    if (!$user || !(int) $user['is_active']) {
        logout_user();
        session_start();
        flash('error', 'Llogaria juaj nuk është aktive. Kontaktoni administratorin.');
        return;
    }
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    $GLOBALS['CURRENT_USER'] = $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true); // parandalon session fixation
    $_SESSION['user_id']   = (int) $user['id'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    unset($_SESSION['csrf_token']);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        flash('warning', 'Ju lutem kyçuni për të vazhduar.');
        redirect(url('login.php'));
    }
}

/** Lejon hyrjen vetëm për rolet e dhëna. */
function require_role(string ...$roles): void
{
    require_login();
    if (!in_array(current_role(), $roles, true)) {
        http_response_code(403);
        flash('error', 'Nuk keni leje për të hyrë në këtë faqe.');
        redirect(dashboard_url());
    }
}

function dashboard_url(?string $role = null): string
{
    $role = $role ?? current_role();
    switch ($role) {
        case 'admin':     return url('admin/dashboard.php');
        case 'employer':  return url('employer/dashboard.php');
        case 'candidate': return url('candidate/dashboard.php');
        default:          return url('index.php');
    }
}

/** Kthen kompaninë e punëdhënësit të kyçur (ose null). */
function employer_company(PDO $pdo): ?array
{
    static $company = false;
    if ($company === false) {
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE user_id = ?');
        $stmt->execute([current_user_id()]);
        $company = $stmt->fetch() ?: null;
    }
    return $company;
}

/** Punëdhënësi duhet të ketë profil kompanie për të menaxhuar shpalljet. */
function require_company(PDO $pdo): array
{
    $company = employer_company($pdo);
    if (!$company) {
        flash('warning', 'Së pari plotësoni profilin e kompanisë.');
        redirect(url('employer/company.php'));
    }
    if (!(int) $company['is_active']) {
        flash('error', 'Kompania juaj është çaktivizuar nga administratori. Kontaktoni administratën.');
        redirect(url('employer/dashboard.php'));
    }
    return $company;
}
