<?php
/**
 * Lista e aplikantëve për shpalljet e punëdhënësit
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('employer');
$company = require_company($pdo);
$cid = (int) $company['id'];

$jobId = input_int('job_id');
$status = input('status');
$q = input('q');

$where = ['j.company_id = ?'];
$params = [$cid];
if ($jobId) {
    $where[] = 'j.id = ?';
    $params[] = $jobId;
}
if (isset(application_statuses()[$status])) {
    $where[] = 'a.status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $where[] = '(u.full_name LIKE ? OR p.skills LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}

$stmt = $pdo->prepare('SELECT a.id, a.status, a.created_at, j.id AS job_id, j.title, u.full_name, u.email, p.city, p.headline, p.skills
    FROM applications a JOIN jobs j ON j.id = a.job_id JOIN users u ON u.id = a.candidate_id
    LEFT JOIN user_profiles p ON p.user_id = u.id
    WHERE ' . implode(' AND ', $where) . ' ORDER BY a.created_at DESC');
$stmt->execute($params);
$apps = $stmt->fetchAll();

$myJobs = $pdo->prepare('SELECT id, title FROM jobs WHERE company_id = ? ORDER BY created_at DESC');
$myJobs->execute([$cid]);
$myJobs = $myJobs->fetchAll();

$pageTitle = 'Aplikantët';
require ROOT_PATH . '/includes/header.php';
?>
<div class="container dash-layout">
    <?php require ROOT_PATH . '/includes/sidebar.php'; ?>
    <section class="dash-content">
        <div class="dash-title"><div><h1>Aplikantët</h1><p>Kandidatët që kanë aplikuar në shpalljet tuaja (<?= count($apps) ?>).</p></div></div>

        <form class="toolbar" method="get">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Kërko sipas emrit ose aftësive…">
            <select name="job_id">
                <option value="">Të gjitha shpalljet</option>
                <?php foreach ($myJobs as $mj): ?><option value="<?= (int) $mj['id'] ?>" <?= $jobId === (int) $mj['id'] ? 'selected' : '' ?>><?= e($mj['title']) ?></option><?php endforeach; ?>
            </select>
            <select name="status">
                <option value="">Të gjitha statuset</option>
                <?php foreach (application_statuses() as $k => $lbl): ?><option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-primary" type="submit">Filtro</button>
            <?php if ($jobId || $status || $q): ?><a href="<?= url('employer/applicants.php') ?>" class="btn btn-outline">Pastro</a><?php endif; ?>
        </form>

        <?php if (!$apps): ?>
            <div class="card empty-state"><span class="emoji">👥</span><h3>Nuk ka aplikantë</h3><p>Aplikimet e reja do të shfaqen këtu.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Kandidati</th><th>Pozita</th><th>Qyteti</th><th>Data</th><th>Statusi</th><th>Veprime</th></tr></thead>
                    <tbody>
                    <?php foreach ($apps as $a): ?>
                        <tr>
                            <td><div class="table-user"><span class="avatar-sm"><?= e(initials($a['full_name'])) ?></span>
                                <div><strong><?= e($a['full_name']) ?></strong><br><small><?= e($a['headline'] ?: $a['email']) ?></small></div></div></td>
                            <td><a href="<?= url('jobs/view.php?id=' . (int) $a['job_id']) ?>"><?= e($a['title']) ?></a></td>
                            <td><?= e($a['city'] ?: '-') ?></td>
                            <td><?= e(format_date($a['created_at'])) ?></td>
                            <td><?= status_badge($a['status']) ?></td>
                            <td><div class="actions">
                                <a class="btn btn-dark btn-xs" href="<?= url('employer/application.php?id=' . (int) $a['id']) ?>">Shqyrto</a>
                                <a class="btn btn-outline btn-xs" href="<?= url('download.php?type=application&id=' . (int) $a['id']) ?>" target="_blank">CV</a>
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
