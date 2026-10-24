<?php
/**
 * Menaxhimi i përdoruesve (aktivizim/çaktivizim, fshirje)
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

if (is_post()) {
    verify_csrf();
    $id = input_int('id');
    $action = input('action');

    if ($id === current_user_id()) {
        flash('error', 'Nuk mund ta ndryshoni llogarinë tuaj nga kjo faqe.');
    } else {
        $stmt = $pdo->prepare('SELECT id, full_name, role, is_active FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) {
            flash('error', 'Përdoruesi nuk u gjet.');
        } elseif ($action === 'toggle') {
            $new = $user['is_active'] ? 0 : 1;
            $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$new, $id]);
            if ($new) {
                notify($pdo, $id, 'system', 'Llogaria u riaktivizua', 'Llogaria juaj në PunaKosova është aktive përsëri.', null);
            }
            flash('success', $user['full_name'] . ($new ? ' u aktivizua.' : ' u çaktivizua. Përmbajtja e tij nuk shfaqet më publikisht.'));
        } elseif ($action === 'delete') {
            // Ruaj file-t për fshirje pas fshirjes së rreshtave (CASCADE)
            $cvs = $pdo->prepare('SELECT cv_file FROM applications WHERE candidate_id = ? UNION SELECT cv_file FROM user_profiles WHERE user_id = ?');
            $cvs->execute([$id, $id]);
            $cvFiles = array_filter($cvs->fetchAll(PDO::FETCH_COLUMN));
            $logo = $pdo->prepare('SELECT logo FROM companies WHERE user_id = ?');
            $logo->execute([$id]);
            $logoFile = $logo->fetchColumn();

            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            foreach ($cvFiles as $f) {
                if (!cv_in_use($pdo, $f)) delete_upload(CV_PATH, $f);
            }
            if ($logoFile) delete_upload(LOGO_PATH, $logoFile);
            flash('success', 'Përdoruesi ' . $user['full_name'] . ' u fshi përgjithmonë.');
        }
    }
    redirect_back(url('admin/users.php'));
}

$q = input('q');
$role = input('role');
$state = input('state');
$where = ['1 = 1'];
$params = [];
if ($q !== '') {
    $where[] = '(u.full_name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if (isset(role_labels()[$role])) {
    $where[] = 'u.role = ?';
    $params[] = $role;
}
if ($state === 'active' || $state === 'inactive') {
    $where[] = 'u.is_active = ' . ($state === 'active' ? 1 : 0);
}
$whereSql = implode(' AND ', $where);

$cnt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $whereSql");
$cnt->execute($params);
$pg = paginate((int) $cnt->fetchColumn(), ADMIN_PER_PAGE, input_int('page', 1));

$stmt = $pdo->prepare("SELECT u.*, c.name AS company_name,
        (SELECT COUNT(*) FROM applications a WHERE a.candidate_id = u.id) AS apps
    FROM users u LEFT JOIN companies c ON c.user_id = u.id
    WHERE $whereSql ORDER BY u.created_at DESC, u.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Përdoruesit';
require ROOT_PATH . '/includes/header.php';
?>
<div class="container dash-layout">
    <?php require ROOT_PATH . '/includes/sidebar.php'; ?>
    <section class="dash-content">
        <div class="dash-title"><div><h1>Përdoruesit</h1><p>Gjithsej <?= $pg['total'] ?> përdorues.</p></div></div>
        <form class="toolbar" method="get">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Kërko emër ose email…">
            <select name="role"><option value="">Të gjitha rolet</option>
                <?php foreach (role_labels() as $k => $lbl): ?><option value="<?= e($k) ?>" <?= $role === $k ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?></select>
            <select name="state"><option value="">Çdo status</option>
                <option value="active" <?= $state === 'active' ? 'selected' : '' ?>>Aktiv</option>
                <option value="inactive" <?= $state === 'inactive' ? 'selected' : '' ?>>Joaktiv</option></select>
            <button class="btn btn-primary" type="submit">Filtro</button>
        </form>

        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Përdoruesi</th><th>Roli</th><th>Detaje</th><th>Regjistruar</th><th>Kyçja e fundit</th><th>Statusi</th><th>Veprime</th></tr></thead>
                <tbody>
                <?php if (!$users): ?><tr><td colspan="7" class="text-center muted">Nuk u gjet asnjë përdorues.</td></tr><?php endif; ?>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><div class="table-user"><span class="avatar-sm"><?= e(initials($u['full_name'])) ?></span>
                            <div><strong><?= e($u['full_name']) ?></strong><br><small><?= e($u['email']) ?></small></div></div></td>
                        <td><span class="badge badge-<?= e($u['role']) ?>"><?= e(label(role_labels(), $u['role'])) ?></span></td>
                        <td><small><?= $u['role'] === 'employer' ? '🏢 ' . e($u['company_name'] ?? 'Pa kompani') : ($u['role'] === 'candidate' ? (int) $u['apps'] . ' aplikime' : '-') ?></small></td>
                        <td><?= e(format_date($u['created_at'])) ?></td>
                        <td><?= e($u['last_login'] ? time_ago($u['last_login']) : 'Asnjëherë') ?></td>
                        <td><span class="badge badge-<?= $u['is_active'] ? 'yes' : 'no' ?>"><?= $u['is_active'] ? 'Aktiv' : 'Joaktiv' ?></span></td>
                        <td>
                            <?php if ((int) $u['id'] !== current_user_id()): ?>
                            <div class="actions">
                                <form method="post" class="inline-form" <?= $u['is_active'] ? 'data-confirm="Të çaktivizohet ky përdorues?"' : '' ?>><?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                    <button class="btn btn-outline btn-xs" type="submit"><?= $u['is_active'] ? 'Çaktivizo' : 'Aktivizo' ?></button></form>
                                <form method="post" class="inline-form" data-confirm="KUJDES: Të fshihet përgjithmonë <?= e($u['full_name']) ?> dhe të gjitha të dhënat e tij?"><?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                    <button class="btn btn-danger btn-xs" type="submit">Fshi</button></form>
                            </div>
                            <?php else: ?><small class="muted">(ju)</small><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination_links($pg) ?>
    </section>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
