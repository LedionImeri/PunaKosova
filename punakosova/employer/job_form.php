<?php
/**
 * Publikimi dhe editimi i një vendi pune
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('employer');
$company = require_company($pdo);
$cid = (int) $company['id'];

$id = input_int('id');
$job = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM jobs WHERE id = ? AND company_id = ?');
    $stmt->execute([$id, $cid]);
    $job = $stmt->fetch();
    if (!$job) {
        flash('error', 'Shpallja nuk u gjet ose nuk ju takon.');
        redirect(url('employer/jobs.php'));
    }
}

$categories = categories_list($pdo);
$categoryIds = array_map('intval', array_column($categories, 'id'));
$errors = [];

if (is_post()) {
    verify_csrf();
    $d = [
        'title'            => input('title'),
        'category_id'      => input_int('category_id'),
        'city'             => input('city'),
        'employment_type'  => input('employment_type'),
        'experience_level' => input('experience_level'),
        'salary_min'       => input('salary_min'),
        'salary_max'       => input('salary_max'),
        'deadline'         => input('deadline'),
        'description'      => input('description'),
        'requirements'     => input('requirements'),
        'benefits'         => input('benefits'),
    ];

    if (str_len($d['title']) < 3 || str_len($d['title']) > 150) $errors[] = 'Titulli duhet të ketë 3-150 karaktere.';
    if (!in_array($d['category_id'], $categoryIds, true)) $errors[] = 'Zgjidhni kategorinë.';
    if (!in_array($d['city'], cities(), true)) $errors[] = 'Zgjidhni lokacionin.';
    if (!isset(employment_types()[$d['employment_type']])) $errors[] = 'Zgjidhni llojin e punësimit.';
    if (!isset(experience_levels()[$d['experience_level']])) $errors[] = 'Zgjidhni nivelin e përvojës.';
    foreach (['salary_min' => 'Paga minimale', 'salary_max' => 'Paga maksimale'] as $k => $lbl) {
        if ($d[$k] !== '' && (!is_numeric($d[$k]) || $d[$k] < 0 || $d[$k] > 100000)) $errors[] = "$lbl nuk është valide.";
    }
    if ($d['salary_min'] !== '' && $d['salary_max'] !== '' && (float) $d['salary_min'] > (float) $d['salary_max']) {
        $errors[] = 'Paga minimale nuk mund të jetë më e madhe se maksimalja.';
    }
    if (!valid_date($d['deadline'])) {
        $errors[] = 'Afati i aplikimit nuk është valid.';
    } elseif ($d['deadline'] < date('Y-m-d')) {
        $errors[] = 'Afati i aplikimit nuk mund të jetë në të kaluarën.';
    } elseif ($d['deadline'] > date('Y-m-d', strtotime('+6 months'))) {
        $errors[] = 'Afati maksimal është 6 muaj.';
    }
    if (str_len($d['description']) < 30) $errors[] = 'Përshkrimi duhet të ketë së paku 30 karaktere.';
    if (str_len($d['description']) > 10000 || str_len($d['requirements']) > 5000 || str_len($d['benefits']) > 5000) $errors[] = 'Teksti është shumë i gjatë.';

    if (!$errors) {
        $vals = [$d['category_id'], $d['title'], $d['description'], $d['requirements'] ?: null, $d['benefits'] ?: null,
                 $d['city'], $d['employment_type'], $d['experience_level'],
                 $d['salary_min'] !== '' ? $d['salary_min'] : null, $d['salary_max'] !== '' ? $d['salary_max'] : null, $d['deadline']];
        if ($job) {
            $pdo->prepare('UPDATE jobs SET category_id = ?, title = ?, description = ?, requirements = ?, benefits = ?, city = ?,
                employment_type = ?, experience_level = ?, salary_min = ?, salary_max = ?, deadline = ? WHERE id = ? AND company_id = ?')
                ->execute(array_merge($vals, [$job['id'], $cid]));
            flash('success', 'Shpallja u përditësua me sukses.');
            redirect(url('employer/jobs.php'));
        } else {
            $pdo->prepare('INSERT INTO jobs (category_id, title, description, requirements, benefits, city, employment_type, experience_level,
                salary_min, salary_max, deadline, company_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute(array_merge($vals, [$cid]));
            $newId = (int) $pdo->lastInsertId();
            notify_admins($pdo, 'job', 'Shpallje e re', $company['name'] . ' publikoi "' . $d['title'] . '".', '/jobs/view.php?id=' . $newId);
            flash('success', 'Shpallja u publikua me sukses!');
            redirect(url('jobs/view.php?id=' . $newId));
        }
    }
}

$v = is_post() ? $_POST : ($job ?? ['deadline' => date('Y-m-d', strtotime('+30 days')), 'city' => $company['city']]);
$val = fn($k) => e($v[$k] ?? '');
$salVal = fn($k) => isset($v[$k]) && $v[$k] !== null && $v[$k] !== '' ? e((string) (float) $v[$k]) : '';

$pageTitle = $job ? 'Edito shpalljen' : 'Publiko punë';
require ROOT_PATH . '/includes/header.php';
?>
<div class="container dash-layout">
    <?php require ROOT_PATH . '/includes/sidebar.php'; ?>
    <section class="dash-content">
        <div class="dash-title"><div><h1><?= $job ? 'Edito shpalljen' : 'Publiko një vend pune' ?></h1><p>Plotësoni detajet e pozitës. Fushat me <span style="color:var(--primary)">*</span> janë të detyrueshme.</p></div></div>
        <?php if ($job && $job['status'] === 'blocked'): ?>
            <div class="alert alert-error"><span>Kjo shpallje është bllokuar nga administratori dhe nuk shfaqet publikisht.</span></div>
        <?php endif; ?>
        <?php if ($errors): ?>
            <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="post" class="card" data-validate novalidate>
            <?= csrf_field() ?>
            <?php if ($job): ?><input type="hidden" name="id" value="<?= (int) $job['id'] ?>"><?php endif; ?>
            <div class="form-group"><label for="title">Titulli i pozitës <span class="req">*</span></label>
                <input type="text" id="title" name="title" value="<?= $val('title') ?>" required maxlength="150" placeholder="p.sh. Zhvillues PHP"></div>
            <div class="form-row-3">
                <div class="form-group"><label for="category_id">Kategoria <span class="req">*</span></label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Zgjidh</option>
                        <?php foreach ($categories as $cat): ?><option value="<?= (int) $cat['id'] ?>" <?= (int) ($v['category_id'] ?? 0) === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label for="city">Lokacioni <span class="req">*</span></label>
                    <select id="city" name="city" required>
                        <option value="">Zgjidh</option>
                        <?php foreach (cities() as $ct): ?><option value="<?= e($ct) ?>" <?= ($v['city'] ?? '') === $ct ? 'selected' : '' ?>><?= e($ct) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label for="deadline">Afati i aplikimit <span class="req">*</span></label>
                    <input type="date" id="deadline" name="deadline" value="<?= $val('deadline') ?>" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+6 months')) ?>" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label for="employment_type">Lloji i punësimit <span class="req">*</span></label>
                    <select id="employment_type" name="employment_type" required>
                        <?php foreach (employment_types() as $k => $lbl): ?><option value="<?= e($k) ?>" <?= ($v['employment_type'] ?? '') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label for="experience_level">Niveli i përvojës <span class="req">*</span></label>
                    <select id="experience_level" name="experience_level" required>
                        <?php foreach (experience_levels() as $k => $lbl): ?><option value="<?= e($k) ?>" <?= ($v['experience_level'] ?? '') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
                    </select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label for="salary_min">Paga minimale (€/muaj)</label>
                    <input type="number" id="salary_min" name="salary_min" value="<?= $salVal('salary_min') ?>" min="0" step="10"></div>
                <div class="form-group"><label for="salary_max">Paga maksimale (€/muaj)</label>
                    <input type="number" id="salary_max" name="salary_max" value="<?= $salVal('salary_max') ?>" min="0" step="10">
                    <span class="help">Lëreni bosh për "Me marrëveshje".</span></div>
            </div>
            <div class="form-group"><label for="description">Përshkrimi i punës <span class="req">*</span></label>
                <textarea id="description" name="description" rows="7" maxlength="10000" required data-counter><?= $val('description') ?></textarea></div>
            <div class="form-group"><label for="requirements">Kërkesat</label>
                <textarea id="requirements" name="requirements" rows="5" maxlength="5000" placeholder="- Diplomë në...&#10;- Përvojë..."><?= $val('requirements') ?></textarea></div>
            <div class="form-group"><label for="benefits">Përfitimet</label>
                <textarea id="benefits" name="benefits" rows="4" maxlength="5000" placeholder="- Sigurim shëndetësor&#10;- Trajnime"><?= $val('benefits') ?></textarea></div>
            <div class="flex">
                <button type="submit" class="btn btn-primary"><?= $job ? 'Ruaj ndryshimet' : '🚀 Publiko shpalljen' ?></button>
                <a href="<?= url('employer/jobs.php') ?>" class="btn btn-outline">Anulo</a>
            </div>
        </form>
    </section>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
