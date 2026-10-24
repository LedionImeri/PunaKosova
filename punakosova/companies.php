<?php
/**
 * Lista e kompanive
 */
require_once __DIR__ . '/includes/bootstrap.php';

$q = input('q');
$city = input('city');
$where = ['c.is_active = 1', 'u.is_active = 1'];
$params = [];
if ($q !== '') {
    $where[] = '(c.name LIKE ? OR c.industry LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($city !== '') {
    $where[] = 'c.city = ?';
    $params[] = $city;
}
$stmt = $pdo->prepare('
    SELECT c.*, (SELECT COUNT(*) FROM jobs j WHERE j.company_id = c.id AND j.status = \'active\' AND j.deadline >= CURDATE()) AS total
    FROM companies c JOIN users u ON u.id = c.user_id
    WHERE ' . implode(' AND ', $where) . ' ORDER BY total DESC, c.name');
$stmt->execute($params);
$companies = $stmt->fetchAll();

$pageTitle = 'Kompanitë';
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div class="container">
        <div class="breadcrumb"><a href="<?= url('index.php') ?>">Ballina</a> / Kompanitë</div>
        <h1>Kompanitë</h1>
        <p>Njihuni me punëdhënësit që publikojnë në PunaKosova.</p>
    </div>
</div>
<div class="container">
    <form class="toolbar card" method="get" style="padding:14px">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Kërko kompani ose industri…">
        <select name="city">
            <option value="">Të gjitha qytetet</option>
            <?php foreach (cities() as $c): ?><option value="<?= e($c) ?>" <?= $city === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-primary" type="submit">Kërko</button>
        <?php if ($q || $city): ?><a class="btn btn-outline" href="<?= url('companies.php') ?>">Pastro</a><?php endif; ?>
    </form>

    <?php if ($companies): ?>
        <div class="grid grid-4">
            <?php foreach ($companies as $co): ?>
                <a class="company-card" href="<?= url('company.php?id=' . (int) $co['id']) ?>">
                    <?= company_logo($co['logo'], $co['name'], 'logo-lg') ?>
                    <h3><?= e($co['name']) ?></h3>
                    <?php if ($co['is_verified']): ?><div class="verified">✔ E verifikuar</div><?php endif; ?>
                    <small><?= e($co['industry'] ?: 'Industri e papërcaktuar') ?></small><br>
                    <small>📍 <?= e($co['city'] ?: 'Kosovë') ?> · <strong><?= (int) $co['total'] ?></strong> punë aktive</small>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card empty-state"><span class="emoji">🏢</span><h3>Nuk u gjet asnjë kompani</h3><p>Provoni me kritere të tjera kërkimi.</p></div>
    <?php endif; ?>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
