<?php
/**
 * Detajet e një aplikimi: profili i kandidatit, CV dhe ndryshimi i statusit
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('employer');
$company = require_company($pdo);
$cid = (int) $company['id'];
$id = input_int('id');

// Punëdhënësi sheh VETËM aplikimet për punët e kompanisë së vet
$stmt = $pdo->prepare('SELECT a.*, j.title, j.id AS job_id, u.full_name, u.email, p.phone, p.city, p.birth_date, p.headline, p.bio, p.skills
    FROM applications a JOIN jobs j ON j.id = a.job_id JOIN users u ON u.id = a.candidate_id
    LEFT JOIN user_profiles p ON p.user_id = u.id
    WHERE a.id = ? AND j.company_id = ?');
$stmt->execute([$id, $cid]);
$app = $stmt->fetch();
if (!$app) {
    flash('error', 'Aplikimi nuk u gjet.');
    redirect(url('employer/applicants.php'));
}

if (is_post()) {
    verify_csrf();
    $newStatus = input('status');
    $note = input('employer_note');
    if (!isset(application_statuses()[$newStatus])) {
        flash('error', 'Statusi nuk është valid.');
    } elseif (str_len($note) > 1000) {
        flash('error', 'Mesazhi nuk duhet të kalojë 1000 karaktere.');
    } else {
        $pdo->prepare('UPDATE applications SET status = ?, employer_note = ? WHERE id = ?')->execute([$newStatus, $note ?: null, $id]);
        if ($newStatus !== $app['status'] || $note !== (string) $app['employer_note']) {
            notify($pdo, (int) $app['candidate_id'], 'status', 'Statusi i aplikimit u ndryshua',
                'Aplikimi juaj për "' . $app['title'] . '" te ' . $company['name'] . ' tani është: ' . label(application_statuses(), $newStatus) . '.',
                '/candidate/applications.php');
        }
        flash('success', 'Statusi u përditësua dhe kandidati u njoftua.');
    }
    redirect(url('employer/application.php?id=' . $id . '&keep=1'));
}

// Kalimi automatik nga "Në pritje" në "Në shqyrtim" kur punëdhënësi e hap aplikimin
if ($app['status'] === 'pending' && input('keep') !== '1') {
    $pdo->prepare("UPDATE applications SET status = 'reviewing' WHERE id = ? AND status = 'pending'")->execute([$id]);
    notify($pdo, (int) $app['candidate_id'], 'status', 'Aplikimi juaj po shqyrtohet',
        $company['name'] . ' po e shqyrton aplikimin tuaj për "' . $app['title'] . '".', '/candidate/applications.php');
    $app['status'] = 'reviewing';
}

$edus = $pdo->prepare('SELECT * FROM educations WHERE user_id = ? ORDER BY start_year DESC');
$edus->execute([$app['candidate_id']]);
$edus = $edus->fetchAll();
$exps = $pdo->prepare('SELECT * FROM experiences WHERE user_id = ? ORDER BY is_current DESC, start_date DESC');
$exps->execute([$app['candidate_id']]);
$exps = $exps->fetchAll();

$pageTitle = 'Aplikimi: ' . $app['full_name'];
require ROOT_PATH . '/includes/header.php';
?>
<div class="container dash-layout">
    <?php require ROOT_PATH . '/includes/sidebar.php'; ?>
    <section class="dash-content">
        <div class="dash-title">
            <div><h1><?= e($app['full_name']) ?></h1><p>Aplikim për <a href="<?= url('jobs/view.php?id=' . (int) $app['job_id']) ?>"><?= e($app['title']) ?></a> · <?= e(format_date($app['created_at'], true)) ?></p></div>
            <a href="<?= url('employer/applicants.php?job_id=' . (int) $app['job_id']) ?>" class="btn btn-outline">← Kthehu</a>
        </div>

        <div class="job-detail">
            <div>
                <div class="card">
                    <div class="profile-head mb-2">
                        <span class="avatar avatar-lg"><?= e(initials($app['full_name'])) ?></span>
                        <div><h2 class="mb-0"><?= e($app['full_name']) ?></h2><p class="muted mb-0"><?= e($app['headline'] ?: 'Kandidat') ?></p></div>
                    </div>
                    <dl class="kv">
                        <dt>Email</dt><dd><a href="mailto:<?= e($app['email']) ?>"><?= e($app['email']) ?></a></dd>
                        <dt>Telefoni</dt><dd><?= e($app['phone'] ?: '-') ?></dd>
                        <dt>Qyteti</dt><dd><?= e($app['city'] ?: '-') ?></dd>
                        <dt>Data e lindjes</dt><dd><?= e(format_date($app['birth_date'])) ?></dd>
                    </dl>
                    <?php if ($app['bio']): ?><h3 class="mt-3">Biografia</h3><p><?= nl2br_e($app['bio']) ?></p><?php endif; ?>
                    <?php if ($app['skills']): ?>
                        <h3 class="mt-3">Aftësitë</h3>
                        <div class="skills"><?php foreach (explode(',', $app['skills']) as $s): ?><span class="chip chip-red"><?= e(trim($s)) ?></span><?php endforeach; ?></div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h3>Letra motivuese</h3>
                    <p style="white-space:pre-line"><?= e($app['cover_letter']) ?></p>
                </div>

                <div class="card">
                    <h3>Përvoja e punës</h3>
                    <?php if (!$exps): ?><p class="muted">Pa përvojë të shënuar.</p><?php endif; ?>
                    <?php foreach ($exps as $ex): ?>
                        <div class="timeline-item"><h4><?= e($ex['position']) ?> – <?= e($ex['company_name']) ?></h4>
                            <small><?= e(format_date($ex['start_date'])) ?> – <?= $ex['is_current'] ? 'Aktualisht' : e(format_date($ex['end_date'])) ?></small>
                            <?php if ($ex['description']): ?><p class="muted mb-0"><?= nl2br_e($ex['description']) ?></p><?php endif; ?></div>
                    <?php endforeach; ?>
                    <h3 class="mt-3">Edukimi</h3>
                    <?php if (!$edus): ?><p class="muted mb-0">Pa edukim të shënuar.</p><?php endif; ?>
                    <?php foreach ($edus as $ed): ?>
                        <div class="timeline-item"><h4><?= e($ed['degree']) ?><?= $ed['field_of_study'] ? ' – ' . e($ed['field_of_study']) : '' ?></h4>
                            <small><?= e($ed['institution']) ?> · <?= (int) $ed['start_year'] ?> – <?= $ed['end_year'] ? (int) $ed['end_year'] : 'në vazhdim' ?></small></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <aside class="sticky-side">
                <div class="card">
                    <h3>CV e kandidatit</h3>
                    <p class="muted"><?= e($app['cv_original_name'] ?: 'CV.pdf') ?></p>
                    <div class="flex">
                        <a href="<?= url('download.php?type=application&id=' . $id) ?>" target="_blank" class="btn btn-dark btn-sm">👁 Shiko CV</a>
                        <a href="<?= url('download.php?type=application&id=' . $id . '&download=1') ?>" class="btn btn-outline btn-sm">⬇ Shkarko</a>
                    </div>
                </div>
                <div class="card">
                    <h3>Statusi i aplikimit</h3>
                    <p>Aktualisht: <?= status_badge($app['status']) ?></p>
                    <form method="post">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label for="status">Ndrysho statusin</label>
                            <select id="status" name="status">
                                <?php foreach (application_statuses() as $k => $lbl): ?><option value="<?= e($k) ?>" <?= $app['status'] === $k ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="employer_note">Mesazh për kandidatin (opsional)</label>
                            <textarea id="employer_note" name="employer_note" rows="4" maxlength="1000" placeholder="p.sh. Ju ftojmë në intervistë më..."><?= e($app['employer_note']) ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Ruaj dhe njofto kandidatin</button>
                    </form>
                </div>
            </aside>
        </div>
    </section>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
