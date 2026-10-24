<?php
/**
 * Kyçja e përdoruesit
 */
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect(dashboard_url());
}

$errors = [];
if (is_post()) {
    verify_csrf();
    $email = strtolower(input('email'));
    $password = $_POST['password'] ?? '';

    // Mbrojtje bazike nga brute-force: maks. 5 tentime të dështuara për 10 minuta
    $attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'time' => time()];
    if (time() - $attempts['time'] > 600) {
        $attempts = ['count' => 0, 'time' => time()];
    }

    if ($attempts['count'] >= 5) {
        $errors[] = 'Shumë tentime të dështuara. Provoni përsëri pas disa minutash.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $errors[] = 'Shkruani email-in dhe fjalëkalimin.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if (!(int) $user['is_active']) {
                $errors[] = 'Llogaria juaj është çaktivizuar. Kontaktoni administratorin.';
            } else {
                // Rifresko hash-in nëse algoritmi është përditësuar
                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                        ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                }
                $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);
                $target = $_SESSION['redirect_after_login'] ?? '';
                unset($_SESSION['login_attempts'], $_SESSION['redirect_after_login']);
                login_user($user);
                flash('success', 'Mirë se erdhët, ' . $user['full_name'] . '!');
                redirect($target !== '' && $target[0] === '/' ? $target : dashboard_url($user['role']));
            }
        } else {
            $attempts['count']++;
            $errors[] = 'Email-i ose fjalëkalimi është i pasaktë.';
        }
    }
    $_SESSION['login_attempts'] = $attempts;
}

$pageTitle = 'Kyçu';
require ROOT_PATH . '/includes/header.php';
?>
<div class="auth-wrap">
    <div class="card auth-card">
        <h1>Mirë se erdhe përsëri 👋</h1>
        <p class="auth-sub">Kyçu në llogarinë tënde të PunaKosova.</p>

        <?php if ($errors): ?>
            <div class="alert alert-error"><span><?= e(implode(' ', $errors)) ?></span></div>
        <?php endif; ?>

        <form method="post" data-validate novalidate>
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="email">Email <span class="req">*</span></label>
                <input type="email" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="email" autofocus>
            </div>
            <div class="form-group">
                <label for="password">Fjalëkalimi <span class="req">*</span></label>
                <div class="password-wrap">
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                    <button type="button" class="toggle-pass" data-toggle-pass>Shfaq</button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Kyçu</button>
        </form>
        <p class="text-center mt-3 mb-0">Nuk ke llogari? <a href="<?= url('register.php') ?>"><strong>Regjistrohu</strong></a></p>

        <div class="demo-box">
            <strong>Llogari testuese:</strong><br>
            Kandidat: <code>kandidat@punakosova.com</code> / <code>Kandidat123!</code><br>
            Punëdhënës: <code>punedhenes@punakosova.com</code> / <code>Punedhenes123!</code><br>
            Admin: <code>admin@punakosova.com</code> / <code>Admin123!</code>
        </div>
    </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
