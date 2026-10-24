<?php
/**
 * Shpalljet e punëdhënësit: lista, mbyllja/rihapja dhe fshirja
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('employer');
$company = require_company($pdo);
$cid = (int) $company['id'];

if (is_post()) {
    verify_csrf();
    $jobId = input_int('id');
    // Punëdhënësi menaxhon VETËM shpalljet e kompanisë së vet
    $stmt = $pdo->prepare('SELECT id, title, status FROM jobs WHERE id = ? AND company_id = ?');
    $stmt->execute([$jobId, $cid]);
    $job = $stmt->fetch();

    if (!$job) {
        flash('error', 'Shpallja nuk u gjet.');
    } elseif (input('action') === 'delete') {
        // Njofto kandidatët që kishin aplikuar
        $c = $pdo->prepare('SELECT candidate_id FROM applications WHERE job_id = ?');
        $c->execute([$jobId]);
        foreach ($c->fetchAll(PDO::FETCH_COLUMN) as $candId) {
            notify($pdo, (int) $candId, 'job', 'Shpallja u hoq', 'Shpallja "' . $job['title'] . '" ku keni aplikuar është fshirë nga punëdhënësi.', '/candidate/applications.php');
        }
        $pdo->prepare('DELETE FROM jobs WHERE id = ? AND company_id = ?')->execute([$jobId, $cid]);
        flash('success', 'Shpallja u fshi.');
    } elseif (input('action') === 'toggle') {
        if ($job['status'] === 'blocked') {
            flash('error', 'Kjo shpallje është bllokuar nga administratori.');
        } else {
            $new = $job['status'] === 'active' ? 'closed' : 'active';
            $pdo->prepare('UPDATE jobs SET status = ? WHERE id = ? AND company_id = ?')->execute([$new, $jobId, $cid]);
            flash('success', $new === 'active' ? 'Shpallja u rihap.' : 'Shpallja u mbyll.');
        }
    }
    redirect(url('employer/jobs.php'));
}

$status = input('status');
$sql = 'SELECT j.*, cat.name AS category_name,
        (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) AS apps,
        (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status = \'pending\') AS pending
        FROM jobs j LEFT JOIN categories cat ON cat.id = j.category_id WHERE j.company_id = ?';
$params = [$cid];
if (isset(job_statuses()[$status])) {
    $sql .= ' AND j.status = ?';
    $params[] = $status;
}
$stmt = $pdo->prepare($sql . ' ORDER BY j.created_at DESC');
$stmt->execute($params);
$jobs = $stmt->fetchAll();

$pageTitle = 'Shpalljet e mia';
require ROOT_PATH . '/includes/header.php';
?>
<div class="container dash-layout">
    <?php require ROOT_PATH . '/includes/sidebar.php'; ?>
    <section class="dash-content">
        <div class="dash-title">
            <div><h1>Shpalljet e mia</h1><p>Menaxhoni vendet e punës të publikuara nga <?= e($company['name']) ?>.</p></div>
            <a href="<?= url('employer/job_form.php') ?>" class="btn btn-primary">+ Publiko punë</a>
        </div>
        <div class="tabs">
            <a href="?" class="<?= $status === '' ? 'active' : '' ?>">Të gjitha</a>
            <?php foreach (job_statuses() as $k => $lbl): ?><a href="?status=<?= e($k) ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= e($lbl) ?></a><?php endforeach; ?>
        </div>

        <?php if (!$jobs): ?>
            <div class="card empty-state"><span class="emoji">📋</span><h3>Nuk ka shpallje</h3><p>Publikoni vendin e parë të punës për të marrë aplikime.</p>
                <a href="<?= url('employer/job_form.php') ?>" class="btn btn-primary">Publiko punë</a></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Pozita</th><th>Qyteti</th><th>Afati</th><th>Aplikime</th><th>Shikime</th><th>Statusi</th><th>Veprime</th></tr></thead>
                    <tbody>
                    <?php foreach ($jobs as $j): $expired = $j['deadline'] < date('Y-m-d'); ?>
                        <tr>
                            <td><a href="<?= url('jobs/view.php?id=' . (int) $j['id']) ?>"><strong><?= e($j['title']) ?></strong></a><br><small><?= e($j['category_name'] ?? 'Pa kategori') ?> · <?= e(label(employment_types(), $j['employment_type'])) ?></small></td>
                            <td><?= e($j['city']) ?></td>
                            <td><?= e(format_date($j['deadline'])) ?><?= $expired ? '<br><small style="color:var(--danger)">Ka skaduar</small>' : '' ?></td>
                            <td><a href="<?= url('employer/applicants.php?job_id=' . (int) $j['id']) ?>"><strong><?= (int) $j['apps'] ?></strong></a><?= $j['pending'] ? ' <span class="badge badge-pending">' . (int) $j['pending'] . ' të reja</span>' : '' ?></td>
                            <td><?= (int) $j['views'] ?></td>
                            <td><?= status_badge($j['status']) ?></td>
                            <td><div class="actions">
                                <a class="btn btn-outline btn-xs" href="<?= url('employer/job_form.php?id=' . (int) $j['id']) ?>">Edito</a>
                                <a class="btn btn-outline btn-xs" href="<?= url('employer/applicants.php?job_id=' . (int) $j['id']) ?>">Aplikantët</a>
                                <?php if ($j['status'] !== 'blocked'): ?>
                                <form method="post" class="inline-form"><?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $j['id'] ?>">
                                    <button class="btn btn-outline btn-xs" type="submit"><?= $j['status'] === 'active' ? 'Mbyll' : 'Rihap' ?></button></form>
                                <?php endif; ?>
                                <form method="post" class="inline-form" data-confirm="Të fshihet shpallja &quot;<?= e($j['title']) ?>&quot;? Do të fshihen edhe aplikimet e saj."><?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $j['id'] ?>">
                                    <button class="btn btn-danger btn-xs" type="submit">Fshi</button></form>
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
