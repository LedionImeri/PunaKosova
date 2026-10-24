<?php
/**
 * Ruaj / hiq një punë nga të preferuarat (POST, AJAX ose formë normale)
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if (!is_post()) {
    redirect(url('jobs/index.php'));
}
if (!has_role('candidate')) {
    if (is_ajax()) {
        json_response(['ok' => false, 'redirect' => url('login.php'), 'error' => 'Kyçuni si kandidat për të ruajtur punët.'], 401);
    }
    require_role('candidate');
}
verify_csrf();

$jobId = input_int('job_id');
$uid = current_user_id();

$exists = $pdo->prepare('SELECT id FROM jobs WHERE id = ?');
$exists->execute([$jobId]);
if (!$exists->fetch()) {
    is_ajax() ? json_response(['ok' => false, 'error' => 'Puna nuk ekziston.'], 404) : redirect(url('jobs/index.php'));
}

$stmt = $pdo->prepare('SELECT id FROM saved_jobs WHERE user_id = ? AND job_id = ?');
$stmt->execute([$uid, $jobId]);
if ($stmt->fetch()) {
    $pdo->prepare('DELETE FROM saved_jobs WHERE user_id = ? AND job_id = ?')->execute([$uid, $jobId]);
    $saved = false;
    $msg = 'Puna u hoq nga të ruajturat.';
} else {
    $pdo->prepare('INSERT INTO saved_jobs (user_id, job_id) VALUES (?, ?)')->execute([$uid, $jobId]);
    $saved = true;
    $msg = 'Puna u ruajt te të preferuarat.';
}

if (is_ajax()) {
    json_response(['ok' => true, 'saved' => $saved, 'message' => $msg]);
}
flash('success', $msg);
redirect_back(url('candidate/saved.php'));
