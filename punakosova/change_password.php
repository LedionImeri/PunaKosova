<?php
/**
 * Ndryshimi i fjalëkalimit (për të gjitha rolet)
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$errors = [];
if (is_post()) {
    verify_csrf();
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([current_user_id()]);
    $hash = $stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $errors[] = 'Fjalëkalimi aktual është i pasaktë.';
    }
    if (!valid_password($new)) {
        $errors[] = 'Fjalëkalimi i ri duhet të ketë së paku 8 karaktere, një shkronjë dhe një numër.';
    } elseif ($new !== $confirm) {
        $errors[] = 'Fjalëkalimet e reja nuk përputhen.';
    }
    if (!$errors) {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), current_user_id()]);
        session_regenerate_id(true);
        flash('success', 'Fjalëkalimi u ndryshua me sukses.');
        redirect(url('change_password.php'));
    }
}

$pageTitle = 'Ndrysho fjalëkalimin';
require ROOT_PATH . '/includes/header.php';
?>
<div class="container dash-layout">
    <?php require ROOT_PATH . '/includes/sidebar.php'; ?>
    <section class="dash-content">
        <div class="dash-title"><div><h1>Ndrysho fjalëkalimin</h1><p>Mbaje llogarinë tënde të sigurt.</p></div></div>
        <div class="card" style="max-width:560px">
            <?php if ($errors): ?>
                <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form method="post" data-validate novalidate>
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="current_password">Fjalëkalimi aktual <span class="req">*</span></label>
                    <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label for="password">Fjalëkalimi i ri <span class="req">*</span></label>
                    <div class="password-wrap">
                        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                        <button type="button" class="toggle-pass" data-toggle-pass>Shfaq</button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password_confirm">Konfirmo fjalëkalimin e ri <span class="req">*</span></label>
                    <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-primary">Ruaj fjalëkalimin</button>
            </form>
        </div>
    </section>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
