<?php
/**
 * Paneli i administratorit me statistika
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$count = fn(string $sql) => (int) $pdo->query($sql)->fetchColumn();

$stats = [
    'candidates'   => $count("SELECT COUNT(*) FROM users WHERE role = 'candidate'"),
    'employers'    => $count("SELECT COUNT(*) FROM users WHERE role = 'employer'"),
    'companies'    => $count('SELECT COUNT(*) FROM companies'),
    'jobs_active'  => $count("SELECT COUNT(*) FROM jobs WHERE status = 'active' AND deadline >= CURDATE()"),
    'jobs_total'   => $count('SELECT COUNT(*) FROM jobs'),
    'jobs_blocked' => $count("SELECT COUNT(*) FROM jobs WHERE status = 'blocked'"),
    'applications' => $count('SELECT COUNT(*) FROM applications'),
    'inactive'     => $count('SELECT COUNT(*) FROM users WHERE is_active = 0'),
    'chat'         => $count("SELECT COUNT(*) FROM chatbot_messages WHERE sender = 'user'"),
    'new_week'     => $count('SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'),
];

$appStatus = array_column($pdo->query('SELECT status, COUNT(*) AS total FROM applications GROUP BY status')->fetchAll(), 'total', 'status');

$byCategory = $pdo->query('SELECT cat.name, COUNT(j.id) AS total FROM categories cat LEFT JOIN jobs j ON j.category_id = cat.id
    GROUP BY cat.id, cat.name ORDER BY total DESC, cat.name LIMIT 8')->fetchAll();
$maxCat = max(1, ...array_map('intval', array_column($byCategory, 'total') ?: [1]));

$byCity = $pdo->query("SELECT city, COUNT(*) AS total FROM jobs WHERE status = 'active' GROUP BY city ORDER BY total DESC LIMIT 6")->fetchAll();
$maxCity = max(1, ...array_map('intval', array_column($byCity, 'total') ?: [1]));

$recentUsers = $pdo->query('SELECT id, full_name, email, role, created_at FROM users ORDER BY created_at DESC, id DESC LIMIT 6')->fetchAll();
$recentJobs = $pdo->query('SELECT j.id, j.title, j.status, j.created_at, c.name AS company_name FROM jobs j JOIN companies c ON c.id = j.company_id ORDER BY j.created_at DESC, j.id DESC LIMIT 6')->fetchAll();

$pageTitle = 'Paneli i administratorit';
require ROOT_PATH . '/includes/header.php';
?>
<div class="container dash-layout">
    <?php require ROOT_PATH . '/includes/sidebar.php'; ?>
    <section class="dash-content">
        <div class="dash-title"><div><h1>Paneli i administratorit</h1><p>Pasqyrë e përgjithshme e platformës PunaKosova.</p></div></div>

        <div class="stats">
            <div class="stat"><span class="stat-icon blue">👤</span><div><strong><?= $stats['candidates'] ?></strong><span>Kandidatë</span></div></div>
            <div class="stat"><span class="stat-icon">🏢</span><div><strong><?= $stats['companies'] ?></strong><span>Kompani</span></div></div>
            <div class="stat"><span class="stat-icon green">📋</span><div><strong><?= $stats['jobs_active'] ?></strong><span>Punë aktive (nga <?= $stats['jobs_total'] ?>)</span></div></div>
            <div class="stat"><span class="stat-icon yellow">📨</span><div><strong><?= $stats['applications'] ?></strong><span>Aplikime</span></div></div>
            <div class="stat"><span class="stat-icon dark">🆕</span><div><strong><?= $stats['new_week'] ?></strong><span>Përdorues të rinj (7 ditë)</span></div></div>
            <div class="stat"><span class="stat-icon">🚫</span><div><strong><?= $stats['jobs_blocked'] + $stats['inactive'] ?></strong><span>Përmbajtje e bllokuar</span></div></div>
            <div class="stat"><span class="stat-icon blue">💬</span><div><strong><?= $stats['chat'] ?></strong><span>Pyetje në PunaBot</span></div></div>
            <div class="stat"><span class="stat-icon green">💼</span><div><strong><?= $stats['employers'] ?></strong><span>Punëdhënës</span></div></div>
        </div>

        <div class="grid grid-2">
            <div class="card">
                <h3>Punët sipas kategorive</h3>
                <div class="bar-chart">
                    <?php foreach ($byCategory as $row): ?>
                        <div class="bar-row"><span><?= e($row['name']) ?></span>
                            <div class="bar-track"><div class="bar-fill" style="width:<?= round($row['total'] / $maxCat * 100) ?>%"></div></div>
                            <strong><?= (int) $row['total'] ?></strong></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card">
                <h3>Aplikimet sipas statusit</h3>
                <?php $totalApps = max(1, array_sum($appStatus)); ?>
                <div class="bar-chart mb-3">
                    <?php foreach (application_statuses() as $k => $lbl): $n = (int) ($appStatus[$k] ?? 0); ?>
                        <div class="bar-row"><span><?= e($lbl) ?></span>
                            <div class="bar-track"><div class="bar-fill" style="width:<?= round($n / $totalApps * 100) ?>%;background:<?= ['pending' => '#d97706', 'reviewing' => '#2563eb', 'accepted' => '#16a34a', 'rejected' => '#dc2626'][$k] ?>"></div></div>
                            <strong><?= $n ?></strong></div>
                    <?php endforeach; ?>
                </div>
                <h3>Punë aktive sipas qytetit</h3>
                <div class="bar-chart">
                    <?php foreach ($byCity as $row): ?>
                        <div class="bar-row"><span><?= e($row['city']) ?></span>
                            <div class="bar-track"><div class="bar-fill" style="width:<?= round($row['total'] / $maxCity * 100) ?>%;background:var(--dark)"></div></div>
                            <strong><?= (int) $row['total'] ?></strong></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-2 mt-3">
            <div class="card">
                <div class="card-title"><h3>Përdoruesit e fundit</h3><a href="<?= url('admin/users.php') ?>" class="btn btn-ghost btn-sm">Menaxho</a></div>
                <?php foreach ($recentUsers as $u): ?>
                    <div class="list-item">
                        <div class="table-user"><span class="avatar-sm"><?= e(initials($u['full_name'])) ?></span>
                            <div><h4><?= e($u['full_name']) ?></h4><small><?= e($u['email']) ?></small></div></div>
                        <span class="badge badge-<?= e($u['role']) ?>"><?= e(label(role_labels(), $u['role'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="card">
                <div class="card-title"><h3>Shpalljet e fundit</h3><a href="<?= url('admin/jobs.php') ?>" class="btn btn-ghost btn-sm">Menaxho</a></div>
                <?php foreach ($recentJobs as $j): ?>
                    <div class="list-item">
                        <div><h4><a href="<?= url('jobs/view.php?id=' . (int) $j['id']) ?>"><?= e($j['title']) ?></a></h4><small><?= e($j['company_name']) ?> · <?= e(time_ago($j['created_at'])) ?></small></div>
                        <?= status_badge($j['status']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
