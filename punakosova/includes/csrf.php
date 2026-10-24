<?php
/**
 * Mbrojtja nga CSRF (Cross-Site Request Forgery)
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Kontrollon token-in nga POST ose nga headeri X-CSRF-Token (për AJAX). */
function csrf_valid(): bool
{
    $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($token) && $token !== '' && hash_equals(csrf_token(), $token);
}

/** Ndalon kërkesën nëse token-i nuk është valid. */
function verify_csrf(): void
{
    if (!csrf_valid()) {
        if (is_ajax()) {
            json_response(['ok' => false, 'error' => 'Sesioni ka skaduar. Rifreskoni faqen.'], 419);
        }
        flash('error', 'Kërkesa nuk është valide (CSRF). Ju lutem provoni përsëri.');
        redirect($_SERVER['HTTP_REFERER'] ?? url('index.php'));
    }
}
