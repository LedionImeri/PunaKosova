<?php
/**
 * Regjistrimi i kandidatëve dhe punëdhënësve
 */
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect(dashboard_url());
}

$errors = [];
$data = [
    'role'         => in_array($_GET['role'] ?? '', ['candidate', 'employer'], true) ? $_GET['role'] : 'candidate',
    'full_name'    => '',
    'email'        => '',
    'company_name' => '',
    'city'         => '',
];

if (is_post()) {
    verify_csrf();
    $data = [
        'role'         => input('role'),
        'full_name'    => input('full_name'),
        'email'        => strtolower(input('email')),
        'company_name' => input('company_name'),
        'city'         => input('city'),
    ];
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    // ---- Validimi ----
    if (!in_array($data['role'], ['candidate', 'employer'], true)) {
        $errors['role'] = 'Zgjidhni llojin e llogarisë.';
    }
    if (str_len($data['full_name']) < 3 || str_len($data['full_name']) > 120) {
        $errors['full_name'] = 'Emri duhet të ketë 3-120 karaktere.';
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email-i nuk është valid.';
    }
    if (!valid_password($password)) {
        $errors['password'] = 'Fjalëkalimi duhet të ketë së paku 8 karaktere, një shkronjë dhe një numër.';
    } elseif ($password !== $confirm) {
        $errors['password_confirm'] = 'Fjalëkalimet nuk përputhen.';
    }
    if ($data['city'] !== '' && !in_array($data['city'], cities(), true)) {
        $errors['city'] = 'Zgjidhni një qytet nga lista.';
    }
    if ($data['role'] === 'employer' && (str_len($data['company_name']) < 2 || str_len($data['company_name']) > 150)) {
        $errors['company_name'] = 'Shkruani emrin e kompanisë.';
    }
    if (empty($_POST['terms'])) {
        $errors['terms'] = 'Duhet të pranoni kushtet e përdorimit.';
    }
    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Ky email është i regjistruar tashmë.';
        }
    }

    // ---- Ruajtja në transaksion ----
    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([$data['full_name'], $data['email'], password_hash($password, PASSWORD_DEFAULT), $data['role']]);
            $userId = (int) $pdo->lastInsertId();

            if ($data['role'] === 'candidate') {
                $pdo->prepare('INSERT INTO user_profiles (user_id, city) VALUES (?, ?)')->execute([$userId, $data['city'] ?: null]);
            } else {
                $pdo->prepare('INSERT INTO companies (user_id, name, city, email) VALUES (?, ?, ?, ?)')
                    ->execute([$userId, $data['company_name'], $data['city'] ?: null, $data['email']]);
            }
            notify($pdo, $userId, 'system', 'Mirë se vini në PunaKosova!',
                $data['role'] === 'candidate'
                    ? 'Plotësoni profilin dhe ngarkoni CV-në për të aplikuar më shpejt.'
                    : 'Plotësoni profilin e kompanisë dhe publikoni shpalljen e parë.',
                $data['role'] === 'candidate' ? '/candidate/profile.php' : '/employer/company.php');
            notify_admins($pdo, 'user', 'Përdorues i ri', $data['full_name'] . ' u regjistrua si ' . label(role_labels(), $data['role']) . '.', '/admin/users.php');
            $pdo->commit();

            login_user(['id' => $userId, 'role' => $data['role'], 'full_name' => $data['full_name']]);
            flash('success', 'Llogaria u krijua me sukses! Mirë se erdhët.');
            redirect($data['role'] === 'candidate' ? url('candidate/profile.php') : url('employer/company.php'));
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['general'] = 'Regjistrimi dështoi. Provoni përsëri.';
        }
    }
}

