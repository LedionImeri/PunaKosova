<?php
/**
 * Paneli i kandidatit
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('candidate');
$uid = current_user_id();

// Statistikat e aplikimeve sipas statusit
$stmt = $pdo->prepare('SELECT status, COUNT(*) AS total FROM applications WHERE candidate_id = ? GROUP BY status');
$stmt->execute([$uid]);
$byStatus = array_column($stmt->fetchAll(), 'total', 'status');
$totalApps = array_sum($byStatus);

$saved = $pdo->prepare('SELECT COUNT(*) FROM saved_jobs WHERE user_id = ?');
$saved->execute([$uid]);
$savedCount = (int) $saved->fetchColumn();

$completeness = profile_completeness($pdo, $uid);

// Aplikimet e fundit
$stmt = $pdo->prepare('SELECT a.id, a.status, a.created_at, j.id AS job_id, j.title, c.name AS company_name, c.logo
    FROM applications a JOIN jobs j ON j.id = a.job_id JOIN companies c ON c.id = j.company_id
    WHERE a.candidate_id = ? ORDER BY a.created_at DESC LIMIT 5');
$stmt->execute([$uid]);
$recentApps = $stmt->fetchAll();

// Punë të rekomanduara: sipas qytetit të kandidatit ose kategorive ku ka aplikuar
$profile = $pdo->prepare('SELECT city, skills FROM user_profiles WHERE user_id = ?');
$profile->execute([$uid]);
$profile = $profile->fetch() ?: ['city' => null, 'skills' => null];

$rec = $pdo->prepare('SELECT j.*, c.name AS company_name, c.logo, cat.name AS category_name
    FROM jobs j JOIN companies c ON c.id = j.company_id JOIN users u ON u.id = c.user_id
    LEFT JOIN categories cat ON cat.id = j.category_id
    WHERE ' . PUBLIC_JOB_SQL . '
      AND j.id NOT IN (SELECT job_id FROM applications WHERE candidate_id = ?)
    ORDER BY (j.city = ?) DESC,
             (j.category_id IN (SELECT j2.category_id FROM applications a2 JOIN jobs j2 ON j2.id = a2.job_id WHERE a2.candidate_id = ?)) DESC,
             j.created_at DESC
    LIMIT 4');
$rec->execute([$uid, (string) $profile['city'], $uid]);
$recommended = $rec->fetchAll();
$savedIds = saved_job_ids($pdo);

$notifs = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 4');
$notifs->execute([$uid]);
$notifs = $notifs->fetchAll();

$pageTitle = 'Paneli i kandidatit';
require ROOT_PATH . '/includes/header.php';
?>
<div class="container dash-layout">
    <?php require ROOT_PATH . '/includes/sidebar.php'; ?>
    <section class="dash-content">
        <div class="dash-title">
            <div><h1>Përshëndetje, <?= e(explode(' ', $_SESSION['full_name'])[0]) ?> 👋</h1><p>Ja një përmbledhje e aktivitetit tuaj.</p></div>
            <a href="<?= url('jobs/index.php') ?>" class="btn btn-primary">🔍 Kërko punë</a>
        </div>

        <div class="stats">
            <div class="stat"><span class="stat-icon">📨</span><div><strong><?= $totalApps ?></strong><span>Aplikime gjithsej</span></div></div>
            <div class="stat"><span class="stat-icon yellow">⏳</span><div><strong><?= (int) ($byStatus['pending'] ?? 0) + (int) ($byStatus['reviewing'] ?? 0) ?></strong><span>Në proces</span></div></div>
            <div class="stat"><span class="stat-icon green">✅</span><div><strong><?= (int) ($byStatus['accepted'] ?? 0) ?></strong><span>Të pranuara</span></div></div>
            <div class="stat"><span class="stat-icon dark">❤️</span><div><strong><?= $savedCount ?></strong><span>Punë të ruajtura</span></div></div>
        </div>

        <div class="grid grid-2">
            <div class="card">
                <div class="card-title"><h3>Plotësimi i profilit</h3><strong><?= $completeness['percent'] ?>%</strong></div>
                <div class="progress mb-2"><div style="width:<?= $completeness['percent'] ?>%"></div></div>
                <?php if ($completeness['missing']): ?>
                    <p class="muted">Mungojnë: <?= e(implode(', ', $completeness['missing'])) ?>.</p>
                    <a href="<?= url('candidate/profile.php') ?>" class="btn btn-outline btn-sm">Plotëso profilin</a>
                <?php else: ?>
                    <p class="mb-0" style="color:var(--success)">🎉 Profili juaj është i plotë!</p>
                <?php endif; ?>
            </div>
            <div class="card">
                <div class="card-title"><h3>Njoftimet e fundit</h3><a href="<?= url('notifications.php') ?>" class="btn btn-ghost btn-sm">Të gjitha</a></div>
                <?php if (!$notifs): ?><p class="muted mb-0">Nuk keni njoftime.</p><?php endif; ?>
                <?php foreach ($notifs as $n): ?>
                    <div class="list-item"><div><h4 style="font-size:.9rem"><?= $n['is_read'] ? '' : '🔴 ' ?><?= e($n['title']) ?></h4><small><?= e(excerpt($n['message'], 70)) ?> · <?= e(time_ago($n['created_at'])) ?></small></div></div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-title"><h3>Aplikimet e fundit</h3><a href="<?= url('candidate/applications.php') ?>" class="btn btn-ghost btn-sm">Shiko të gjitha</a></div>
            <?php if (!$recentApps): ?>
                <div class="empty-state" style="padding:24px"><p>Nuk keni aplikuar ende për asnjë punë.</p><a href="<?= url('jobs/index.php') ?>" class="btn btn-primary btn-sm">Gjej punë</a></div>
            <?php endif; ?>
            <?php foreach ($recentApps as $a): ?>
                <div class="list-item">
                    <div class="flex"><?= company_logo($a['logo'], $a['company_name']) ?>
                        <div><h4><a href="<?= url('jobs/view.php?id=' . (int) $a['job_id']) ?>"><?= e($a['title']) ?></a></h4><small><?= e($a['company_name']) ?> · <?= e(format_date($a['created_at'])) ?></small></div></div>
                    <?= status_badge($a['status']) ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($recommended): ?>
            <h2 class="mt-3">Punë të rekomanduara për ju</h2>
            <div class="grid grid-2">
                <?php foreach ($recommended as $job) { require ROOT_PATH . '/includes/job_card.php'; } ?>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
