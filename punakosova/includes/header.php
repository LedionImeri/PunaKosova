<?php
/**
 * Header i përbashkët: <head>, navbar dhe mesazhet flash.
 * Variabla opsionale: $pageTitle, $pageDescription
 */
$pageTitle = isset($pageTitle) ? $pageTitle . ' | ' . APP_NAME : APP_NAME . ' - Punë në Kosovë';
$pageDescription = $pageDescription ?? 'PunaKosova - platforma për kërkimin e vendeve të punës dhe aplikimet online në Kosovë.';
$script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$isActive = fn(string $needle) => strpos($script, $needle) !== false ? ' class="active"' : '';
$unread = unread_notifications($pdo);
$me = current_user();
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="base-url" content="<?= e(BASE_URL) ?>">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='22' fill='%23d62828'/><text x='50' y='68' font-size='56' text-anchor='middle' fill='white' font-family='Arial' font-weight='bold'>P</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/chatbot.css') ?>">
</head>
<body>
<header class="navbar">
    <div class="container nav-inner">
        <a href="<?= url('index.php') ?>" class="brand" aria-label="PunaKosova ballina">
            <span class="brand-mark">P</span><span class="brand-text">Puna<strong>Kosova</strong></span>
        </a>

        <button class="nav-toggle" type="button" aria-label="Hap menunë" aria-expanded="false" data-nav-toggle>
            <span></span><span></span><span></span>
        </button>

        <nav class="nav-menu" data-nav-menu>
            <a href="<?= url('index.php') ?>"<?= $script === BASE_URL . '/index.php' ? ' class="active"' : '' ?>>Ballina</a>
            <a href="<?= url('jobs/index.php') ?>"<?= $isActive('/jobs/') ?>>Punët</a>
            <a href="<?= url('companies.php') ?>"<?= $isActive('compan') && strpos($script, '/admin/') === false && strpos($script, '/employer/') === false ? ' class="active"' : '' ?>>Kompanitë</a>

            <?php if (has_role('candidate')): ?>
                <a href="<?= url('candidate/dashboard.php') ?>"<?= $isActive('/candidate/') ?>>Paneli im</a>
            <?php elseif (has_role('employer')): ?>
                <a href="<?= url('employer/dashboard.php') ?>"<?= $isActive('/employer/') ?>>Paneli i kompanisë</a>
                <a href="<?= url('employer/job_form.php') ?>" class="nav-cta-link">+ Publiko punë</a>
            <?php elseif (has_role('admin')): ?>
                <a href="<?= url('admin/dashboard.php') ?>"<?= $isActive('/admin/') ?>>Admin</a>
            <?php endif; ?>

            <div class="nav-actions">
                <?php if ($me): ?>
                    <a href="<?= url('notifications.php') ?>" class="icon-btn" title="Njoftimet" aria-label="Njoftimet">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <?php if ($unread > 0): ?><span class="notif-count"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?>
                    </a>
                    <div class="dropdown" data-dropdown>
                        <button type="button" class="user-chip" data-dropdown-toggle aria-haspopup="true">
                            <span class="avatar-sm"><?= e(initials($me['full_name'])) ?></span>
                            <span class="user-name"><?= e($me['full_name']) ?></span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <div class="dropdown-menu">
                            <div class="dropdown-header">
                                <strong><?= e($me['full_name']) ?></strong>
                                <small><?= e(label(role_labels(), $me['role'])) ?></small>
                            </div>
                            <a href="<?= dashboard_url() ?>">Paneli</a>
                            <?php if (has_role('candidate')): ?>
                                <a href="<?= url('candidate/profile.php') ?>">Profili im</a>
                                <a href="<?= url('candidate/applications.php') ?>">Aplikimet e mia</a>
                                <a href="<?= url('candidate/saved.php') ?>">Punët e ruajtura</a>
                            <?php elseif (has_role('employer')): ?>
                                <a href="<?= url('employer/company.php') ?>">Profili i kompanisë</a>
                                <a href="<?= url('employer/jobs.php') ?>">Shpalljet e mia</a>
                                <a href="<?= url('employer/applicants.php') ?>">Aplikantët</a>
                            <?php endif; ?>
                            <a href="<?= url('notifications.php') ?>">Njoftimet<?= $unread ? ' (' . $unread . ')' : '' ?></a>
                            <a href="<?= url('change_password.php') ?>">Ndrysho fjalëkalimin</a>
                            <form action="<?= url('logout.php') ?>" method="post">
                                <?= csrf_field() ?>
                                <button type="submit" class="dropdown-logout">Dil nga llogaria</button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?= url('login.php') ?>" class="btn btn-ghost btn-sm">Kyçu</a>
                    <a href="<?= url('register.php') ?>" class="btn btn-primary btn-sm">Regjistrohu</a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>

<div class="flash-wrap container">
    <?php foreach (get_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>" role="alert" data-autoclose>
            <span><?= e($f['message']) ?></span>
            <button type="button" class="alert-close" aria-label="Mbyll" data-close-alert>&times;</button>
        </div>
    <?php endforeach; ?>
</div>

<main class="site-main">
