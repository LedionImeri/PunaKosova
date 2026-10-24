<?php
/**
 * Krijimi dhe editimi i profilit të kompanisë
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('employer');
$uid = current_user_id();
$company = employer_company($pdo);
$errors = [];

if (is_post()) {
    verify_csrf();

    if (input('action') === 'delete_logo' && $company) {
        delete_upload(LOGO_PATH, $company['logo']);
        $pdo->prepare('UPDATE companies SET logo = NULL WHERE id = ?')->execute([$company['id']]);
        flash('success', 'Logoja u fshi.');
        redirect(url('employer/company.php'));
    }

    $d = [
        'name'        => input('name'),
        'industry'    => input('industry'),
        'description' => input('description'),
        'city'        => input('city'),
        'address'     => input('address'),
        'phone'       => input('phone'),
        'email'       => strtolower(input('email')),
        'website'     => input('website'),
        'employees'   => input('employees'),
    ];
    if (str_len($d['name']) < 2 || str_len($d['name']) > 150) $errors[] = 'Emri i kompanisë duhet të ketë 2-150 karaktere.';
    if ($d['city'] === '' || !in_array($d['city'], cities(), true)) $errors[] = 'Zgjidhni qytetin.';
    if (!valid_phone($d['phone'])) $errors[] = 'Numri i telefonit nuk është valid.';
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email-i nuk është valid.';
    if ($d['website'] !== '') {
        if (!preg_match('#^https?://#i', $d['website'])) $d['website'] = 'https://' . $d['website'];
        if (!filter_var($d['website'], FILTER_VALIDATE_URL)) $errors[] = 'Website-i nuk është valid.';
    }
    if (str_len($d['description']) > 5000) $errors[] = 'Përshkrimi nuk duhet të kalojë 5000 karaktere.';
    if ($d['employees'] !== '' && !in_array($d['employees'], ['1-10', '11-50', '50-100', '100-200', '200+'], true)) $errors[] = 'Numri i punëtorëve nuk është valid.';

    // Logoja (opsionale)
    $newLogo = null;
    if (!$errors && !empty($_FILES['logo']['name'])) {
        try {
            $newLogo = upload_logo($_FILES['logo']);
        } catch (RuntimeException $ex) {
            $errors[] = 'Logoja: ' . $ex->getMessage();
        }
    }

    if (!$errors) {
        $vals = [$d['name'], $d['industry'] ?: null, $d['description'] ?: null, $d['city'], $d['address'] ?: null,
                 $d['phone'] ?: null, $d['email'] ?: null, $d['website'] ?: null, $d['employees'] ?: null];
        if ($company) {
            $pdo->prepare('UPDATE companies SET name = ?, industry = ?, description = ?, city = ?, address = ?, phone = ?, email = ?, website = ?, employees = ? WHERE id = ? AND user_id = ?')
                ->execute(array_merge($vals, [$company['id'], $uid]));
            if ($newLogo) {
                delete_upload(LOGO_PATH, $company['logo']);
                $pdo->prepare('UPDATE companies SET logo = ? WHERE id = ?')->execute([$newLogo, $company['id']]);
            }
        } else {
            $pdo->prepare('INSERT INTO companies (name, industry, description, city, address, phone, email, website, employees, logo, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute(array_merge($vals, [$newLogo, $uid]));
        }
        flash('success', 'Profili i kompanisë u ruajt me sukses.');
        redirect(url('employer/company.php'));
    } elseif ($newLogo) {
        delete_upload(LOGO_PATH, $newLogo);
    }
}

$c = is_post() ? array_merge($company ?? [], $_POST) : ($company ?? []);
$v = fn($k) => e($c[$k] ?? '');

$pageTitle = 'Profili i kompanisë';
require ROOT_PATH . '/includes/header.php';
?>
<div class="container dash-layout">
    <?php require ROOT_PATH . '/includes/sidebar.php'; ?>
    <section class="dash-content">
        <div class="dash-title">
            <div><h1>Profili i kompanisë</h1><p>Këto të dhëna shfaqen publikisht te shpalljet tuaja.</p></div>
            <?php if ($company): ?><a href="<?= url('company.php?id=' . (int) $company['id']) ?>" class="btn btn-outline" target="_blank">👁 Shiko profilin publik</a><?php endif; ?>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <div class="card">
            <form method="post" enctype="multipart/form-data" data-validate novalidate>
                <?= csrf_field() ?>
                <div class="profile-head mb-3">
                    <?= company_logo($company['logo'] ?? null, $company['name'] ?? 'K', 'logo-lg') ?>
                    <div style="flex:1;min-width:220px">
                        <label class="file-drop">
                            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
                            🖼 Ngarko logon (JPG, PNG, WEBP – maks. <?= MAX_LOGO_SIZE / 1048576 ?> MB)
                            <span class="file-name"></span>
                        </label>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group"><label for="name">Emri i kompanisë <span class="req">*</span></label>
                        <input type="text" id="name" name="name" value="<?= $v('name') ?>" required maxlength="150"></div>
                    <div class="form-group"><label for="industry">Industria</label>
                        <input type="text" id="industry" name="industry" value="<?= $v('industry') ?>" maxlength="100" placeholder="p.sh. Teknologji Informative"></div>
                </div>
                <div class="form-group"><label for="description">Përshkrimi i kompanisë</label>
                    <textarea id="description" name="description" rows="6" maxlength="5000" data-counter><?= $v('description') ?></textarea></div>
                <div class="form-row">
                    <div class="form-group"><label for="city">Qyteti <span class="req">*</span></label>
                        <select id="city" name="city" required>
                            <option value="">Zgjidh</option>
                            <?php foreach (cities() as $ct): ?><option value="<?= e($ct) ?>" <?= ($c['city'] ?? '') === $ct ? 'selected' : '' ?>><?= e($ct) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label for="address">Adresa</label>
                        <input type="text" id="address" name="address" value="<?= $v('address') ?>" maxlength="200"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="phone">Telefoni</label>
                        <input type="tel" id="phone" name="phone" value="<?= $v('phone') ?>" maxlength="30"></div>
                    <div class="form-group"><label for="email">Email kontakti</label>
                        <input type="email" id="email" name="email" value="<?= $v('email') ?>" maxlength="150"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="website">Website</label>
                        <input type="text" id="website" name="website" value="<?= $v('website') ?>" maxlength="200" placeholder="https://..."></div>
                    <div class="form-group"><label for="employees">Numri i punëtorëve</label>
                        <select id="employees" name="employees">
                            <option value="">Zgjidh</option>
                            <?php foreach (['1-10', '11-50', '50-100', '100-200', '200+'] as $opt): ?>
                                <option value="<?= $opt ?>" <?= ($c['employees'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select></div>
                </div>
                <button type="submit" class="btn btn-primary">Ruaj profilin</button>
            </form>
            <?php if (!empty($company['logo'])): ?>
                <form method="post" class="mt-2" data-confirm="Të fshihet logoja?">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete_logo">
                    <button class="btn btn-danger btn-sm" type="submit">Fshi logon</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
