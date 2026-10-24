<?php
/**
 * Ballina e PunaKosova
 */
require_once __DIR__ . '/includes/bootstrap.php';

$base = 'FROM jobs j JOIN companies c ON c.id = j.company_id JOIN users u ON u.id = c.user_id
         LEFT JOIN categories cat ON cat.id = j.category_id WHERE ' . PUBLIC_JOB_SQL;

// Statistikat
$stats = [
    'jobs'       => (int) $pdo->query('SELECT COUNT(*) ' . $base)->fetchColumn(),
    'companies'  => (int) $pdo->query('SELECT COUNT(*) FROM companies c JOIN users u ON u.id = c.user_id WHERE c.is_active = 1 AND u.is_active = 1')->fetchColumn(),
    'candidates' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'candidate' AND is_active = 1")->fetchColumn(),
];

// Punët më të reja
$latest = $pdo->query('SELECT j.*, c.name AS company_name, c.logo, cat.name AS category_name ' . $base . ' ORDER BY j.created_at DESC, j.id DESC LIMIT 6')->fetchAll();

// Kategoritë me numrin e punëve aktive
$categories = $pdo->query("
    SELECT cat.id, cat.name, cat.icon,
        (SELECT COUNT(*) FROM jobs j JOIN companies c ON c.id = j.company_id JOIN users u ON u.id = c.user_id
         WHERE j.category_id = cat.id AND " . PUBLIC_JOB_SQL . ") AS total
    FROM categories cat WHERE cat.is_active = 1 ORDER BY total DESC, cat.name LIMIT 8")->fetchAll();

// Kompanitë kryesore
$companies = $pdo->query("
    SELECT c.id, c.name, c.logo, c.city, c.is_verified,
        (SELECT COUNT(*) FROM jobs j WHERE j.company_id = c.id AND j.status = 'active' AND j.deadline >= CURDATE()) AS total
    FROM companies c JOIN users u ON u.id = c.user_id
    WHERE c.is_active = 1 AND u.is_active = 1 ORDER BY total DESC, c.name LIMIT 4")->fetchAll();

$savedIds = saved_job_ids($pdo);
$allCategories = categories_list($pdo);
$pageTitle = 'Gjej punën tënde në Kosovë';
require ROOT_PATH . '/includes/header.php';
?>

<section class="hero">
    <div class="container">
        <span class="hero-badge">🇽🇰 Platforma #1 për punësim në Kosovë</span>
        <h1>Gjej punën që të <span>përshtatet</span>, në qytetin tënd.</h1>
        <p class="lead">Mijëra kandidatë dhe kompani lidhen çdo ditë përmes PunaKosova. Kërko, apliko online me CV-në tënde dhe ndiq statusin e aplikimeve.</p>

        <form class="search-box" action="<?= url('jobs/index.php') ?>" method="get" role="search">
            <input type="search" name="q" placeholder="Pozita, fjalë kyçe ose kompania…" aria-label="Fjalë kyçe">
            <select name="city" aria-label="Qyteti">
                <option value="">Të gjitha qytetet</option>
                <?php foreach (cities() as $city): ?>
                    <option value="<?= e($city) ?>"><?= e($city) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="category" aria-label="Kategoria">
                <option value="">Të gjitha kategoritë</option>
                <?php foreach ($allCategories as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>"><?= e($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">🔍 Kërko</button>
        </form>

        <div class="hero-stats">
            <div><strong><?= $stats['jobs'] ?></strong><span>Vende pune aktive</span></div>
            <div><strong><?= $stats['companies'] ?></strong><span>Kompani</span></div>
            <div><strong><?= $stats['candidates'] ?></strong><span>Kandidatë</span></div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <h2>Kategoritë populare</h2>
                <p>Eksploro vendet e punës sipas fushës</p>
            </div>
            <a href="<?= url('jobs/index.php') ?>" class="btn btn-outline btn-sm">Të gjitha punët →</a>
        </div>
        <div class="grid grid-4">
            <?php foreach ($categories as $cat): ?>
                <a class="category-card" href="<?= url('jobs/index.php?category=' . (int) $cat['id']) ?>">
                    <span class="category-icon"><?= e($cat['icon'] ?: '💼') ?></span>
                    <span><strong><?= e($cat['name']) ?></strong><small><?= (int) $cat['total'] ?> punë aktive</small></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section" style="background:#fff">
    <div class="container">
        <div class="section-head">
            <div>
                <h2>Vendet më të reja të punës</h2>
                <p>Shpalljet e fundit nga punëdhënësit</p>
            </div>
            <a href="<?= url('jobs/index.php') ?>" class="btn btn-outline btn-sm">Shiko më shumë →</a>
        </div>
        <?php if ($latest): ?>
            <div class="grid grid-3">
                <?php foreach ($latest as $job) { require ROOT_PATH . '/includes/job_card.php'; } ?>
            </div>
        <?php else: ?>
            <div class="empty-state"><span class="emoji">📭</span><h3>Nuk ka punë aktive për momentin</h3></div>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head"><div><h2>Si funksionon?</h2><p>Tre hapa deri te puna e re</p></div></div>
        <div class="grid grid-3 steps">
            <div class="step"><h3>Krijo llogarinë</h3><p class="muted mb-0">Regjistrohu falas si kandidat, plotëso profilin dhe ngarko CV-në në PDF.</p></div>
            <div class="step"><h3>Kërko & apliko</h3><p class="muted mb-0">Filtro punët sipas qytetit, kategorisë dhe pagës, pastaj apliko me një letër motivuese.</p></div>
            <div class="step"><h3>Ndiq statusin</h3><p class="muted mb-0">Merr njoftime sa herë që punëdhënësi e shqyrton ose e pranon aplikimin tënd.</p></div>
        </div>
    </div>
</section>

<?php if ($companies): ?>
<section class="section" style="background:#fff">
    <div class="container">
        <div class="section-head">
            <div><h2>Kompanitë që punësojnë</h2><p>Punëdhënës të besueshëm në PunaKosova</p></div>
            <a href="<?= url('companies.php') ?>" class="btn btn-outline btn-sm">Të gjitha kompanitë →</a>
        </div>
        <div class="grid grid-4">
            <?php foreach ($companies as $co): ?>
                <a class="company-card" href="<?= url('company.php?id=' . (int) $co['id']) ?>">
                    <?= company_logo($co['logo'], $co['name'], 'logo-lg') ?>
                    <h3><?= e($co['name']) ?></h3>
                    <?php if ($co['is_verified']): ?><div class="verified">✔ E verifikuar</div><?php endif; ?>
                    <small><?= e($co['city'] ?: 'Kosovë') ?> · <?= (int) $co['total'] ?> punë aktive</small>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="container">
        <div class="cta">
            <div>
                <h2>Jeni punëdhënës?</h2>
                <p>Publikoni vende pune dhe gjeni kandidatët më të mirë në pak minuta.</p>
            </div>
            <a href="<?= has_role('employer') ? url('employer/job_form.php') : url('register.php?role=employer') ?>" class="btn btn-light">Publiko një punë</a>
        </div>
    </div>
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