$pageTitle = 'Regjistrohu';
require ROOT_PATH . '/includes/header.php';
$err = fn($k) => isset($errors[$k]) ? '<span class="field-error">' . e($errors[$k]) . '</span>' : '';
$inv = fn($k) => isset($errors[$k]) ? ' is-invalid' : '';
?>
<div class="auth-wrap">
    <div class="card auth-card wide">
        <h1>Krijo llogari falas</h1>
        <p class="auth-sub">Bashkohu me PunaKosova si kandidat ose si punëdhënës.</p>

        <?php if ($errors): ?>
            <div class="alert alert-error"><span><?= e($errors['general'] ?? 'Ju lutem korrigjoni gabimet më poshtë.') ?></span></div>
        <?php endif; ?>

        <form method="post" data-validate novalidate>
            <?= csrf_field() ?>
            <div class="role-picker">
                <label class="role-option">
                    <input type="radio" name="role" value="candidate" <?= $data['role'] === 'candidate' ? 'checked' : '' ?> data-role-radio>
                    <span><em>👤</em>Kandidat<small>Kërkoj punë</small></span>
                </label>
                <label class="role-option">
                    <input type="radio" name="role" value="employer" <?= $data['role'] === 'employer' ? 'checked' : '' ?> data-role-radio>
                    <span><em>🏢</em>Punëdhënës<small>Dua të punësoj</small></span>
                </label>
            </div>

            <div class="form-group" id="company-field" <?= $data['role'] === 'employer' ? '' : 'hidden' ?>>
                <label for="company_name">Emri i kompanisë <span class="req">*</span></label>
                <input type="text" id="company_name" name="company_name" class="<?= $inv('company_name') ?>" value="<?= e($data['company_name']) ?>" maxlength="150">
                <?= $err('company_name') ?>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="full_name">Emri dhe mbiemri <span class="req">*</span></label>
                    <input type="text" id="full_name" name="full_name" class="<?= $inv('full_name') ?>" value="<?= e($data['full_name']) ?>" required maxlength="120">
                    <?= $err('full_name') ?>
                </div>
                <div class="form-group">
                    <label for="email">Email <span class="req">*</span></label>
                    <input type="email" id="email" name="email" class="<?= $inv('email') ?>" value="<?= e($data['email']) ?>" required maxlength="150" autocomplete="email">
                    <?= $err('email') ?>
                </div>
            </div>

            <div class="form-group">
                <label for="city">Qyteti</label>
                <select id="city" name="city" class="<?= $inv('city') ?>">
                    <option value="">Zgjidh qytetin</option>
                    <?php foreach (cities() as $c): ?>
                        <option value="<?= e($c) ?>" <?= $data['city'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $err('city') ?>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Fjalëkalimi <span class="req">*</span></label>
                    <div class="password-wrap">
                        <input type="password" id="password" name="password" class="<?= $inv('password') ?>" required minlength="8" autocomplete="new-password">
                        <button type="button" class="toggle-pass" data-toggle-pass>Shfaq</button>
                    </div>
                    <?= $err('password') ?: '<span class="help">Min. 8 karaktere, shkronja dhe numra.</span>' ?>
                </div>
                <div class="form-group">
                    <label for="password_confirm">Konfirmo fjalëkalimin <span class="req">*</span></label>
                    <input type="password" id="password_confirm" name="password_confirm" class="<?= $inv('password_confirm') ?>" required autocomplete="new-password">
                    <?= $err('password_confirm') ?>
                </div>
            </div>

            <div class="form-group">
                <label class="checkbox"><input type="checkbox" name="terms" value="1" required <?= !empty($_POST['terms']) ? 'checked' : '' ?>> Pranoj kushtet e përdorimit dhe politikën e privatësisë</label>
                <?= $err('terms') ?>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Krijo llogarinë</button>
        </form>
        <p class="text-center mt-3 mb-0">Ke llogari? <a href="<?= url('login.php') ?>"><strong>Kyçu</strong></a></p>
    </div>
</div>
<script>
document.querySelectorAll('[data-role-radio]').forEach(function (r) {
    r.addEventListener('change', function () {
        var isEmp = document.querySelector('[data-role-radio]:checked').value === 'employer';
        var f = document.getElementById('company-field');
        f.hidden = !isEmp;
        document.getElementById('company_name').required = isEmp;
    });
});
document.getElementById('company_name').required = <?= $data['role'] === 'employer' ? 'true' : 'false' ?>;
</script>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
