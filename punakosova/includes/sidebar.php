<?php
/**
 * Menuja anësore e panelit sipas rolit të përdoruesit.
 */
$script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$menus = [
    'candidate' => [
        ['candidate/dashboard.php',    '🏠', 'Paneli'],
        ['candidate/profile.php',      '👤', 'Profili & CV'],
        ['candidate/applications.php', '📨', 'Aplikimet e mia'],
        ['candidate/saved.php',        '❤️', 'Punët e ruajtura'],
        ['jobs/index.php',             '🔍', 'Kërko punë'],
        ['notifications.php',          '🔔', 'Njoftimet'],
        ['change_password.php',        '🔒', 'Fjalëkalimi'],
    ],
    'employer' => [
        ['employer/dashboard.php',  '🏠', 'Paneli'],
        ['employer/company.php',    '🏢', 'Profili i kompanisë'],
        ['employer/jobs.php',       '📋', 'Shpalljet e mia'],
        ['employer/job_form.php',   '➕', 'Publiko punë'],
        ['employer/applicants.php', '👥', 'Aplikantët'],
        ['notifications.php',       '🔔', 'Njoftimet'],
        ['change_password.php',     '🔒', 'Fjalëkalimi'],
    ],
    'admin' => [
        ['admin/dashboard.php',    '📊', 'Paneli'],
        ['admin/users.php',        '👥', 'Përdoruesit'],
        ['admin/companies.php',    '🏢', 'Kompanitë'],
        ['admin/jobs.php',         '📋', 'Vendet e punës'],
        ['admin/categories.php',   '🗂️', 'Kategoritë'],
        ['admin/applications.php', '📨', 'Aplikimet'],
        ['notifications.php',      '🔔', 'Njoftimet'],
        ['change_password.php',    '🔒', 'Fjalëkalimi'],
    ],
];
$items = $menus[current_role()] ?? [];
?>
<aside class="sidebar">
    <div class="sidebar-user">
        <span class="avatar"><?= e(initials($_SESSION['full_name'] ?? '?')) ?></span>
        <div>
            <strong><?= e($_SESSION['full_name'] ?? '') ?></strong>
            <small><?= e(label(role_labels(), current_role())) ?></small>
        </div>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($items as [$path, $icon, $text]):
            $active = substr($script, -strlen('/' . $path)) === '/' . $path; ?>
            <a href="<?= url($path) ?>" class="<?= $active ? 'active' : '' ?>"><span class="ico"><?= $icon ?></span><?= e($text) ?></a>
        <?php endforeach; ?>
    </nav>
</aside>
