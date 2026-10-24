<?php
/**
 * Detajet e një vendi pune
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$id = input_int('id');
$stmt = $pdo->prepare('
    SELECT j.*, c.id AS company_id, c.name AS company_name, c.logo, c.description AS company_description,
           c.city AS company_city, c.website, c.is_verified, c.user_id AS owner_id, c.is_active AS company_active,
           u.is_active AS owner_active, cat.name AS category_name
    FROM jobs j JOIN companies c ON c.id = j.company_id JOIN users u ON u.id = c.user_id
    LEFT JOIN categories cat ON cat.id = j.category_id
    WHERE j.id = ?');
$stmt->execute([$id]);
$job = $stmt->fetch();

$isOwner = $job && has_role('employer') && (int) $job['owner_id'] === current_user_id();
$isPublic = $job && $job['status'] === 'active' && $job['company_active'] && $job['owner_active'] && $job['deadline'] >= date('Y-m-d');

// Punët jo-publike i shohin vetëm pronari dhe admini
if (!$job || (!$isPublic && !$isOwner && !has_role('admin'))) {
    // Kandidati që ka aplikuar mund ta shohë ende shpalljen (p.sh. afati ka kaluar)
    $allowed = false;
    if ($job && has_role('candidate') && $job['status'] !== 'blocked') {
        $chk = $pdo->prepare('SELECT 1 FROM applications WHERE job_id = ? AND candidate_id = ?');
        $chk->execute([$job['id'], current_user_id()]);
        $allowed = (bool) $chk->fetchColumn();
    }
    if (!$allowed) {
        http_response_code(404);
        require ROOT_PATH . '/404.php';
        exit;
    }
}

// Rrit numrin e shikimeve një herë për sesion
if (empty($_SESSION['viewed_jobs'][$id]) && !$isOwner) {
    $pdo->prepare('UPDATE jobs SET views = views + 1 WHERE id = ?')->execute([$id]);
    $_SESSION['viewed_jobs'][$id] = true;
}

$application = null;
$isSaved = false;
if (has_role('candidate')) {
    $s = $pdo->prepare('SELECT id, status, created_at FROM applications WHERE job_id = ? AND candidate_id = ?');
    $s->execute([$id, current_user_id()]);
    $application = $s->fetch();
    $isSaved = in_array($id, saved_job_ids($pdo), true);
}

$appCount = $pdo->prepare('SELECT COUNT(*) FROM applications WHERE job_id = ?');
$appCount->execute([$id]);
$appCount = (int) $appCount->fetchColumn();

// Punë të ngjashme
$sim = $pdo->prepare('SELECT j.*, c.name AS company_name, c.logo, cat.name AS category_name
    FROM jobs j JOIN companies c ON c.id = j.company_id JOIN users u ON u.id = c.user_id
    LEFT JOIN categories cat ON cat.id = j.category_id
    WHERE ' . PUBLIC_JOB_SQL . ' AND j.id <> ? AND (j.category_id = ? OR j.city = ?)
    ORDER BY (j.category_id = ?) DESC, j.created_at DESC LIMIT 3');
$sim->execute([$id, $job['category_id'], $job['city'], $job['category_id']]);
$similar = $sim->fetchAll();
$savedIds = saved_job_ids($pdo);
$left = days_left($job['deadline']);

$pageTitle = $job['title'];
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div class="container">
        <div class="breadcrumb"><a href="<?= url('index.php') ?>">Ballina</a> / <a href="<?= url('jobs/index.php') ?>">Punët</a> / <?= e($job['title']) ?></div>
        <div class="job-header">
            <?= company_logo($job['logo'], $job['company_name'], 'logo-lg') ?>
            <div>
                <h1><?= e($job['title']) ?></h1>
                <p><a href="<?= url('company.php?id=' . (int) $job['company_id']) ?>" style="color:#fca5a5"><?= e($job['company_name']) ?></a>
                   · 📍 <?= e($job['city']) ?> · Publikuar <?= e(time_ago($job['created_at'])) ?></p>
            </div>
        </div>
    </div>
</div>

<div class="container job-detail">
    <div>
        <?php if (!$isPublic): ?>
            <div class="alert alert-warning"><span>
                Kjo shpallje nuk është publike për momentin
                (statusi: <?= e(label(job_statuses(), $job['status'])) ?><?= $left < 0 ? ', afati ka kaluar' : '' ?>).
            </span></div>
        <?php endif; ?>
        <div class="card content-block">
            <div class="job-meta mb-3">
                <span class="chip chip-red"><?= e(label(employment_types(), $job['employment_type'])) ?></span>
                <span class="chip"><?= e(label(experience_levels(), $job['experience_level'])) ?></span>
                <?php if ($job['category_name']): ?><span class="chip"><?= e($job['category_name']) ?></span><?php endif; ?>
                <span class="chip">👁 <?= (int) $job['views'] ?> shikime</span>
            </div>
            <h3>Përshkrimi i punës</h3>
            <p><?= nl2br_e($job['description']) ?></p>
            <?php if ($job['requirements']): ?>
                <h3>Kërkesat</h3>
                <p><?= nl2br_e($job['requirements']) ?></p>
            <?php endif; ?>
            <?php if ($job['benefits']): ?>
                <h3>Çfarë ofrojmë</h3>
                <p><?= nl2br_e($job['benefits']) ?></p>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-title"><h3>Rreth <?= e($job['company_name']) ?></h3>
                <?php if ($job['is_verified']): ?><span class="verified">✔ E verifikuar</span><?php endif; ?></div>
            <p><?= $job['company_description'] ? nl2br_e(excerpt($job['company_description'], 400)) : '<span class="muted">Pa përshkrim.</span>' ?></p>
            <a href="<?= url('company.php?id=' . (int) $job['company_id']) ?>" class="btn btn-outline btn-sm">Shiko profilin e kompanisë</a>
        </div>
    </div>

    <aside class="sticky-side">
        <div class="card">
            <ul class="info-list">
                <li><span>Paga</span><span><?= e(format_salary($job['salary_min'], $job['salary_max'])) ?></span></li>
                <li><span>Lokacioni</span><span><?= e($job['city']) ?></span></li>
                <li><span>Lloji</span><span><?= e(label(employment_types(), $job['employment_type'])) ?></span></li>
                <li><span>Përvoja</span><span><?= e(label(experience_levels(), $job['experience_level'])) ?></span></li>
                <li><span>Afati</span><span class="<?= $left <= 5 ? 'deadline urgent' : '' ?>"><?= e(format_date($job['deadline'])) ?></span></li>
                <li><span>Aplikime</span><span><?= $appCount ?></span></li>
            </ul>
            <hr style="margin:16px 0">

            <?php if (has_role('candidate')): ?>
                <?php if ($application): ?>
                    <div class="alert alert-info"><span>Keni aplikuar më <?= e(format_date($application['created_at'])) ?>. Statusi: <?= status_badge($application['status']) ?></span></div>
                    <a href="<?= url('candidate/applications.php') ?>" class="btn btn-outline btn-block">Shiko aplikimet e mia</a>
                <?php elseif ($isPublic): ?>
                    <a href="<?= url('jobs/apply.php?id=' . $id) ?>" class="btn btn-primary btn-block">Apliko tani</a>
                <?php else: ?>
                    <button class="btn btn-primary btn-block" disabled>Aplikimet janë mbyllur</button>
                <?php endif; ?>
                <form action="<?= url('jobs/save.php') ?>" method="post" data-save-job data-job="<?= $id ?>" class="mt-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="job_id" value="<?= $id ?>">
                    <button type="submit" class="save-btn save-wide<?= $isSaved ? ' saved' : '' ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                        <span class="save-label"><?= $isSaved ? 'E ruajtur' : 'Ruaje' ?></span>
                    </button>
                </form>
            <?php elseif ($isOwner): ?>
                <a href="<?= url('employer/job_form.php?id=' . $id) ?>" class="btn btn-dark btn-block">✏️ Edito shpalljen</a>
                <a href="<?= url('employer/applicants.php?job_id=' . $id) ?>" class="btn btn-outline btn-block mt-2">👥 Shiko aplikantët (<?= $appCount ?>)</a>
            <?php elseif (has_role('admin')): ?>
                <a href="<?= url('admin/jobs.php?q=' . urlencode($job['title'])) ?>" class="btn btn-dark btn-block">Menaxho në admin</a>
            <?php elseif (has_role('employer')): ?>
                <p class="muted mb-0 text-center">Vetëm kandidatët mund të aplikojnë.</p>
            <?php else: ?>
                <a href="<?= url('jobs/apply.php?id=' . $id) ?>" class="btn btn-primary btn-block">Kyçu për të aplikuar</a>
                <p class="text-center mt-2 mb-0"><small>Nuk ke llogari? <a href="<?= url('register.php') ?>">Regjistrohu</a></small></p>
            <?php endif; ?>
        </div>
    </aside>
</div>

<?php if ($similar): ?>
<section class="section">
    <div class="container">
        <h2>Punë të ngjashme</h2>
        <div class="grid grid-3">
            <?php foreach ($similar as $job) { require ROOT_PATH . '/includes/job_card.php'; } ?>
        </div>
    </div>
</section>
<?php endif; ?>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
