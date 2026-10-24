<?php
/**
 * Kërkimi dhe filtrimi i vendeve të punës
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$f = [
    'q'          => input('q'),
    'city'       => input('city'),
    'category'   => input_int('category'),
    'type'       => input('type'),
    'level'      => input('level'),
    'salary_min' => input_int('salary_min'),
    'sort'       => input('sort', 'newest'),
];

$where = [PUBLIC_JOB_SQL];
$params = [];

if ($f['q'] !== '') {
    $where[] = '(j.title LIKE ? OR j.description LIKE ? OR j.requirements LIKE ? OR c.name LIKE ?)';
    $like = '%' . $f['q'] . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($f['city'] !== '') {
    $where[] = 'j.city = ?';
    $params[] = $f['city'];
}
if ($f['category'] > 0) {
    $where[] = 'j.category_id = ?';
    $params[] = $f['category'];
}
if (isset(employment_types()[$f['type']])) {
    $where[] = 'j.employment_type = ?';
    $params[] = $f['type'];
}
if (isset(experience_levels()[$f['level']])) {
    $where[] = 'j.experience_level = ?';
    $params[] = $f['level'];
}
if ($f['salary_min'] > 0) {
    $where[] = 'COALESCE(j.salary_max, j.salary_min) >= ?';
    $params[] = $f['salary_min'];
}

$sorts = [
    'newest'   => 'j.created_at DESC, j.id DESC',
    'deadline' => 'j.deadline ASC',
    'salary'   => 'COALESCE(j.salary_max, j.salary_min, 0) DESC',
    'popular'  => 'j.views DESC',
];
$orderBy = $sorts[$f['sort']] ?? $sorts['newest'];

$from = 'FROM jobs j JOIN companies c ON c.id = j.company_id JOIN users u ON u.id = c.user_id
         LEFT JOIN categories cat ON cat.id = j.category_id WHERE ' . implode(' AND ', $where);

$count = $pdo->prepare('SELECT COUNT(*) ' . $from);
$count->execute($params);
$pg = paginate((int) $count->fetchColumn(), JOBS_PER_PAGE, input_int('page', 1));

$stmt = $pdo->prepare('SELECT j.*, c.name AS company_name, c.logo, cat.name AS category_name ' . $from .
    " ORDER BY $orderBy LIMIT " . (int) $pg['per_page'] . ' OFFSET ' . (int) $pg['offset']);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

$categories = categories_list($pdo);
$savedIds = saved_job_ids($pdo);
$hasFilters = $f['q'] !== '' || $f['city'] !== '' || $f['category'] || $f['type'] !== '' || $f['level'] !== '' || $f['salary_min'];

$pageTitle = 'Vendet e punës';
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div class="container">
        <div class="breadcrumb"><a href="<?= url('index.php') ?>">Ballina</a> / Punët</div>
        <h1>Vendet e punës në Kosovë</h1>
        <p>U gjetën <strong><?= $pg['total'] ?></strong> vende pune aktive<?= $hasFilters ? ' sipas filtrave tuaj' : '' ?>.</p>
    </div>
</div>

<div class="container jobs-layout">
    <aside>
        <button type="button" class="btn btn-outline btn-block filter-toggle mb-2" data-filter-toggle>⚙️ Filtrat</button>
        <form class="card filters" method="get" action="">
            <h3>Filtro punët</h3>
            <div class="form-group">
                <label for="q">Fjalë kyçe</label>
                <input type="search" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="p.sh. PHP, shitës…">
            </div>
            <div class="form-group">
                <label for="city">Qyteti</label>
                <select id="city" name="city">
                    <option value="">Të gjitha</option>
                    <?php foreach (cities() as $c): ?><option value="<?= e($c) ?>" <?= $f['city'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="category">Kategoria</label>
                <select id="category" name="category">
                    <option value="">Të gjitha</option>
                    <?php foreach ($categories as $cat): ?><option value="<?= (int) $cat['id'] ?>" <?= $f['category'] === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="type">Lloji i punësimit</label>
                <select id="type" name="type">
                    <option value="">Të gjitha</option>
                    <?php foreach (employment_types() as $k => $v): ?><option value="<?= e($k) ?>" <?= $f['type'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="level">Niveli i përvojës</label>
                <select id="level" name="level">
                    <option value="">Të gjitha</option>
                    <?php foreach (experience_levels() as $k => $v): ?><option value="<?= e($k) ?>" <?= $f['level'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="salary_min">Paga minimale (€)</label>
                <input type="number" id="salary_min" name="salary_min" min="0" step="50" value="<?= $f['salary_min'] ?: '' ?>" placeholder="p.sh. 500">
            </div>
            <input type="hidden" name="sort" value="<?= e($f['sort']) ?>">
            <button type="submit" class="btn btn-primary btn-block">Apliko filtrat</button>
            <?php if ($hasFilters): ?>
                <a href="<?= url('jobs/index.php') ?>" class="btn btn-ghost btn-block mt-2">Pastro filtrat</a>
            <?php endif; ?>
        </form>
    </aside>

    <section>
        <div class="results-bar">
            <span class="muted">Duke shfaqur <?= count($jobs) ?> nga <?= $pg['total'] ?> rezultate</span>
            <form method="get" class="flex">
                <?php foreach ($f as $k => $v): if ($k === 'sort' || $v === '' || $v === 0) continue; ?>
                    <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
                <?php endforeach; ?>
                <label for="sort" class="mb-0 muted" style="font-weight:500">Rendit:</label>
                <select id="sort" name="sort" data-autosubmit>
                    <option value="newest"   <?= $f['sort'] === 'newest' ? 'selected' : '' ?>>Më të rejat</option>
                    <option value="deadline" <?= $f['sort'] === 'deadline' ? 'selected' : '' ?>>Afati më i afërt</option>
                    <option value="salary"   <?= $f['sort'] === 'salary' ? 'selected' : '' ?>>Paga më e lartë</option>
                    <option value="popular"  <?= $f['sort'] === 'popular' ? 'selected' : '' ?>>Më të shikuarat</option>
                </select>
                <noscript><button class="btn btn-sm btn-outline">OK</button></noscript>
            </form>
        </div>

        <?php if ($jobs): ?>
            <div class="grid grid-2">
                <?php foreach ($jobs as $job) { require ROOT_PATH . '/includes/job_card.php'; } ?>
            </div>
            <?= pagination_links($pg) ?>
        <?php else: ?>
            <div class="card empty-state">
                <span class="emoji">🔍</span>
                <h3>Nuk u gjet asnjë punë</h3>
                <p>Provoni të ndryshoni ose të pastroni filtrat e kërkimit.</p>
                <a href="<?= url('jobs/index.php') ?>" class="btn btn-primary">Shiko të gjitha punët</a>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
