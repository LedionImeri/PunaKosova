<?php
/**
 * Profili publik i një kompanie
 */
require_once __DIR__ . '/includes/bootstrap.php';

$stmt = $pdo->prepare('SELECT c.* FROM companies c JOIN users u ON u.id = c.user_id WHERE c.id = ? AND c.is_active = 1 AND u.is_active = 1');
$stmt->execute([input_int('id')]);
$company = $stmt->fetch();
if (!$company) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$stmt = $pdo->prepare('SELECT j.*, c.name AS company_name, c.logo, cat.name AS category_name
    FROM jobs j JOIN companies c ON c.id = j.company_id JOIN users u ON u.id = c.user_id
    LEFT JOIN categories cat ON cat.id = j.category_id
    WHERE c.id = ? AND ' . PUBLIC_JOB_SQL . ' ORDER BY j.created_at DESC');
$stmt->execute([$company['id']]);
$jobs = $stmt->fetchAll();
$savedIds = saved_job_ids($pdo);

$pageTitle = $company['name'];
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div class="container">
        <div class="breadcrumb"><a href="<?= url('index.php') ?>">Ballina</a> / <a href="<?= url('companies.php') ?>">Kompanitë</a> / <?= e($company['name']) ?></div>
        <div class="profile-head">
            <?= company_logo($company['logo'], $company['name'], 'logo-lg') ?>
            <div>
                <h1><?= e($company['name']) ?></h1>
                <p><?= e($company['industry'] ?: '') ?><?= $company['city'] ? ' · 📍 ' . e($company['city']) : '' ?>
                   <?= $company['is_verified'] ? ' · <span style="color:#93c5fd">✔ E verifikuar</span>' : '' ?></p>
            </div>
        </div>
    </div>
</div>
<div class="container job-detail">
    <div>
        <div class="card content-block">
            <h3>Rreth kompanisë</h3>
            <p><?= $company['description'] ? nl2br_e($company['description']) : '<span class="muted">Kompania nuk ka shtuar përshkrim.</span>' ?></p>
        </div>
        <div class="card">
            <div class="card-title"><h3>Vendet e hapura të punës (<?= count($jobs) ?>)</h3></div>
            <?php if ($jobs): ?>
                <div class="grid grid-2">
                    <?php foreach ($jobs as $job) { require ROOT_PATH . '/includes/job_card.php'; } ?>
                </div>
            <?php else: ?>
                <p class="muted mb-0">Kjo kompani nuk ka shpallje aktive për momentin.</p>
            <?php endif; ?>
        </div>
    </div>
    <aside class="sticky-side">
        <div class="card">
            <h3>Informata kontakti</h3>
            <ul class="info-list">
                <li><span>Qyteti</span><span><?= e($company['city'] ?: '-') ?></span></li>
                <li><span>Adresa</span><span><?= e($company['address'] ?: '-') ?></span></li>
                <li><span>Telefoni</span><span><?= e($company['phone'] ?: '-') ?></span></li>
                <li><span>Email</span><span><?= e($company['email'] ?: '-') ?></span></li>
                <li><span>Punëtorë</span><span><?= e($company['employees'] ?: '-') ?></span></li>
                <li><span>Website</span><span>
                    <?php if ($company['website']): ?>
                        <a href="<?= e($company['website']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e(parse_url($company['website'], PHP_URL_HOST) ?: $company['website']) ?></a>
                    <?php else: ?>-<?php endif; ?>
                </span></li>
            </ul>
        </div>
    </aside>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
