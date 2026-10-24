<?php
/**
 * Menaxhimi i vendeve të punës (bllokim i përmbajtjes së papërshtatshme, fshirje)
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

if (is_post()) {
    verify_csrf();
    $id = input_int('id');
    $stmt = $pdo->prepare('SELECT j.id, j.title, j.status, c.user_id AS employer_id FROM jobs j JOIN companies c ON c.id = j.company_id WHERE j.id = ?');
    $stmt->execute([$id]);
    $job = $stmt->fetch();

    if (!$job) {
        flash('error', 'Shpallja nuk u gjet.');
    } elseif (input('action') === 'block') {
        $new = $job['status'] === 'blocked' ? 'active' : 'blocked';
        $pdo->prepare('UPDATE jobs SET status = ? WHERE id = ?')->execute([$new, $id]);
        notify($pdo, (int) $job['employer_id'], 'warning',
            $new === 'blocked' ? 'Shpallja u bllokua' : 'Shpallja u zhbllokua',
            $new === 'blocked' ? 'Shpallja "' . $job['title'] . '" u bllokua nga administratori për shkak të përmbajtjes së papërshtatshme.'
                               : 'Shpallja "' . $job['title'] . '" është sërish aktive.',
            '/employer/jobs.php');
        flash('success', $new === 'blocked' ? 'Shpallja u bllokua.' : 'Shpallja u zhbllokua.');
    } elseif (input('action') === 'delete') {
        $pdo->prepare('DELETE FROM jobs WHERE id = ?')->execute([$id]);
        notify($pdo, (int) $job['employer_id'], 'warning', 'Shpallja u fshi', 'Shpallja "' . $job['title'] . '" u fshi nga administratori.', '/employer/jobs.php');
        flash('success', 'Shpallja u fshi.');
    }
    redirect_back(url('admin/jobs.php'));
}

$q = input('q');
$status = input('status');
$where = ['1 = 1'];
$params = [];
if ($q !== '') {
    $where[] = '(j.title LIKE ? OR c.name LIKE ? OR j.city LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
if (isset(job_statuses()[$status])) {
    $where[] = 'j.status = ?';
    $params[] = $status;
}
$whereSql = implode(' AND ', $where);
$cnt = $pdo->prepare("SELECT COUNT(*) FROM jobs j JOIN companies c ON c.id = j.company_id WHERE $whereSql");
$cnt->execute($params);
$pg = paginate((int) $cnt->fetchColumn(), ADMIN_PER_PAGE, input_int('page', 1));

$stmt = $pdo->prepare("SELECT j.*, c.name AS company_name, cat.name AS category_name,
        (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) AS apps
    FROM jobs j JOIN companies c ON c.id = j.company_id LEFT JOIN categories cat ON cat.id = j.category_id
    WHERE $whereSql ORDER BY j.created_at DESC, j.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$stmt->execute($params);
$jobs = $stmt->fetchAll();

$pageTitle = 'Vendet e punës';
require ROOT_PATH . '/includes/header.php';
?>
<div class="container dash-layout">
    <?php require ROOT_PATH . '/includes/sidebar.php'; ?>
    <section class="dash-content">
        <div class="dash-title"><div><h1>Vendet e punës</h1><p>Gjithsej <?= $pg['total'] ?> shpallje.</p></div></div>
        <form class="toolbar" method="get">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Kërko titull, kompani ose qytet…">
            <select name="status"><option value="">Të gjitha statuset</option>
                <?php foreach (job_statuses() as $k => $lbl): ?><option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?></select>
            <button class="btn btn-primary" type="submit">Filtro</button>
        </form>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Shpallja</th><th>Kompania</th><th>Kategoria</th><th>Afati</th><th>Aplikime</th><th>Statusi</th><th>Veprime</th></tr></thead>
                <tbody>
                <?php if (!$jobs): ?><tr><td colspan="7" class="text-center muted">Nuk u gjet asnjë shpallje.</td></tr><?php endif; ?>
                <?php foreach ($jobs as $j): ?>
                    <tr>
                        <td><a href="<?= url('jobs/view.php?id=' . (int) $j['id']) ?>" target="_blank"><strong><?= e($j['title']) ?></strong></a><br><small>📍 <?= e($j['city']) ?> · <?= e(time_ago($j['created_at'])) ?></small></td>
                        <td><?= e($j['company_name']) ?></td>
                        <td><?= e($j['category_name'] ?? '-') ?></td>
                        <td><?= e(format_date($j['deadline'])) ?></td>
                        <td><?= (int) $j['apps'] ?></td>
                        <td><?= status_badge($j['status']) ?></td>
                        <td><div class="actions">
                            <form method="post" class="inline-form" <?= $j['status'] !== 'blocked' ? 'data-confirm="Të bllokohet kjo shpallje si e papërshtatshme?"' : '' ?>><?= csrf_field() ?>
                                <input type="hidden" name="action" value="block"><input type="hidden" name="id" value="<?= (int) $j['id'] ?>">
                                <button class="btn btn-outline btn-xs" type="submit"><?= $j['status'] === 'blocked' ? 'Zhblloko' : 'Blloko' ?></button></form>
                            <form method="post" class="inline-form" data-confirm="Të fshihet përgjithmonë shpallja?"><?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $j['id'] ?>">
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
