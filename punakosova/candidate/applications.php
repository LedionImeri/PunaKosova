<?php
/**
 * Aplikimet e kandidatit dhe statusi i tyre
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('candidate');
$uid = current_user_id();

// Tërheqja e aplikimit (vetëm kur është "Në pritje")
if (is_post() && input('action') === 'withdraw') {
    verify_csrf();
    $stmt = $pdo->prepare('SELECT a.id, a.cv_file, j.title, c.user_id AS employer_id
        FROM applications a JOIN jobs j ON j.id = a.job_id JOIN companies c ON c.id = j.company_id
        WHERE a.id = ? AND a.candidate_id = ? AND a.status = \'pending\'');
    $stmt->execute([input_int('id'), $uid]);
    $app = $stmt->fetch();
    if ($app) {
        $pdo->prepare('DELETE FROM applications WHERE id = ? AND candidate_id = ?')->execute([$app['id'], $uid]);
        if (!cv_in_use($pdo, $app['cv_file'])) {
            delete_upload(CV_PATH, $app['cv_file']);
        }
        notify($pdo, (int) $app['employer_id'], 'application', 'Aplikim i tërhequr',
            $_SESSION['full_name'] . ' e tërhoqi aplikimin për "' . $app['title'] . '".', '/employer/applicants.php');
        flash('success', 'Aplikimi u tërhoq.');
    } else {
        flash('error', 'Vetëm aplikimet në pritje mund të tërhiqen.');
    }
    redirect(url('candidate/applications.php'));
}

$status = input('status');
$sql = 'SELECT a.*, j.id AS job_id, j.title, j.city, j.deadline, j.status AS job_status, c.name AS company_name, c.logo
        FROM applications a JOIN jobs j ON j.id = a.job_id JOIN companies c ON c.id = j.company_id
        WHERE a.candidate_id = ?';
$params = [$uid];
if (isset(application_statuses()[$status])) {
    $sql .= ' AND a.status = ?';
    $params[] = $status;
}
$stmt = $pdo->prepare($sql . ' ORDER BY a.created_at DESC');
$stmt->execute($params);
$apps = $stmt->fetchAll();

$pageTitle = 'Aplikimet e mia';
require ROOT_PATH . '/includes/header.php';
?>
<div class="container dash-layout">
    <?php require ROOT_PATH . '/includes/sidebar.php'; ?>
    <section class="dash-content">
        <div class="dash-title"><div><h1>Aplikimet e mia</h1><p>Ndiqni statusin e çdo aplikimi.</p></div></div>
        <div class="tabs">
            <a href="?" class="<?= $status === '' ? 'active' : '' ?>">Të gjitha</a>
            <?php foreach (application_statuses() as $k => $v): ?>
                <a href="?status=<?= e($k) ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= e($v) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (!$apps): ?>
            <div class="card empty-state"><span class="emoji">📭</span><h3>Nuk ka aplikime</h3><p>Kur të aplikoni për një punë, ajo do të shfaqet këtu.</p>
                <a href="<?= url('jobs/index.php') ?>" class="btn btn-primary">Kërko punë</a></div>
        <?php else: ?>
            <?php foreach ($apps as $a): ?>
                <div class="card">
                    <div class="flex-between">
                        <div class="flex">
                            <?= company_logo($a['logo'], $a['company_name']) ?>
                            <div>
                                <h3 class="mb-0"><a href="<?= url('jobs/view.php?id=' . (int) $a['job_id']) ?>"><?= e($a['title']) ?></a></h3>
                                <small><?= e($a['company_name']) ?> · 📍 <?= e($a['city']) ?> · Aplikuar më <?= e(format_date($a['created_at'], true)) ?></small>
                            </div>
                        </div>
                        <?= status_badge($a['status']) ?>
                    </div>
                    <?php if ($a['employer_note'] && in_array($a['status'], ['accepted', 'rejected', 'reviewing'], true)): ?>
                        <div class="alert alert-info mt-2"><span><strong>Mesazh nga punëdhënësi:</strong> <?= e($a['employer_note']) ?></span></div>
                    <?php endif; ?>
                    <div class="flex-between mt-2">
                        <details class="cover"><summary>Letra motivuese</summary><p><?= e($a['cover_letter']) ?></p></details>
                        <div class="flex">
                            <a href="<?= url('download.php?type=application&id=' . (int) $a['id']) ?>" target="_blank" class="btn btn-outline btn-sm">📄 CV e dërguar</a>
                            <?php if ($a['status'] === 'pending'): ?>
                                <form method="post" class="inline-form" data-confirm="Jeni i sigurt që doni ta tërhiqni këtë aplikim?">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="withdraw"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                    <button class="btn btn-danger btn-sm" type="submit">Tërhiq aplikimin</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
