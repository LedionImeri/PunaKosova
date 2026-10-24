<?php
/**
 * Aplikimi online për një punë (vetëm kandidatët)
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('candidate');

$id = input_int('id');
$uid = current_user_id();

$stmt = $pdo->prepare('SELECT j.*, c.name AS company_name, c.logo, c.user_id AS employer_id
    FROM jobs j JOIN companies c ON c.id = j.company_id JOIN users u ON u.id = c.user_id
    WHERE j.id = ? AND ' . PUBLIC_JOB_SQL);
$stmt->execute([$id]);
$job = $stmt->fetch();
if (!$job) {
    flash('error', 'Kjo punë nuk ekziston ose nuk pranon më aplikime.');
    redirect(url('jobs/index.php'));
}

// A ka aplikuar më parë?
$chk = $pdo->prepare('SELECT id FROM applications WHERE job_id = ? AND candidate_id = ?');
$chk->execute([$id, $uid]);
if ($chk->fetch()) {
    flash('info', 'Ju keni aplikuar tashmë për këtë punë.');
    redirect(url('candidate/applications.php'));
}

$profile = $pdo->prepare('SELECT * FROM user_profiles WHERE user_id = ?');
$profile->execute([$uid]);
$profile = $profile->fetch() ?: [];
$hasProfileCv = !empty($profile['cv_file']) && is_file(CV_PATH . '/' . $profile['cv_file']);

$errors = [];
$cover = '';
if (is_post()) {
    verify_csrf();
    $cover = input('cover_letter');
    $cvOption = input('cv_option', $hasProfileCv ? 'profile' : 'new');

    if (str_len($cover) < 50) {
        $errors[] = 'Letra motivuese duhet të ketë së paku 50 karaktere.';
    } elseif (str_len($cover) > 5000) {
        $errors[] = 'Letra motivuese nuk duhet të kalojë 5000 karaktere.';
    }

    $cvFile = null;
    $cvOriginal = null;
    if (!$errors) {
        if ($cvOption === 'profile' && $hasProfileCv) {
            $cvFile = $profile['cv_file'];
            $cvOriginal = $profile['cv_original_name'];
        } else {
            try {
                $cvFile = upload_cv($_FILES['cv'] ?? []);
                $cvOriginal = mb_substr(basename($_FILES['cv']['name']), 0, 255);
                // Nëse kandidati s'ka CV në profil, ruaje edhe aty
                if (!$hasProfileCv && !empty($_POST['save_to_profile'])) {
                    $pdo->prepare('UPDATE user_profiles SET cv_file = ?, cv_original_name = ? WHERE user_id = ?')
                        ->execute([$cvFile, $cvOriginal, $uid]);
                }
            } catch (RuntimeException $ex) {
                $errors[] = 'CV: ' . $ex->getMessage();
            }
        }
    }

    if (!$errors) {
        try {
            $pdo->prepare('INSERT INTO applications (job_id, candidate_id, cover_letter, cv_file, cv_original_name) VALUES (?, ?, ?, ?, ?)')
                ->execute([$id, $uid, $cover, $cvFile, $cvOriginal]);
            $appId = (int) $pdo->lastInsertId();

            notify($pdo, (int) $job['employer_id'], 'application', 'Aplikim i ri',
                ($_SESSION['full_name'] ?? 'Një kandidat') . ' aplikoi për "' . $job['title'] . '".',
                '/employer/application.php?id=' . $appId);
            notify($pdo, $uid, 'application', 'Aplikimi u dërgua',
                'Aplikimi juaj për "' . $job['title'] . '" te ' . $job['company_name'] . ' u dërgua me sukses.',
                '/candidate/applications.php');

            flash('success', 'Aplikimi u dërgua me sukses! Do të njoftoheni kur statusi të ndryshojë.');
            redirect(url('candidate/applications.php'));
        } catch (PDOException $ex) {
            $errors[] = $ex->getCode() === '23000' ? 'Keni aplikuar tashmë për këtë punë.' : 'Aplikimi dështoi. Provoni përsëri.';
        }
    }
}

$pageTitle = 'Apliko: ' . $job['title'];
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div class="container">
        <div class="breadcrumb"><a href="<?= url('jobs/index.php') ?>">Punët</a> / <a href="<?= url('jobs/view.php?id=' . $id) ?>"><?= e($job['title']) ?></a> / Apliko</div>
        <h1>Apliko për: <?= e($job['title']) ?></h1>
        <p><?= e($job['company_name']) ?> · 📍 <?= e($job['city']) ?> · Afati: <?= e(format_date($job['deadline'])) ?></p>
    </div>
</div>
<div class="container job-detail">
    <div class="card">
        <?php if ($errors): ?>
            <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" data-validate novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">

            <h3>1. CV-ja juaj (PDF)</h3>
            <?php if ($hasProfileCv): ?>
                <div class="form-group">
                    <label class="checkbox"><input type="radio" name="cv_option" value="profile" checked>
                        Përdor CV-në nga profili: <a href="<?= url('download.php?type=profile') ?>" target="_blank"><?= e($profile['cv_original_name'] ?: 'CV.pdf') ?></a></label>
                    <label class="checkbox mt-2"><input type="radio" name="cv_option" value="new"> Ngarko një CV tjetër për këtë aplikim</label>
                </div>
            <?php else: ?>
                <input type="hidden" name="cv_option" value="new">
                <div class="alert alert-info"><span>Nuk keni CV në profil. Ngarkoni një CV në format PDF.</span></div>
            <?php endif; ?>
            <div class="form-group">
                <label class="file-drop">
                    <input type="file" name="cv" accept="application/pdf,.pdf" <?= $hasProfileCv ? '' : 'required' ?>>
                    📄 Kliko për të zgjedhur CV-në (PDF, maks. <?= MAX_CV_SIZE / 1048576 ?> MB)
                    <span class="file-name"></span>
                </label>
            </div>
            <?php if (!$hasProfileCv): ?>
                <div class="form-group"><label class="checkbox"><input type="checkbox" name="save_to_profile" value="1" checked> Ruaje këtë CV edhe në profilin tim</label></div>
            <?php endif; ?>

            <h3 class="mt-3">2. Letra motivuese</h3>
            <div class="form-group">
                <label for="cover_letter">Pse jeni kandidati i duhur për këtë pozitë? <span class="req">*</span></label>
                <textarea id="cover_letter" name="cover_letter" rows="9" maxlength="5000" minlength="50" required data-counter
                    placeholder="I/E nderuar..., jam i interesuar për pozitën..."><?= e($cover) ?></textarea>
                <span class="help">Minimumi 50 karaktere.</span>
            </div>

            <div class="flex">
                <button type="submit" class="btn btn-primary">📨 Dërgo aplikimin</button>
                <a href="<?= url('jobs/view.php?id=' . $id) ?>" class="btn btn-outline">Anulo</a>
            </div>
        </form>
    </div>
    <aside class="sticky-side">
        <div class="card">
            <div class="flex mb-2"><?= company_logo($job['logo'], $job['company_name']) ?><div><strong><?= e($job['title']) ?></strong><br><small><?= e($job['company_name']) ?></small></div></div>
            <ul class="info-list">
                <li><span>Paga</span><span><?= e(format_salary($job['salary_min'], $job['salary_max'])) ?></span></li>
                <li><span>Lloji</span><span><?= e(label(employment_types(), $job['employment_type'])) ?></span></li>
                <li><span>Afati</span><span><?= e(format_date($job['deadline'])) ?></span></li>
            </ul>
        </div>
        <div class="card">
            <h3>💡 Këshilla</h3>
            <p class="muted mb-0">Përshtateni letrën motivuese me kërkesat e pozitës dhe përmendni përvojën konkrete. Profili i plotë rrit shanset tuaja.</p>
        </div>
    </aside>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
