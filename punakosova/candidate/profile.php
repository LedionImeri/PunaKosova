<?php
/**
 * Profili i kandidatit: të dhënat personale, CV, edukimi dhe përvoja e punës
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('candidate');
$uid = current_user_id();

// Sigurohu që rreshti i profilit ekziston
$pdo->prepare('INSERT IGNORE INTO user_profiles (user_id) VALUES (?)')->execute([$uid]);

$tab = in_array(input('tab'), ['personal', 'cv', 'education', 'experience'], true) ? input('tab') : 'personal';
$errors = [];

if (is_post()) {
    verify_csrf();
    $action = input('action');

    switch ($action) {
        // ---------- Të dhënat personale ----------
        case 'save_profile':
            $tab = 'personal';
            $d = [
                'full_name'  => input('full_name'),
                'phone'      => input('phone'),
                'city'       => input('city'),
                'birth_date' => input('birth_date'),
                'headline'   => input('headline'),
                'bio'        => input('bio'),
                'skills'     => input('skills'),
            ];
            if (str_len($d['full_name']) < 3 || str_len($d['full_name']) > 120) $errors[] = 'Emri duhet të ketë 3-120 karaktere.';
            if (!valid_phone($d['phone'])) $errors[] = 'Numri i telefonit nuk është valid.';
            if ($d['city'] !== '' && !in_array($d['city'], cities(), true)) $errors[] = 'Zgjidhni qytetin nga lista.';
            if ($d['birth_date'] !== '' && (!valid_date($d['birth_date']) || $d['birth_date'] > date('Y-m-d', strtotime('-15 years')))) {
                $errors[] = 'Data e lindjes nuk është valide (min. 15 vjeç).';
            }
            if (str_len($d['headline']) > 150) $errors[] = 'Titulli profesional nuk duhet të kalojë 150 karaktere.';
            if (str_len($d['bio']) > 3000) $errors[] = 'Biografia nuk duhet të kalojë 3000 karaktere.';
            if (str_len($d['skills']) > 1000) $errors[] = 'Aftësitë nuk duhet të kalojnë 1000 karaktere.';

            if (!$errors) {
                // Normalizo aftësitë: të ndara me presje, pa dublikata
                $skills = array_unique(array_filter(array_map('trim', explode(',', $d['skills']))));
                $pdo->prepare('UPDATE users SET full_name = ? WHERE id = ?')->execute([$d['full_name'], $uid]);
                $pdo->prepare('UPDATE user_profiles SET phone = ?, city = ?, birth_date = ?, headline = ?, bio = ?, skills = ? WHERE user_id = ?')
                    ->execute([$d['phone'] ?: null, $d['city'] ?: null, $d['birth_date'] ?: null, $d['headline'] ?: null,
                               $d['bio'] ?: null, $skills ? implode(', ', $skills) : null, $uid]);
                $_SESSION['full_name'] = $d['full_name'];
                flash('success', 'Profili u përditësua me sukses.');
                redirect(url('candidate/profile.php?tab=personal'));
            }
            break;

        // ---------- CV ----------
        case 'upload_cv':
            $tab = 'cv';
            try {
                $new = upload_cv($_FILES['cv'] ?? []);
                $old = $pdo->prepare('SELECT cv_file FROM user_profiles WHERE user_id = ?');
                $old->execute([$uid]);
                $oldFile = $old->fetchColumn();
                $pdo->prepare('UPDATE user_profiles SET cv_file = ?, cv_original_name = ? WHERE user_id = ?')
                    ->execute([$new, mb_substr(basename($_FILES['cv']['name']), 0, 255), $uid]);
                // Fshi CV-në e vjetër vetëm nëse nuk përdoret në ndonjë aplikim
                if ($oldFile && !cv_in_use($pdo, $oldFile)) {
                    delete_upload(CV_PATH, $oldFile);
                }
                flash('success', 'CV u ngarkua me sukses.');
                redirect(url('candidate/profile.php?tab=cv'));
            } catch (RuntimeException $ex) {
                $errors[] = $ex->getMessage();
            }
            break;

        case 'delete_cv':
            $old = $pdo->prepare('SELECT cv_file FROM user_profiles WHERE user_id = ?');
            $old->execute([$uid]);
            $oldFile = $old->fetchColumn();
            $pdo->prepare('UPDATE user_profiles SET cv_file = NULL, cv_original_name = NULL WHERE user_id = ?')->execute([$uid]);
            if ($oldFile && !cv_in_use($pdo, $oldFile)) {
                delete_upload(CV_PATH, $oldFile);
            }
            flash('success', 'CV u fshi nga profili.');
            redirect(url('candidate/profile.php?tab=cv'));
            break;

        // ---------- Edukimi ----------
        case 'save_education':
            $tab = 'education';
            $eid = input_int('edu_id');
            $d = [
                'institution'    => input('institution'),
                'degree'         => input('degree'),
                'field_of_study' => input('field_of_study'),
                'start_year'     => input_int('start_year'),
                'end_year'       => input_int('end_year'),
                'description'    => input('description'),
            ];
            $year = (int) date('Y');
            if (str_len($d['institution']) < 2 || str_len($d['institution']) > 150) $errors[] = 'Shkruani institucionin (2-150 karaktere).';
            if (str_len($d['degree']) < 2 || str_len($d['degree']) > 120) $errors[] = 'Shkruani nivelin/diplomën.';
            if ($d['start_year'] < 1950 || $d['start_year'] > $year) $errors[] = 'Viti i fillimit nuk është valid.';
            if ($d['end_year'] && ($d['end_year'] < $d['start_year'] || $d['end_year'] > $year + 8)) $errors[] = 'Viti i përfundimit nuk është valid.';
            if (!$errors) {
                $vals = [$d['institution'], $d['degree'], $d['field_of_study'] ?: null, $d['start_year'], $d['end_year'] ?: null, $d['description'] ?: null];
                if ($eid) {
                    $pdo->prepare('UPDATE educations SET institution = ?, degree = ?, field_of_study = ?, start_year = ?, end_year = ?, description = ? WHERE id = ? AND user_id = ?')
                        ->execute(array_merge($vals, [$eid, $uid]));
                    flash('success', 'Edukimi u përditësua.');
                } else {
                    $pdo->prepare('INSERT INTO educations (institution, degree, field_of_study, start_year, end_year, description, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)')
                        ->execute(array_merge($vals, [$uid]));
                    flash('success', 'Edukimi u shtua.');
                }
                redirect(url('candidate/profile.php?tab=education'));
            }
            break;

        case 'delete_education':
            $pdo->prepare('DELETE FROM educations WHERE id = ? AND user_id = ?')->execute([input_int('edu_id'), $uid]);
            flash('success', 'Edukimi u fshi.');
            redirect(url('candidate/profile.php?tab=education'));
            break;

        // ---------- Përvoja ----------
        case 'save_experience':
            $tab = 'experience';
            $xid = input_int('exp_id');
            $d = [
                'company_name' => input('company_name'),
                'position'     => input('position'),
                'start_date'   => input('start_date'),
                'end_date'     => input('end_date'),
                'is_current'   => !empty($_POST['is_current']) ? 1 : 0,
                'description'  => input('description'),
            ];
            if (str_len($d['company_name']) < 2 || str_len($d['company_name']) > 150) $errors[] = 'Shkruani emrin e kompanisë.';
            if (str_len($d['position']) < 2 || str_len($d['position']) > 120) $errors[] = 'Shkruani pozitën.';
            if (!valid_date($d['start_date']) || $d['start_date'] > date('Y-m-d')) $errors[] = 'Data e fillimit nuk është valide.';
            if (!$d['is_current']) {
                if (!valid_date($d['end_date'])) $errors[] = 'Shkruani datën e përfundimit ose zgjidhni "Punoj aktualisht këtu".';
                elseif ($d['end_date'] < $d['start_date']) $errors[] = 'Data e përfundimit duhet të jetë pas datës së fillimit.';
            }
            if (!$errors) {
                $vals = [$d['company_name'], $d['position'], $d['start_date'], $d['is_current'] ? null : $d['end_date'], $d['is_current'], $d['description'] ?: null];
                if ($xid) {
                    $pdo->prepare('UPDATE experiences SET company_name = ?, position = ?, start_date = ?, end_date = ?, is_current = ?, description = ? WHERE id = ? AND user_id = ?')
                        ->execute(array_merge($vals, [$xid, $uid]));
                    flash('success', 'Përvoja u përditësua.');
                } else {
                    $pdo->prepare('INSERT INTO experiences (company_name, position, start_date, end_date, is_current, description, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)')
                        ->execute(array_merge($vals, [$uid]));
                    flash('success', 'Përvoja u shtua.');
                }
                redirect(url('candidate/profile.php?tab=experience'));
            }
            break;

        case 'delete_experience':
            $pdo->prepare('DELETE FROM experiences WHERE id = ? AND user_id = ?')->execute([input_int('exp_id'), $uid]);
            flash('success', 'Përvoja u fshi.');
            redirect(url('candidate/profile.php?tab=experience'));
            break;
    }
}

// ---- Ngarkimi i të dhënave ----
$stmt = $pdo->prepare('SELECT u.full_name, u.email, u.created_at, p.* FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id WHERE u.id = ?');
$stmt->execute([$uid]);
$p = $stmt->fetch();

$edus = $pdo->prepare('SELECT * FROM educations WHERE user_id = ? ORDER BY start_year DESC');
$edus->execute([$uid]);
$edus = $edus->fetchAll();

$exps = $pdo->prepare('SELECT * FROM experiences WHERE user_id = ? ORDER BY is_current DESC, start_date DESC');
$exps->execute([$uid]);
$exps = $exps->fetchAll();

// Rreshti që po editohet (nëse ka)
$editEdu = null;
if ($id = input_int('edit_edu')) {
    foreach ($edus as $row) if ((int) $row['id'] === $id) $editEdu = $row;
    $tab = 'education';
}
$editExp = null;
if ($id = input_int('edit_exp')) {
    foreach ($exps as $row) if ((int) $row['id'] === $id) $editExp = $row;
    $tab = 'experience';
}
// Pas gabimit në POST rimbush formën me vlerat e dërguara
$post = fn($k, $fallback = '') => is_post() ? e($_POST[$k] ?? '') : e($fallback);

$completeness = profile_completeness($pdo, $uid);
$pageTitle = 'Profili im';
require ROOT_PATH . '/includes/header.php';
?>
<div class="container dash-layout">
    <?php require ROOT_PATH . '/includes/sidebar.php'; ?>
    <section class="dash-content">
        <div class="card mb-3">
            <div class="profile-head">
                <span class="avatar avatar-lg"><?= e(initials($p['full_name'])) ?></span>
                <div style="flex:1">
                    <h1 class="mb-0" style="font-size:1.5rem"><?= e($p['full_name']) ?></h1>
                    <p class="muted mb-0"><?= e($p['headline'] ?: 'Shtoni titullin tuaj profesional') ?> · <?= e($p['city'] ?: 'Qyteti i papërcaktuar') ?></p>
                    <div class="progress mt-2" style="max-width:320px"><div style="width:<?= $completeness['percent'] ?>%"></div></div>
                    <small>Profili i plotësuar <?= $completeness['percent'] ?>%</small>
                </div>
            </div>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <div class="tabs">
            <a href="?tab=personal" class="<?= $tab === 'personal' ? 'active' : '' ?>">👤 Të dhënat personale</a>
            <a href="?tab=cv" class="<?= $tab === 'cv' ? 'active' : '' ?>">📄 CV</a>
            <a href="?tab=education" class="<?= $tab === 'education' ? 'active' : '' ?>">🎓 Edukimi (<?= count($edus) ?>)</a>
            <a href="?tab=experience" class="<?= $tab === 'experience' ? 'active' : '' ?>">💼 Përvoja (<?= count($exps) ?>)</a>
        </div>

        <?php if ($tab === 'personal'): ?>
        <div class="card">
            <form method="post" data-validate novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_profile">
                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">Emri dhe mbiemri <span class="req">*</span></label>
                        <input type="text" id="full_name" name="full_name" value="<?= $post('full_name', $p['full_name']) ?>" required maxlength="120">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" value="<?= e($p['email']) ?>" disabled>
                        <span class="help">Email-i nuk mund të ndryshohet.</span>
                    </div>
                </div>
                <div class="form-row-3">
                    <div class="form-group">
                        <label for="phone">Telefoni</label>
                        <input type="tel" id="phone" name="phone" value="<?= $post('phone', $p['phone']) ?>" placeholder="+383 4X XXX XXX" maxlength="30">
                    </div>
                    <div class="form-group">
                        <label for="city">Qyteti</label>
                        <?php $selCity = is_post() ? ($_POST['city'] ?? '') : $p['city']; ?>
                        <select id="city" name="city">
                            <option value="">Zgjidh</option>
                            <?php foreach (cities() as $c): ?><option value="<?= e($c) ?>" <?= $selCity === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="birth_date">Data e lindjes</label>
                        <input type="date" id="birth_date" name="birth_date" value="<?= $post('birth_date', $p['birth_date']) ?>" max="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="headline">Titulli profesional</label>
                    <input type="text" id="headline" name="headline" value="<?= $post('headline', $p['headline']) ?>" maxlength="150" placeholder="p.sh. Zhvillues Web Junior">
                </div>
                <div class="form-group">
                    <label for="bio">Biografia</label>
                    <textarea id="bio" name="bio" rows="5" maxlength="3000" data-counter placeholder="Shkruani shkurt për veten, qëllimet dhe interesat profesionale..."><?= $post('bio', $p['bio']) ?></textarea>
                </div>
                <div class="form-group">
                    <label for="skills">Aftësitë</label>
                    <input type="text" id="skills" name="skills" value="<?= $post('skills', $p['skills']) ?>" maxlength="1000" placeholder="p.sh. PHP, MySQL, Komunikim, Excel">
                    <span class="help">Ndajini me presje.</span>
                </div>
                <?php if ($p['skills']): ?>
                    <div class="skills mb-3"><?php foreach (explode(',', $p['skills']) as $s): ?><span class="chip chip-red"><?= e(trim($s)) ?></span><?php endforeach; ?></div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary">Ruaj ndryshimet</button>
            </form>
        </div>

        <?php elseif ($tab === 'cv'): ?>
        <div class="card">
            <h3>CV-ja juaj</h3>
            <?php if ($p['cv_file']): ?>
                <div class="list-item">
                    <div class="flex"><span class="stat-icon">📄</span>
                        <div><h4><?= e($p['cv_original_name'] ?: 'CV.pdf') ?></h4><small>Përditësuar <?= e(format_date($p['updated_at'], true)) ?></small></div></div>
                    <div class="flex">
                        <a class="btn btn-outline btn-sm" href="<?= url('download.php?type=profile') ?>" target="_blank">👁 Shiko</a>
                        <a class="btn btn-outline btn-sm" href="<?= url('download.php?type=profile&download=1') ?>">⬇ Shkarko</a>
                        <form method="post" class="inline-form" data-confirm="Të fshihet CV nga profili?">
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete_cv">
                            <button class="btn btn-danger btn-sm" type="submit">Fshi</button>
                        </form>
                    </div>
                </div>
                <hr>
                <h4><?= 'Zëvendëso CV-në' ?></h4>
            <?php else: ?>
                <div class="alert alert-info"><span>Nuk keni ngarkuar ende CV. Me CV në profil aplikoni më shpejt.</span></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" data-validate novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload_cv">
                <div class="form-group">
                    <label class="file-drop">
                        <input type="file" name="cv" accept="application/pdf,.pdf" required>
                        📤 Kliko për të zgjedhur CV-në në PDF (maks. <?= MAX_CV_SIZE / 1048576 ?> MB)
                        <span class="file-name"></span>
                    </label>
                </div>
                <button type="submit" class="btn btn-primary">Ngarko CV-në</button>
            </form>
        </div>

        <?php elseif ($tab === 'education'): ?>
        <div class="card">
            <div class="card-title"><h3>Edukimi</h3></div>
            <?php if (!$edus): ?><p class="muted">Nuk keni shtuar edukim.</p><?php endif; ?>
            <?php foreach ($edus as $ed): ?>
                <div class="timeline-item">
                    <div class="flex-between">
                        <div>
                            <h4><?= e($ed['degree']) ?><?= $ed['field_of_study'] ? ' – ' . e($ed['field_of_study']) : '' ?></h4>
                            <small><?= e($ed['institution']) ?> · <?= (int) $ed['start_year'] ?> – <?= $ed['end_year'] ? (int) $ed['end_year'] : 'në vazhdim' ?></small>
                        </div>
                        <div class="flex">
                            <a class="btn btn-outline btn-xs" href="?tab=education&edit_edu=<?= (int) $ed['id'] ?>#edu-form">Edito</a>
                            <form method="post" class="inline-form" data-confirm="Të fshihet ky edukim?">
                                <?= csrf_field() ?><input type="hidden" name="action" value="delete_education"><input type="hidden" name="edu_id" value="<?= (int) $ed['id'] ?>">
                                <button class="btn btn-danger btn-xs" type="submit">Fshi</button>
                            </form>
                        </div>
                    </div>
                    <?php if ($ed['description']): ?><p class="muted mb-0 mt-2"><?= nl2br_e($ed['description']) ?></p><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="card" id="edu-form">
            <h3><?= $editEdu ? 'Edito edukimin' : 'Shto edukim' ?></h3>
            <form method="post" data-validate novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_education">
                <input type="hidden" name="edu_id" value="<?= (int) ($editEdu['id'] ?? input_int('edu_id')) ?>">
                <div class="form-row">
                    <div class="form-group"><label for="institution">Institucioni <span class="req">*</span></label>
                        <input type="text" id="institution" name="institution" value="<?= $post('institution', $editEdu['institution'] ?? '') ?>" required maxlength="150" placeholder="p.sh. Universiteti i Prishtinës"></div>
                    <div class="form-group"><label for="degree">Niveli / Diploma <span class="req">*</span></label>
                        <input type="text" id="degree" name="degree" value="<?= $post('degree', $editEdu['degree'] ?? '') ?>" required maxlength="120" placeholder="p.sh. Bachelor, Master"></div>
                </div>
                <div class="form-row-3">
                    <div class="form-group"><label for="field_of_study">Drejtimi</label>
                        <input type="text" id="field_of_study" name="field_of_study" value="<?= $post('field_of_study', $editEdu['field_of_study'] ?? '') ?>" maxlength="120"></div>
                    <div class="form-group"><label for="start_year">Viti i fillimit <span class="req">*</span></label>
                        <input type="number" id="start_year" name="start_year" min="1950" max="<?= date('Y') ?>" value="<?= $post('start_year', $editEdu['start_year'] ?? '') ?>" required></div>
                    <div class="form-group"><label for="end_year">Viti i përfundimit</label>
                        <input type="number" id="end_year" name="end_year" min="1950" max="<?= date('Y') + 8 ?>" value="<?= $post('end_year', $editEdu['end_year'] ?? '') ?>" placeholder="Bosh = në vazhdim"></div>
                </div>
                <div class="form-group"><label for="edu_description">Përshkrimi</label>
                    <textarea id="edu_description" name="description" rows="3" maxlength="2000"><?= $post('description', $editEdu['description'] ?? '') ?></textarea></div>
                <div class="flex">
                    <button type="submit" class="btn btn-primary"><?= $editEdu ? 'Ruaj ndryshimet' : '+ Shto edukimin' ?></button>
                    <?php if ($editEdu): ?><a href="?tab=education" class="btn btn-outline">Anulo</a><?php endif; ?>
                </div>
            </form>
        </div>

        <?php else: ?>
        <div class="card">
            <div class="card-title"><h3>Përvoja e punës</h3></div>
            <?php if (!$exps): ?><p class="muted">Nuk keni shtuar përvojë pune.</p><?php endif; ?>
            <?php foreach ($exps as $ex): ?>
                <div class="timeline-item">
                    <div class="flex-between">
                        <div>
                            <h4><?= e($ex['position']) ?></h4>
                            <small><?= e($ex['company_name']) ?> · <?= e(format_date($ex['start_date'])) ?> – <?= $ex['is_current'] ? 'Aktualisht' : e(format_date($ex['end_date'])) ?></small>
                        </div>
                        <div class="flex">
                            <a class="btn btn-outline btn-xs" href="?tab=experience&edit_exp=<?= (int) $ex['id'] ?>#exp-form">Edito</a>
                            <form method="post" class="inline-form" data-confirm="Të fshihet kjo përvojë?">
                                <?= csrf_field() ?><input type="hidden" name="action" value="delete_experience"><input type="hidden" name="exp_id" value="<?= (int) $ex['id'] ?>">
                                <button class="btn btn-danger btn-xs" type="submit">Fshi</button>
                            </form>
                        </div>
                    </div>
                    <?php if ($ex['description']): ?><p class="muted mb-0 mt-2"><?= nl2br_e($ex['description']) ?></p><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="card" id="exp-form">
            <h3><?= $editExp ? 'Edito përvojën' : 'Shto përvojë pune' ?></h3>
            <form method="post" data-validate novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_experience">
                <input type="hidden" name="exp_id" value="<?= (int) ($editExp['id'] ?? input_int('exp_id')) ?>">
                <div class="form-row">
                    <div class="form-group"><label for="position">Pozita <span class="req">*</span></label>
                        <input type="text" id="position" name="position" value="<?= $post('position', $editExp['position'] ?? '') ?>" required maxlength="120"></div>
                    <div class="form-group"><label for="company_name">Kompania <span class="req">*</span></label>
                        <input type="text" id="company_name" name="company_name" value="<?= $post('company_name', $editExp['company_name'] ?? '') ?>" required maxlength="150"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="start_date">Data e fillimit <span class="req">*</span></label>
                        <input type="date" id="start_date" name="start_date" value="<?= $post('start_date', $editExp['start_date'] ?? '') ?>" max="<?= date('Y-m-d') ?>" required></div>
                    <div class="form-group"><label for="end_date">Data e përfundimit</label>
                        <input type="date" id="end_date" name="end_date" value="<?= $post('end_date', $editExp['end_date'] ?? '') ?>"></div>
                </div>
                <?php $isCur = is_post() ? !empty($_POST['is_current']) : !empty($editExp['is_current']); ?>
                <div class="form-group"><label class="checkbox"><input type="checkbox" name="is_current" value="1" data-current-toggle="end_date" <?= $isCur ? 'checked' : '' ?>> Punoj aktualisht këtu</label></div>
                <div class="form-group"><label for="exp_description">Përshkrimi i detyrave</label>
                    <textarea id="exp_description" name="description" rows="3" maxlength="2000"><?= $post('description', $editExp['description'] ?? '') ?></textarea></div>
                <div class="flex">
                    <button type="submit" class="btn btn-primary"><?= $editExp ? 'Ruaj ndryshimet' : '+ Shto përvojën' ?></button>
                    <?php if ($editExp): ?><a href="?tab=experience" class="btn btn-outline">Anulo</a><?php endif; ?>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </section>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
