<?php
/**
 * Punët e ruajtura (të preferuarat) të kandidatit
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('candidate');
$uid = current_user_id();

if (is_post() && input('action') === 'remove') {
    verify_csrf();
    $pdo->prepare('DELETE FROM saved_jobs WHERE user_id = ? AND job_id = ?')->execute([$uid, input_int('job_id')]);
    flash('success', 'Puna u hoq nga lista.');
    redirect(url('candidate/saved.php'));
}

$stmt = $pdo->prepare('SELECT s.created_at AS saved_at, j.*, c.name AS company_name, c.logo, cat.name AS category_name,
        (j.status = \'active\' AND j.deadline >= CURDATE() AND c.is_active = 1) AS is_open,
        (SELECT a.status FROM applications a WHERE a.job_id = j.id AND a.candidate_id = s.user_id) AS app_status
    FROM saved_jobs s JOIN jobs j ON j.id = s.job_id JOIN companies c ON c.id = j.company_id
    LEFT JOIN categories cat ON cat.id = j.category_id
    WHERE s.user_id = ? AND j.status <> \'blocked\' ORDER BY s.created_at DESC');
$stmt->execute([$uid]);
$jobs = $stmt->fetchAll();

$pageTitle = 'Punët e ruajtura';
require ROOT_PATH . '/includes/header.php';
?>
<div class="container dash-layout">
    <?php require ROOT_PATH . '/includes/sidebar.php'; ?>
    <section class="dash-content">
        <div class="dash-title"><div><h1>Punët e ruajtura</h1><p>Punët që keni shënuar si të preferuara.</p></div></div>
        <?php if (!$jobs): ?>
            <div class="card empty-state"><span class="emoji">❤️</span><h3>Nuk keni punë të ruajtura</h3><p>Klikoni zemrën te çdo punë për ta ruajtur këtu.</p>
                <a href="<?= url('jobs/index.php') ?>" class="btn btn-primary">Shfleto punët</a></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Puna</th><th>Qyteti</th><th>Paga</th><th>Afati</th><th>Statusi</th><th>Veprime</th></tr></thead>
                    <tbody>
                    <?php foreach ($jobs as $j): ?>
                        <tr>
                            <td><div class="table-user"><?= company_logo($j['logo'], $j['company_name']) ?>
                                <div><a href="<?= url('jobs/view.php?id=' . (int) $j['id']) ?>"><strong><?= e($j['title']) ?></strong></a><br><small><?= e($j['company_name']) ?></small></div></div></td>
                            <td><?= e($j['city']) ?></td>
                            <td><?= e(format_salary($j['salary_min'], $j['salary_max'])) ?></td>
                            <td><?= e(format_date($j['deadline'])) ?></td>
                            <td><?= $j['app_status'] ? status_badge($j['app_status']) : ($j['is_open'] ? '<span class="badge badge-active">E hapur</span>' : '<span class="badge badge-closed">E mbyllur</span>') ?></td>
                            <td><div class="actions">
                                <?php if ($j['is_open'] && !$j['app_status']): ?>
                                    <a class="btn btn-primary btn-xs" href="<?= url('jobs/apply.php?id=' . (int) $j['id']) ?>">Apliko</a>
                                <?php endif; ?>
                                <form method="post" class="inline-form"><?= csrf_field() ?>
                                    <input type="hidden" name="action" value="remove"><input type="hidden" name="job_id" value="<?= (int) $j['id'] ?>">
                                    <button class="btn btn-danger btn-xs" type="submit">Hiq</button></form>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
