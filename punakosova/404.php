<?php
/**
 * Faqja 404 - nuk u gjet
 */
require_once __DIR__ . '/includes/bootstrap.php';
http_response_code(404);
$pageTitle = 'Faqja nuk u gjet';
require ROOT_PATH . '/includes/header.php';
?>
<div class="container error-page">
    <h1>404</h1>
    <h2>Faqja që kërkoni nuk ekziston</h2>
    <p class="muted">Mund të jetë fshirë, zhvendosur ose linku është i pasaktë.</p>
    <div class="flex" style="justify-content:center">
        <a class="btn btn-primary" href="<?= url('index.php') ?>">Kthehu në ballinë</a>
        <a class="btn btn-outline" href="<?= url('jobs/index.php') ?>">Shiko punët</a>
    </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
