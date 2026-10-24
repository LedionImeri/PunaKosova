<?php
/**
 * Dalja nga llogaria (vetëm POST me CSRF)
 */
require_once __DIR__ . '/includes/bootstrap.php';

if (!is_post()) {
    redirect(url('index.php'));
}
verify_csrf();
logout_user();
session_start();
flash('success', 'Dolët me sukses nga llogaria.');
redirect(url('login.php'));
