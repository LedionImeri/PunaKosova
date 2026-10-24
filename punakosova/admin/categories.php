<?php
/**
 * Menaxhimi i kategorive (shto, edito, aktivizo/çaktivizo, fshi)
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$errors = [];
if (is_post()) {
    verify_csrf();
    $action = input('action');
    $id = input_int('id');

    if ($action === 'save') {
        $name = input('name');
        $icon = input('icon');
        $keywords = input('keywords');
        if (str_len($name) < 2 || str_len($name) > 100) $errors[] = 'Emri duhet të ketë 2-100 karaktere.';
        if (str_len($icon) > 10) $errors[] = 'Ikona duhet të jetë një emoji i shkurtër.';
        if (str_len($keywords) > 255) $errors[] = 'Fjalët kyçe nuk duhet të kalojnë 255 karaktere.';
        $slug = slugify($name);
        $dup = $pdo->prepare('SELECT id FROM categories WHERE (name = ? OR slug = ?) AND id <> ?');
        $dup->execute([$name, $slug, $id]);
        if ($dup->fetch()) $errors[] = 'Kjo kategori ekziston tashmë.';

        if (!$errors) {
            if ($id) {
                $pdo->prepare('UPDATE categories SET name = ?, slug = ?, icon = ?, keywords = ? WHERE id = ?')->execute([$name, $slug, $icon ?: null, $keywords ?: null, $id]);
                flash('success', 'Kategoria u përditësua.');
            } else {
                $pdo->prepare('INSERT INTO categories (name, slug, icon, keywords) VALUES (?, ?, ?, ?)')->execute([$name, $slug, $icon ?: null, $keywords ?: null]);
                flash('success', 'Kategoria u shtua.');
            }
            redirect(url('admin/categories.php'));
        }
    } elseif ($action === 'toggle') {
        $pdo->prepare('UPDATE categories SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        flash('success', 'Statusi i kategorisë u ndryshua.');
        redirect(url('admin/categories.php'));
    } elseif ($action === 'delete') {
        // Punët e kësaj kategorie mbeten, por pa kategori (ON DELETE SET NULL)
        $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
        flash('success', 'Kategoria u fshi. Punët e saj mbetën pa kategori.');
        redirect(url('admin/categories.php'));
    }
}

$categories = $pdo->query('SELECT cat.*, (SELECT COUNT(*) FROM jobs j WHERE j.category_id = cat.id) AS jobs FROM categories cat ORDER BY cat.name')->fetchAll();
$edit = null;
if ($eid = input_int('edit')) {
    foreach ($categories as $c) if ((int) $c['id'] === $eid) $edit = $c;
}
$v = is_post() ? $_POST : ($edit ?? []);

$pageTitle = 'Kategoritë';
require ROOT_PATH . '/includes/header.php';
?>
<div class="container dash-layout">
    <?php require ROOT_PATH . '/includes/sidebar.php'; ?>
    <section class="dash-content">
        <div class="dash-title"><div><h1>Kategoritë</h1><p>Kategoritë e vendeve të punës.</p></div></div>
        <?php if ($errors): ?>
            <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <div class="card mb-3">
            <h3><?= $edit || input_int('id') ? 'Edito kategorinë' : 'Shto kategori të re' ?></h3>
            <form method="post" data-validate novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? input_int('id')) ?>">
                <div class="form-row-3">
                    <div class="form-group"><label for="name">Emri <span class="req">*</span></label>
                        <input type="text" id="name" name="name" value="<?= e($v['name'] ?? '') ?>" required maxlength="100"></div>
                    <div class="form-group"><label for="icon">Ikona (emoji)</label>
                        <input type="text" id="icon" name="icon" value="<?= e($v['icon'] ?? '') ?>" maxlength="10" placeholder="💼"></div>
                    <div class="form-group"><label for="keywords">Fjalë kyçe për PunaBot</label>
                        <input type="text" id="keywords" name="keywords" value="<?= e($v['keywords'] ?? '') ?>" maxlength="255" placeholder="it,programim,developer"></div>
                </div>
                <div class="flex">
                    <button type="submit" class="btn btn-primary"><?= $edit ? 'Ruaj ndryshimet' : '+ Shto kategorinë' ?></button>
                    <?php if ($edit): ?><a href="<?= url('admin/categories.php') ?>" class="btn btn-outline">Anulo</a><?php endif; ?>
                </div>
            </form>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Kategoria</th><th>Slug</th><th>Punë</th><th>Statusi</th><th>Veprime</th></tr></thead>
                <tbody>
                <?php foreach ($categories as $c): ?>
                    <tr>
                        <td><span style="font-size:1.2rem"><?= e($c['icon'] ?: '💼') ?></span> <strong><?= e($c['name']) ?></strong><br><small><?= e(excerpt($c['keywords'], 60)) ?></small></td>
                        <td><code><?= e($c['slug']) ?></code></td>
                        <td><?= (int) $c['jobs'] ?></td>
                        <td><span class="badge badge-<?= $c['is_active'] ? 'yes' : 'no' ?>"><?= $c['is_active'] ? 'Aktive' : 'Joaktive' ?></span></td>
                        <td><div class="actions">
                            <a class="btn btn-outline btn-xs" href="?edit=<?= (int) $c['id'] ?>">Edito</a>
                            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <button class="btn btn-outline btn-xs" type="submit"><?= $c['is_active'] ? 'Çaktivizo' : 'Aktivizo' ?></button></form>
                            <form method="post" class="inline-form" data-confirm="Të fshihet kategoria <?= e($c['name']) ?>?"><?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <button class="btn btn-danger btn-xs" type="submit">Fshi</button></form>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
