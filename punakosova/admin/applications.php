<?php
/**
 * Shikimi dhe menaxhimi i të gjitha aplikimeve
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

if (is_post()) {
    verify_csrf();
    $id = input_int('id');
    $stmt = $pdo->prepare('SELECT a.id, a.status, a.candidate_id, a.cv_file, j.title FROM applications a JOIN jobs j ON j.id = a.job_id WHERE a.id = ?');
    $stmt->execute([$id]);
    $app = $stmt->fetch();

    if (!$app) {
        flash('error', 'Aplikimi nuk u gjet.');
    } elseif (input('action') === 'status') {
        $new = input('status');
        if (isset(application_statuses()[$new])) {
            $pdo->prepare('UPDATE applications SET status = ? WHERE id = ?')->execute([$new, $id]);
            if ($new !== $app['status']) {
                notify($pdo, (int) $app['candidate_id'], 'status', 'Statusi i aplikimit u ndryshua',
                    'Aplikimi juaj për "' . $app['title'] . '" tani është: ' . label(application_statuses(), $new) . '.', '/candidate/applications.php');
            }
            flash('success', 'Statusi u ndryshua.');
        }
    } elseif (input('action') === 'delete') {
        $pdo->prepare('DELETE FROM applications WHERE id = ?')->execute([$id]);
        if (!cv_in_use($pdo, $app['cv_file'])) delete_upload(CV_PATH, $app['cv_file']);
        flash('success', 'Aplikimi u fshi.');
    }
    redirect_back(url('admin/applications.php'));
}

$q = input('q');
$status = input('status');
$where = ['1 = 1'];
$params = [];
if ($q !== '') {
    $where[] = '(u.full_name LIKE ? OR j.title LIKE ? OR c.name LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
if (isset(application_statuses()[$status])) {
    $where[] = 'a.status = ?';
    $params[] = $status;
}
$whereSql = implode(' AND ', $where);
$from = "FROM applications a JOIN jobs j ON j.id = a.job_id JOIN companies c ON c.id = j.company_id JOIN users u ON u.id = a.candidate_id WHERE $whereSql";

$cnt = $pdo->prepare("SELECT COUNT(*) $from");
$cnt->execute($params);
$pg = paginate((int) $cnt->fetchColumn(), ADMIN_PER_PAGE, input_int('page', 1));

$stmt = $pdo->prepare("SELECT a.*, j.title, j.id AS job_id, c.name AS company_name, u.full_name, u.email $from
    ORDER BY a.created_at DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$stmt->execute($params);
$apps = $stmt->fetchAll();

$pageTitle = 'Aplikimet';
require ROOT_PATH . '/includes/header.php';
?>
<div class="container dash-layout">
    <?php require ROOT_PATH . '/includes/sidebar.php'; ?>
    <section class="dash-content">
        <div class="dash-title"><div><h1>Aplikimet</h1><p>Gjithsej <?= $pg['total'] ?> aplikime.</p></div></div>
        <form class="toolbar" method="get">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Kërko kandidat, punë ose kompani…">
            <select name="status"><option value="">Të gjitha statuset</option>
                <?php foreach (application_statuses() as $k => $lbl): ?><option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?></select>
            <button class="btn btn-primary" type="submit">Filtro</button>
        </form>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Kandidati</th><th>Puna</th><th>Data</th><th>Statusi</th><th>Ndrysho</th><th>Veprime</th></tr></thead>
                <tbody>
                <?php if (!$apps): ?><tr><td colspan="6" class="text-center muted">Nuk u gjet asnjë aplikim.</td></tr><?php endif; ?>
                <?php foreach ($apps as $a): ?>
                    <tr>
                        <td><strong><?= e($a['full_name']) ?></strong><br><small><?= e($a['email']) ?></small></td>
                        <td><a href="<?= url('jobs/view.php?id=' . (int) $a['job_id']) ?>" target="_blank"><?= e($a['title']) ?></a><br><small><?= e($a['company_name']) ?></small></td>
                        <td><?= e(format_date($a['created_at'])) ?></td>
                        <td><?= status_badge($a['status']) ?></td>
                        <td>
                            <form method="post" class="flex"><?= csrf_field() ?>
                                <input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                <select name="status" style="padding:5px 8px;width:auto" data-autosubmit>
                                    <?php foreach (application_statuses() as $k => $lbl): ?><option value="<?= e($k) ?>" <?= $a['status'] === $k ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
                                </select>
                                <noscript><button class="btn btn-outline btn-xs">OK</button></noscript>
                            </form>
                        </td>
                        <td><div class="actions">
                            <a class="btn btn-outline btn-xs" href="<?= url('download.php?type=application&id=' . (int) $a['id']) ?>" target="_blank">CV</a>
                            <details class="cover"><summary>Letra</summary><p><?= e($a['cover_letter']) ?></p></details>
                            <form method="post" class="inline-form" data-confirm="Të fshihet ky aplikim?"><?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                <button class="btn btn-danger btn-xs" type="submit">Fshi</button></form>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination_links($pg) ?>
    </section>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
