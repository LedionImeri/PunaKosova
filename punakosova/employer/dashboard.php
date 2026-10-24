<?php
/**
 * Paneli i punëdhënësit
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('employer');
$company = employer_company($pdo);

$stats = ['active' => 0, 'jobs' => 0, 'apps' => 0, 'pending' => 0, 'accepted' => 0, 'views' => 0];
$recentApps = [];
$jobs = [];

if ($company) {
    $cid = (int) $company['id'];
    $s = $pdo->prepare("SELECT COUNT(*) AS jobs, COALESCE(SUM(status = 'active' AND deadline >= CURDATE()), 0) AS active, COALESCE(SUM(views), 0) AS views FROM jobs WHERE company_id = ?");
    $s->execute([$cid]);
    $stats = array_merge($stats, $s->fetch());

    $s = $pdo->prepare("SELECT COUNT(*) AS apps, COALESCE(SUM(a.status = 'pending'), 0) AS pending, COALESCE(SUM(a.status = 'accepted'), 0) AS accepted
        FROM applications a JOIN jobs j ON j.id = a.job_id WHERE j.company_id = ?");
    $s->execute([$cid]);
    $stats = array_merge($stats, $s->fetch());

    $s = $pdo->prepare('SELECT a.id, a.status, a.created_at, u.full_name, j.title
        FROM applications a JOIN jobs j ON j.id = a.job_id JOIN users u ON u.id = a.candidate_id
        WHERE j.company_id = ? ORDER BY a.created_at DESC LIMIT 6');
    $s->execute([$cid]);
    $recentApps = $s->fetchAll();

    $s = $pdo->prepare('SELECT j.id, j.title, j.status, j.deadline, j.views,
        (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) AS apps
        FROM jobs j WHERE j.company_id = ? ORDER BY j.created_at DESC LIMIT 5');
    $s->execute([$cid]);
    $jobs = $s->fetchAll();
}

$pageTitle = 'Paneli i punëdhënësit';
require ROOT_PATH . '/includes/header.php';
?>
<div class="container dash-layout">
    <?php require ROOT_PATH . '/includes/sidebar.php'; ?>
    <section class="dash-content">
        <div class="dash-title">
            <div><h1><?= e($company['name'] ?? 'Paneli') ?></h1><p>Menaxhoni shpalljet dhe aplikantët tuaj.</p></div>
            <a href="<?= url('employer/job_form.php') ?>" class="btn btn-primary">+ Publiko punë të re</a>
        </div>

        <?php if (!$company): ?>
            <div class="alert alert-warning"><span>Nuk keni profil kompanie. <a href="<?= url('employer/company.php') ?>"><strong>Krijojeni tani</strong></a> për të publikuar punë.</span></div>
        <?php elseif (!$company['is_active']): ?>
            <div class="alert alert-error"><span>Kompania juaj është çaktivizuar nga administratori. Shpalljet tuaja nuk shfaqen publikisht.</span></div>
        <?php elseif (!$company['description'] || !$company['phone']): ?>
            <div class="alert alert-info"><span>Plotësoni profilin e kompanisë (përshkrimi, telefoni, logoja) për të tërhequr më shumë kandidatë. <a href="<?= url('employer/company.php') ?>">Plotëso</a></span></div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat"><span class="stat-icon">📋</span><div><strong><?= (int) $stats['active'] ?></strong><span>Shpallje aktive</span></div></div>
            <div class="stat"><span class="stat-icon blue">📨</span><div><strong><?= (int) $stats['apps'] ?></strong><span>Aplikime gjithsej</span></div></div>
            <div class="stat"><span class="stat-icon yellow">⏳</span><div><strong><?= (int) $stats['pending'] ?></strong><span>Në pritje</span></div></div>
            <div class="stat"><span class="stat-icon green">✅</span><div><strong><?= (int) $stats['accepted'] ?></strong><span>Të pranuar</span></div></div>
            <div class="stat"><span class="stat-icon dark">👁</span><div><strong><?= (int) $stats['views'] ?></strong><span>Shikime</span></div></div>
        </div>

        <div class="grid grid-2">
            <div class="card">
                <div class="card-title"><h3>Aplikimet e fundit</h3><a href="<?= url('employer/applicants.php') ?>" class="btn btn-ghost btn-sm">Të gjitha</a></div>
                <?php if (!$recentApps): ?><p class="muted mb-0">Nuk keni aplikime ende.</p><?php endif; ?>
                <?php foreach ($recentApps as $a): ?>
                    <div class="list-item">
                        <div class="table-user"><span class="avatar-sm"><?= e(initials($a['full_name'])) ?></span>
                            <div><h4><a href="<?= url('employer/application.php?id=' . (int) $a['id']) ?>"><?= e($a['full_name']) ?></a></h4><small><?= e($a['title']) ?> · <?= e(time_ago($a['created_at'])) ?></small></div></div>
                        <?= status_badge($a['status']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="card">
                <div class="card-title"><h3>Shpalljet e fundit</h3><a href="<?= url('employer/jobs.php') ?>" class="btn btn-ghost btn-sm">Menaxho</a></div>
                <?php if (!$jobs): ?><p class="muted mb-0">Nuk keni publikuar asnjë punë.</p><?php endif; ?>
                <?php foreach ($jobs as $j): ?>
                    <div class="list-item">
                        <div><h4><a href="<?= url('jobs/view.php?id=' . (int) $j['id']) ?>"><?= e($j['title']) ?></a></h4>
                            <small><?= (int) $j['apps'] ?> aplikime · <?= (int) $j['views'] ?> shikime · afati <?= e(format_date($j['deadline'])) ?></small></div>
                        <?= status_badge($j['status']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
