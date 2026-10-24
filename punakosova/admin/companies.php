<?php
/**
 * Menaxhimi i kompanive (verifikim, çaktivizim, fshirje)
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

if (is_post()) {
    verify_csrf();
    $id = input_int('id');
    $stmt = $pdo->prepare('SELECT id, name, user_id, logo, is_active, is_verified FROM companies WHERE id = ?');
    $stmt->execute([$id]);
    $co = $stmt->fetch();

    if (!$co) {
        flash('error', 'Kompania nuk u gjet.');
    } else {
        switch (input('action')) {
            case 'verify':
                $new = $co['is_verified'] ? 0 : 1;
                $pdo->prepare('UPDATE companies SET is_verified = ? WHERE id = ?')->execute([$new, $id]);
                if ($new) notify($pdo, (int) $co['user_id'], 'system', 'Kompania u verifikua', 'Kompania juaj ' . $co['name'] . ' tani shfaqet si e verifikuar.', '/employer/company.php');
                flash('success', $co['name'] . ($new ? ' u verifikua.' : ' nuk është më e verifikuar.'));
                break;
            case 'toggle':
                $new = $co['is_active'] ? 0 : 1;
                $pdo->prepare('UPDATE companies SET is_active = ? WHERE id = ?')->execute([$new, $id]);
                notify($pdo, (int) $co['user_id'], 'warning', $new ? 'Kompania u aktivizua' : 'Kompania u çaktivizua',
                    $new ? 'Shpalljet tuaja shfaqen sërish publikisht.' : 'Kompania juaj u çaktivizua nga administratori. Shpalljet nuk shfaqen publikisht.', '/employer/dashboard.php');
                flash('success', $co['name'] . ($new ? ' u aktivizua.' : ' u çaktivizua dhe shpalljet e saj u fshehën.'));
                break;
            case 'delete':
                $pdo->prepare('DELETE FROM companies WHERE id = ?')->execute([$id]); // CASCADE fshin punët dhe aplikimet
                delete_upload(LOGO_PATH, $co['logo']);
                notify($pdo, (int) $co['user_id'], 'warning', 'Profili i kompanisë u fshi', 'Profili i kompanisë dhe shpalljet u fshinë nga administratori.', '/employer/company.php');
                flash('success', 'Kompania ' . $co['name'] . ' u fshi bashkë me shpalljet e saj.');
                break;
        }
    }
    redirect_back(url('admin/companies.php'));
}

$q = input('q');
$params = [];
$where = '1 = 1';
if ($q !== '') {
    $where = '(c.name LIKE ? OR c.city LIKE ? OR u.email LIKE ?)';
    $params = ["%$q%", "%$q%", "%$q%"];
}
$cnt = $pdo->prepare("SELECT COUNT(*) FROM companies c JOIN users u ON u.id = c.user_id WHERE $where");
$cnt->execute($params);
$pg = paginate((int) $cnt->fetchColumn(), ADMIN_PER_PAGE, input_int('page', 1));

$stmt = $pdo->prepare("SELECT c.*, u.full_name AS owner, u.email AS owner_email, u.is_active AS owner_active,
        (SELECT COUNT(*) FROM jobs j WHERE j.company_id = c.id) AS jobs
    FROM companies c JOIN users u ON u.id = c.user_id WHERE $where
    ORDER BY c.created_at DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$stmt->execute($params);
$companies = $stmt->fetchAll();

$pageTitle = 'Kompanitë';
require ROOT_PATH . '/includes/header.php';
?>
<div class="container dash-layout">
    <?php require ROOT_PATH . '/includes/sidebar.php'; ?>
    <section class="dash-content">
        <div class="dash-title"><div><h1>Kompanitë</h1><p>Gjithsej <?= $pg['total'] ?> kompani.</p></div></div>
        <form class="toolbar" method="get">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Kërko kompani, qytet ose email…">
            <button class="btn btn-primary" type="submit">Kërko</button>
        </form>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Kompania</th><th>Pronari</th><th>Qyteti</th><th>Punë</th><th>Verifikuar</th><th>Statusi</th><th>Veprime</th></tr></thead>
                <tbody>
                <?php if (!$companies): ?><tr><td colspan="7" class="text-center muted">Nuk u gjet asnjë kompani.</td></tr><?php endif; ?>
                <?php foreach ($companies as $co): ?>
                    <tr>
                        <td><div class="table-user"><?= company_logo($co['logo'], $co['name']) ?>
                            <div><a href="<?= url('company.php?id=' . (int) $co['id']) ?>" target="_blank"><strong><?= e($co['name']) ?></strong></a><br><small><?= e($co['industry'] ?: '-') ?></small></div></div></td>
                        <td><?= e($co['owner']) ?><br><small><?= e($co['owner_email']) ?></small><?= $co['owner_active'] ? '' : '<br><span class="badge badge-no">Pronari joaktiv</span>' ?></td>
                        <td><?= e($co['city'] ?: '-') ?></td>
                        <td><?= (int) $co['jobs'] ?></td>
                        <td><span class="badge badge-<?= $co['is_verified'] ? 'yes' : 'neutral' ?>"><?= $co['is_verified'] ? '✔ Po' : 'Jo' ?></span></td>
                        <td><span class="badge badge-<?= $co['is_active'] ? 'yes' : 'no' ?>"><?= $co['is_active'] ? 'Aktive' : 'Joaktive' ?></span></td>
                        <td><div class="actions">
                            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="verify"><input type="hidden" name="id" value="<?= (int) $co['id'] ?>">
                                <button class="btn btn-outline btn-xs" type="submit"><?= $co['is_verified'] ? 'Hiq verifikimin' : 'Verifiko' ?></button></form>
                            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $co['id'] ?>">
                                <button class="btn btn-outline btn-xs" type="submit"><?= $co['is_active'] ? 'Çaktivizo' : 'Aktivizo' ?></button></form>
                            <form method="post" class="inline-form" data-confirm="Të fshihet kompania <?= e($co['name']) ?> me të gjitha shpalljet dhe aplikimet?"><?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $co['id'] ?>">
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
