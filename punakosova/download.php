<?php
/**
 * Shkarkimi/shikimi i sigurt i CV-ve (PDF).
 * CV-të nuk janë të qasshme direkt (uploads/cv/.htaccess) – vetëm përmes këtij file-i.
 *
 *  ?type=profile            -> CV e profilit të kandidatit të kyçur
 *  ?type=application&id=X   -> CV e një aplikimi (kandidati i vet, punëdhënësi i punës, admini)
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$type = input('type');
$file = null;
$name = 'CV.pdf';

if ($type === 'profile' && has_role('candidate')) {
    $stmt = $pdo->prepare('SELECT cv_file, cv_original_name FROM user_profiles WHERE user_id = ?');
    $stmt->execute([current_user_id()]);
    $row = $stmt->fetch();
    if ($row) {
        $file = $row['cv_file'];
        $name = $row['cv_original_name'] ?: $name;
    }
} elseif ($type === 'application') {
    $stmt = $pdo->prepare('
        SELECT a.cv_file, a.cv_original_name, a.candidate_id, c.user_id AS employer_id
        FROM applications a JOIN jobs j ON j.id = a.job_id JOIN companies c ON c.id = j.company_id
        WHERE a.id = ?');
    $stmt->execute([input_int('id')]);
    $row = $stmt->fetch();
    // Kontrolli i autorizimit
    if ($row && (
        has_role('admin') ||
        (has_role('candidate') && (int) $row['candidate_id'] === current_user_id()) ||
        (has_role('employer') && (int) $row['employer_id'] === current_user_id())
    )) {
        $file = $row['cv_file'];
        $name = $row['cv_original_name'] ?: $name;
    }
}

$path = $file ? CV_PATH . '/' . basename($file) : null;
if (!$path || !is_file($path)) {
    http_response_code(404);
    flash('error', 'CV nuk u gjet ose nuk keni leje për ta parë.');
    redirect_back(dashboard_url());
}

$safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . (input('download') ? 'attachment' : 'inline') . '; filename="' . $safeName . '"');
header('Cache-Control: private, no-store');
readfile($path);
exit;
