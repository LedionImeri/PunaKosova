<?php
/**
 * Njoftimet e përdoruesit (të gjitha rolet)
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$uid = current_user_id();

if (is_post()) {
    verify_csrf();
    $action = input('action');
    $id = input_int('id');

    if ($action === 'read_all') {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$uid]);
        flash('success', 'Të gjitha njoftimet u shënuan si të lexuara.');
    } elseif ($action === 'delete_all') {
        $pdo->prepare('DELETE FROM notifications WHERE user_id = ? AND is_read = 1')->execute([$uid]);
        flash('success', 'Njoftimet e lexuara u fshinë.');
    } elseif ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
        flash('success', 'Njoftimi u fshi.');
    } elseif ($action === 'open' && $id) {
        // Shëno si të lexuar dhe hap linkun (vetëm linke të brendshme)
        $stmt = $pdo->prepare('SELECT link FROM notifications WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $uid]);
        $link = $stmt->fetchColumn();
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
        if ($link && $link[0] === '/') {
            redirect(url($link));
        }
    }
    redirect(url('notifications.php'));
}

$filter = input('filter') === 'unread' ? 'unread' : 'all';
$sql = 'SELECT * FROM notifications WHERE user_id = ?' . ($filter === 'unread' ? ' AND is_read = 0' : '') . ' ORDER BY created_at DESC, id DESC LIMIT 100';
$stmt = $pdo->prepare($sql);
$stmt->execute([$uid]);
$notifications = $stmt->fetchAll();

$icons = ['application' => '📨', 'status' => '📋', 'system' => '⚙️', 'user' => '👤', 'job' => '💼', 'warning' => '⚠️', 'info' => 'ℹ️'];

$pageTitle = 'Njoftimet';
require ROOT_PATH . '/includes/header.php';
?>
<div class="container dash-layout">
    <?php require ROOT_PATH . '/includes/sidebar.php'; ?>
    <section class="dash-content">
        <div class="dash-title">
            <div><h1>Njoftimet</h1><p>Aktivitetet më të fundit që kanë të bëjnë me ju.</p></div>
            <div class="flex">
                <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="read_all">
                    <button class="btn btn-outline btn-sm" type="submit">✓ Shëno të gjitha si të lexuara</button></form>
                <form method="post" class="inline-form" data-confirm="Të fshihen të gjitha njoftimet e lexuara?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_all">
                    <button class="btn btn-danger btn-sm" type="submit">Fshi të lexuarat</button></form>
            </div>
        </div>

        <div class="tabs">
            <a href="?filter=all" class="<?= $filter === 'all' ? 'active' : '' ?>">Të gjitha</a>
            <a href="?filter=unread" class="<?= $filter === 'unread' ? 'active' : '' ?>">Të palexuara</a>
        </div>

        <?php if (!$notifications): ?>
            <div class="card empty-state"><span class="emoji">🔔</span><h3>Nuk keni njoftime</h3><p>Këtu do të shfaqen njoftimet për aplikimet dhe aktivitetet tuaja.</p></div>
        <?php endif; ?>

        <?php foreach ($notifications as $n): ?>
            <div class="notif<?= $n['is_read'] ? '' : ' unread' ?>">
                <span class="notif-icon"><?= $icons[$n['type']] ?? '🔔' ?></span>
                <div class="notif-body">
                    <strong><?= e($n['title']) ?></strong>
                    <p><?= e($n['message']) ?></p>
                    <small><?= e(time_ago($n['created_at'])) ?></small>
                </div>
                <div class="flex">
                    <?php if ($n['link']): ?>
                        <form method="post" class="inline-form"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="open"><input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                            <button class="btn btn-outline btn-xs" type="submit">Hap</button></form>
                    <?php endif; ?>
                    <form method="post" class="inline-form"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                        <button class="btn btn-danger btn-xs" type="submit" title="Fshi">✕</button></form>
                </div>
            </div>
        <?php endforeach; ?>
    </section>
</div>
<?php
// Pasi u shfaqën, njoftimet e palexuara shënohen si të lexuara (badge-i rifreskohet në faqen tjetër)
$pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')->execute([$uid]);
require ROOT_PATH . '/includes/footer.php';
?>
