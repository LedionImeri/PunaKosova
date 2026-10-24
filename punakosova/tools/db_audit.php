<?php
/**
 * PunaKosova - Auditi i databazës
 * ---------------------------------------------------------------
 * Hapeni: http://localhost/PunaKosova/tools/db_audit.php
 *
 * Krahason databazën nga config/config.php (DB_NAME, p.sh. punakosova2) me strukturën
 * që kërkon kodi aktual (database/punakosova.sql) dhe tregon saktësisht:
 *   - tabelat që mungojnë
 *   - kolonat që mungojnë
 *   - ENUM-et që nuk kanë vlerat që përdor kodi
 *   - kolonat NOT NULL pa vlerë default që do të prishnin INSERT-et
 *   - çelësat UNIQUE që mungojnë
 * dhe gjeneron SQL-in e saktë për t'i rregulluar.
 *
 * SIGURIA:
 *   - Funksionon vetëm nga localhost.
 *   - Si parazgjedhje është VETËM LEXIM. SQL-i ekzekutohet vetëm kur klikoni "Apliko".
 *   - SQL-i i gjeneruar vetëm SHTON (CREATE TABLE, ADD COLUMN, ADD UNIQUE) ose zgjeron
 *     (MODIFY që ruan vlerat ekzistuese). Nuk ka DROP, nuk fshihet asnjë rresht.
 *   - Prek VETËM databazën DB_NAME. Databaza "punakosova" (backup) vetëm lexohet.
 *   - Nuk është i nevojshëm që projekti të funksionojë; mund ta fshini pas përdorimit.
 */
require_once __DIR__ . '/../config/config.php';

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Ky mjet lejohet vetëm nga localhost.');
}
session_name('PUNAKOSOVA_AUDIT');
session_start();
if (empty($_SESSION['audit_token'])) {
    $_SESSION['audit_token'] = bin2hex(random_bytes(16));
}

const BACKUP_DB = 'punakosova';
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$q = fn($name) => '`' . str_replace('`', '``', $name) . '`';

// =====================================================================
// 1. Struktura e pritur – lexohet nga database/punakosova.sql (burimi i vetëm i së vërtetës)
// =====================================================================
function parse_schema(string $sql): array
{
    $schema = [];
    preg_match_all('/CREATE TABLE (\w+) \((.*?)\n\)\s*(ENGINE[^;]*);/s', $sql, $m, PREG_SET_ORDER);
    foreach ($m as $t) {
        [$all, $table, $body, $engine] = $t;
        $cols = [];
        $uniques = [];
        $createLines = [];
        foreach (explode("\n", $body) as $line) {
            $line = rtrim(trim($line), ',');
            if ($line === '') continue;
            if (preg_match('/^CONSTRAINT\b/i', $line)) continue;           // FK-të nuk shtohen në migrim
            $createLines[] = '    ' . $line;
            if (preg_match('/^UNIQUE KEY (\w+) \(([^)]+)\)/i', $line, $u)) {
                $uniques[$u[1]] = array_map('trim', explode(',', $u[2]));
            } elseif (!preg_match('/^(PRIMARY KEY|KEY|INDEX)\b/i', $line) && preg_match('/^(\w+)\s+(.+)$/', $line, $c)) {
                $cols[$c[1]] = $c[2];
            }
        }
        $schema[$table] = [
            'columns' => $cols,
            'uniques' => $uniques,
            'create'  => "CREATE TABLE IF NOT EXISTS `$table` (\n" . implode(",\n", $createLines) . "\n) $engine",
        ];
    }
    return $schema;
}

/** Definicion i sigurt për ADD COLUMN mbi një tabelë që ka rreshta. */
function safe_add_definition(string $def): string
{
    if (stripos($def, 'NOT NULL') !== false && stripos($def, 'DEFAULT') === false) {
        $def = preg_replace('/NOT NULL/i', 'NULL', $def, 1);
    }
    return $def;
}

function enum_values(string $type): ?array
{
    if (!preg_match('/^\s*enum\((.*)\)/i', $type, $m)) return null;
    preg_match_all("/'((?:[^'\\\\]|\\\\.|'')*)'/", $m[1], $v);
    return $v[1];
}

function expected_default(string $def): ?string
{
    if (preg_match("/DEFAULT\s+('(?:[^']*)'|[A-Z_]+(?:\(\))?|-?\d+(?:\.\d+)?)/i", $def, $m)) return $m[1];
    return null;
}

$schema = parse_schema((string) file_get_contents(__DIR__ . '/../database/punakosova.sql'));

// =====================================================================
// 2. Lidhja dhe leximi i strukturës aktuale (vetëm lexim)
// =====================================================================
$fatal = null;
$results = [];
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $server = $pdo->query('SELECT VERSION()')->fetchColumn();
    $dbExists = (bool) $pdo->query('SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ' . $pdo->quote(DB_NAME))->fetchColumn();
    if (!$dbExists) {
        throw new RuntimeException('Databaza `' . DB_NAME . '` nuk ekziston. Krijojeni në phpMyAdmin (utf8mb4_unicode_ci) dhe rifreskoni.');
    }
    $pdo->exec('USE ' . $q(DB_NAME));
} catch (Throwable $e) {
    $fatal = $e->getMessage();
}

/** Ndërton listën e problemeve dhe SQL-in rregullues. */
function audit(PDO $pdo, array $schema): array
{
    $q = fn($n) => '`' . str_replace('`', '``', $n) . '`';
    $db = DB_NAME;

    $cols = [];
    $st = $pdo->prepare('SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
        FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION');
    $st->execute([$db]);
    foreach ($st->fetchAll() as $r) {
        $cols[strtolower($r['TABLE_NAME'])][strtolower($r['COLUMN_NAME'])] = $r;
    }
    $idx = [];
    $st = $pdo->prepare('SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, COLUMN_NAME FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX');
    $st->execute([$db]);
    foreach ($st->fetchAll() as $r) {
        if ((int) $r['NON_UNIQUE'] === 0) {
            $idx[strtolower($r['TABLE_NAME'])][$r['INDEX_NAME']][] = strtolower($r['COLUMN_NAME']);
        }
    }

    $issues = [];   // [lloji, tabela, përshkrimi]
    $sql = ['create' => [], 'add' => [], 'modify' => [], 'data' => [], 'unique' => [], 'manual' => []];
    $added = [];    // kolonat që do të shtohen: "table.col"

    foreach ($schema as $table => $def) {
        if (!isset($cols[$table])) {
            $issues[] = ['Tabelë që mungon', $table, 'Tabela nuk ekziston.'];
            $sql['create'][] = $def['create'] . ';';
            foreach ($def['columns'] as $c => $_) $added["$table.$c"] = true;
            continue;
        }
        $have = $cols[$table];
        foreach ($def['columns'] as $c => $cdef) {
            if (!isset($have[$c])) {
                if (stripos($cdef, 'AUTO_INCREMENT') !== false) {
                    $issues[] = ['Kolonë që mungon', $table, "`$c` (çelësi primar) mungon – duhet rregulluar manualisht."];
                    $sql['manual'][] = "-- Tabela `$table` nuk ka kolonën primare `$c`. Kontrolloni cila kolonë përdoret si ID.";
                    continue;
                }
                $issues[] = ['Kolonë që mungon', $table, "`$c` $cdef"];
                $sql['add'][] = 'ALTER TABLE ' . $q($table) . ' ADD COLUMN ' . $q($c) . ' ' . safe_add_definition($cdef) . ';';
                $added["$table.$c"] = true;
                continue;
            }
            $cur = $have[$c];
            // ENUM: vlerat që kodi përdor duhet të ekzistojnë (ruhen edhe vlerat e vjetra)
            $want = enum_values($cdef);
            $got = enum_values($cur['COLUMN_TYPE']);
            if ($want !== null && $got !== null && array_diff($want, $got)) {
                $union = array_values(array_unique(array_merge($want, $got)));
                $issues[] = ['ENUM jo i plotë', $table, "`$c` ka (" . implode(', ', $got) . '), mungojnë: ' . implode(', ', array_diff($want, $got))];
                $d = expected_default($cdef);
                $sql['modify'][] = 'ALTER TABLE ' . $q($table) . ' MODIFY ' . $q($c) . " ENUM('" . implode("','", array_map(fn($v) => str_replace("'", "''", $v), $union)) . "') "
                    . ($cur['IS_NULLABLE'] === 'YES' ? 'NULL' : 'NOT NULL') . ($d !== null ? " DEFAULT $d" : '') . ';';
                continue;
            }
            // NOT NULL pa default, ndërsa kodi pret default / NULL
            $curNoDefault = $cur['IS_NULLABLE'] === 'NO' && $cur['COLUMN_DEFAULT'] === null && stripos($cur['EXTRA'], 'auto_increment') === false;
            if ($curNoDefault) {
                $d = expected_default($cdef);
                $expectNullable = stripos($cdef, 'NOT NULL') === false;
                if ($expectNullable) {
                    $issues[] = ['NOT NULL i tepërt', $table, "`$c` është NOT NULL, por kodi mund ta lërë bosh (NULL)."];
                    $sql['modify'][] = 'ALTER TABLE ' . $q($table) . ' MODIFY ' . $q($c) . ' ' . $cur['COLUMN_TYPE'] . ' NULL;';
                } elseif ($d !== null) {
                    $issues[] = ['Default që mungon', $table, "`$c` nuk ka vlerë default ($d) që kodi e pret."];
                    $sql['modify'][] = 'ALTER TABLE ' . $q($table) . ' MODIFY ' . $q($c) . ' ' . $cur['COLUMN_TYPE'] . " NOT NULL DEFAULT $d;";
                }
            }
        }
        // Kolona të vjetra NOT NULL pa default që kodi nuk i njeh -> INSERT-et do të dështonin
        foreach ($have as $c => $cur) {
            if (isset($def['columns'][$c])) continue;
            if ($cur['IS_NULLABLE'] === 'NO' && $cur['COLUMN_DEFAULT'] === null && stripos($cur['EXTRA'], 'auto_increment') === false) {
                $issues[] = ['Kolonë e vjetër bllokuese', $table, "`$c` ({$cur['COLUMN_TYPE']}) është NOT NULL pa default dhe nuk përdoret nga kodi – INSERT-et do të dështojnë."];
                $sql['modify'][] = 'ALTER TABLE ' . $q($table) . ' MODIFY ' . $q($c) . ' ' . $cur['COLUMN_TYPE'] . ' NULL;';
            }
        }
    }

    // ---- Migrimi i të dhënave (vetëm kur ka kuptim) ----
    $has = fn($t, $c) => isset($cols[$t][$c]);
    $adding = fn($t, $c) => isset($added["$t.$c"]);
    if ($adding('users', 'password_hash') && $has('users', 'password')) {
        $sql['data'][] = "UPDATE `users` SET `password_hash` = `password` WHERE (`password_hash` IS NULL OR `password_hash` = '') AND `password` LIKE '\$2y\$%';";
    }
    if ($adding('users', 'full_name')) {
        if ($has('users', 'name')) $sql['data'][] = "UPDATE `users` SET `full_name` = `name` WHERE `full_name` IS NULL;";
        elseif ($has('users', 'first_name') && $has('users', 'last_name')) $sql['data'][] = "UPDATE `users` SET `full_name` = CONCAT_WS(' ', `first_name`, `last_name`) WHERE `full_name` IS NULL;";
        elseif ($has('users', 'username')) $sql['data'][] = "UPDATE `users` SET `full_name` = `username` WHERE `full_name` IS NULL;";
    }
    if ($adding('companies', 'name') && $has('companies', 'company_name')) {
        $sql['data'][] = "UPDATE `companies` SET `name` = `company_name` WHERE `name` IS NULL;";
    }
    if ($adding('jobs', 'city') && $has('jobs', 'location')) {
        $sql['data'][] = "UPDATE `jobs` SET `city` = `location` WHERE `city` IS NULL;";
    }
    if ($adding('jobs', 'company_id')) {
        foreach (['employer_id', 'user_id'] as $old) {
            if ($has('jobs', $old) && $has('companies', 'user_id')) {
                $sql['data'][] = "UPDATE `jobs` j JOIN `companies` c ON c.`user_id` = j.`$old` SET j.`company_id` = c.`id` WHERE j.`company_id` IS NULL;";
                break;
            }
        }
    }
    if ($adding('applications', 'candidate_id') && $has('applications', 'user_id')) {
        $sql['data'][] = "UPDATE `applications` SET `candidate_id` = `user_id` WHERE `candidate_id` IS NULL;";
    }
    if (isset($cols['jobs']) && ($adding('jobs', 'deadline') || $pdo->query('SELECT COUNT(*) FROM `jobs` WHERE `deadline` IS NULL')->fetchColumn())) {
        $sql['data'][] = 'UPDATE `jobs` SET `deadline` = DATE_ADD(CURDATE(), INTERVAL 30 DAY) WHERE `deadline` IS NULL;';
    }
    if (isset($cols['categories']) && ($adding('categories', 'slug') || (isset($cols['categories']['slug']) && $pdo->query("SELECT COUNT(*) FROM `categories` WHERE `slug` IS NULL OR `slug` = ''")->fetchColumn()))) {
        $sql['data'][] = "UPDATE `categories` SET `slug` = CONCAT('kategori-', `id`) WHERE `slug` IS NULL OR `slug` = '';";
    }
    if ($sql['data']) {
        $issues[] = ['Migrim të dhënash', '-', count($sql['data']) . ' UPDATE për të mbushur kolonat e reja nga të dhënat ekzistuese.'];
    }

    // ---- Çelësat UNIQUE ----
    foreach ($schema as $table => $def) {
        if (!isset($cols[$table])) continue;   // tabela e re i krijon vetë
        foreach ($def['uniques'] as $name => $ucols) {
            $found = false;
            foreach ($idx[$table] ?? [] as $icols) {
                if ($icols === array_map('strtolower', $ucols)) { $found = true; break; }
            }
            if ($found) continue;
            $missingCol = array_filter($ucols, fn($c) => !isset($cols[$table][$c]) && !isset($added["$table.$c"]));
            if ($missingCol) continue;
            $dupes = 0;
            if (!array_filter($ucols, fn($c) => isset($added["$table.$c"]))) {
                $list = implode(', ', array_map($q, $ucols));
                $dupes = (int) $pdo->query("SELECT COUNT(*) FROM (SELECT $list FROM " . $q($table) . " GROUP BY $list HAVING COUNT(*) > 1) d")->fetchColumn();
            }
            if ($dupes) {
                $issues[] = ['UNIQUE që mungon', $table, "($ucols[0]" . (count($ucols) > 1 ? ', ' . implode(', ', array_slice($ucols, 1)) : '') . ") – ka $dupes vlera të dyfishta, duhen pastruar manualisht."];
                $on = implode(' AND ', array_map(fn($c) => "a.`$c` = b.`$c`", $ucols));
                $sql['manual'][] = "-- `$table`: ka rreshta të dyfishtë për (" . implode(', ', $ucols) . '). Kontrollojini, pastaj (nëse jeni dakord) ekzekutoni:';
                $sql['manual'][] = "-- DELETE a FROM `$table` a JOIN `$table` b ON $on AND a.`id` > b.`id`;   -- mban rreshtin më të vjetër";
                $sql['manual'][] = '-- ALTER TABLE `' . $table . '` ADD UNIQUE KEY `' . $name . '` (' . implode(', ', $ucols) . ');';
            } else {
                $issues[] = ['UNIQUE që mungon', $table, '(' . implode(', ', $ucols) . ')'];
                $sql['unique'][] = 'ALTER TABLE ' . $q($table) . ' ADD UNIQUE KEY ' . $q($name) . ' (' . implode(', ', array_map($q, $ucols)) . ');';
            }
        }
    }
    return [$issues, $sql];
}

// =====================================================================
// 3. Aplikimi (vetëm me klikim + token)
// =====================================================================
$applyLog = [];
if (!$fatal && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && hash_equals($_SESSION['audit_token'], $_POST['token'] ?? '')) {
    if (DB_NAME === BACKUP_DB) {
        $applyLog[] = ['err', 'DB_NAME është "' . BACKUP_DB . '" (backup). Ndryshimet nuk aplikohen në këtë databazë.'];
    } else {
        [, $sqlNow] = audit($pdo, $schema);
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['create', 'add', 'modify', 'data', 'unique'] as $group) {
            foreach ($sqlNow[$group] as $stmt) {
                try {
                    $n = $pdo->exec($stmt);
                    $applyLog[] = ['ok', $stmt . ($group === 'data' ? "  → $n rreshta" : '')];
                } catch (PDOException $e) {
                    $applyLog[] = ['err', $stmt . '  → ' . $e->getMessage()];
                }
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}

// =====================================================================
// 4. Raporti
// =====================================================================
$issues = [];
$sql = [];
$tablesInfo = [];
$backupInfo = null;
$stats = [];
if (!$fatal) {
    [$issues, $sql] = audit($pdo, $schema);
    $st = $pdo->prepare('SELECT TABLE_NAME, TABLE_ROWS FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME');
    $st->execute([DB_NAME]);
    $tablesInfo = $st->fetchAll(PDO::FETCH_KEY_PAIR);

    // Kontroll vetëm-lexim i databazës backup
    $bt = $pdo->prepare('SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?');
    $bt->execute([BACKUP_DB]);
    $backupTables = array_map('strtolower', $bt->fetchAll(PDO::FETCH_COLUMN));
    if ($backupTables) {
        $newMarkers = array_intersect(['educations', 'experiences', 'chatbot_messages'], $backupTables);
        $testUsers = 0;
        if (in_array('users', $backupTables, true)) {
            try {
                $testUsers = (int) $pdo->query("SELECT COUNT(*) FROM `" . BACKUP_DB . "`.`users` WHERE email IN ('admin@punakosova.com','kandidat@punakosova.com','punedhenes@punakosova.com')")->fetchColumn();
            } catch (Throwable $e) {
                $testUsers = 0;
            }
        }
        $backupInfo = ['tables' => count($backupTables), 'markers' => $newMarkers, 'testUsers' => $testUsers];
    }

    // Statistika të shpejta (vetëm nëse struktura është e plotë)
    if (!$issues) {
        try {
            $stats = [
                'Përdorues' => $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
                'Përdorues pa fjalëkalim (s\'mund të kyçen)' => $pdo->query("SELECT COUNT(*) FROM users WHERE password_hash IS NULL OR password_hash = ''")->fetchColumn(),
                'Kompani' => $pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn(),
                'Punëdhënës pa kompani' => $pdo->query("SELECT COUNT(*) FROM users u LEFT JOIN companies c ON c.user_id = u.id WHERE u.role = 'employer' AND c.id IS NULL")->fetchColumn(),
                'Punë gjithsej' => $pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn(),
                'Punë që shfaqen publikisht' => $pdo->query("SELECT COUNT(*) FROM jobs j JOIN companies c ON c.id = j.company_id JOIN users u ON u.id = c.user_id WHERE j.status = 'active' AND j.deadline >= CURDATE() AND c.is_active = 1 AND u.is_active = 1")->fetchColumn(),
                'Kandidatë pa rresht në user_profiles' => $pdo->query("SELECT COUNT(*) FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id WHERE u.role = 'candidate' AND p.id IS NULL")->fetchColumn(),
            ];
        } catch (Throwable $e) {
            $stats = ['Gabim gjatë statistikave' => $e->getMessage()];
        }
    }
}
$allSql = [];
foreach (['create' => 'Tabelat që mungojnë', 'add' => 'Kolonat që mungojnë', 'modify' => 'Rregullime tipesh / ENUM / NULL', 'data' => 'Migrimi i të dhënave', 'unique' => 'Çelësat UNIQUE', 'manual' => 'Kërkojnë kontroll manual'] as $k => $title) {
    if (!empty($sql[$k])) {
        $allSql[] = "-- ---- $title ----";
        foreach ($sql[$k] as $s) $allSql[] = $s;
    }
}
$fixSql = $allSql ? "-- SQL rregullues për databazën `" . DB_NAME . "` (gjeneruar " . date('d.m.Y H:i') . ")\nUSE `" . DB_NAME . "`;\nSET FOREIGN_KEY_CHECKS = 0;\n" . implode("\n", $allSql) . "\nSET FOREIGN_KEY_CHECKS = 1;\n" : '';
$autoCount = count($sql['create'] ?? []) + count($sql['add'] ?? []) + count($sql['modify'] ?? []) + count($sql['data'] ?? []) + count($sql['unique'] ?? []);
?>
<!DOCTYPE html>
<html lang="sq">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Auditi i databazës | PunaKosova</title>
<style>
    body { font-family: Segoe UI, Arial, sans-serif; background: #f6f7f9; color: #1f2937; margin: 0; padding: 32px 16px; }
    .box { max-width: 1000px; margin: 0 auto 18px; background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 24px; }
    h1 { margin: 0 0 6px; } h1 span { color: #d62828; } h2 { margin-top: 0; font-size: 1.15rem; }
    .ok { background: #dcfce7; color: #166534; padding: 12px 14px; border-radius: 9px; margin: 8px 0; }
    .err { background: #fee2e2; color: #991b1b; padding: 12px 14px; border-radius: 9px; margin: 8px 0; }
    .warn { background: #fef3c7; color: #92400e; padding: 12px 14px; border-radius: 9px; margin: 8px 0; }
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #eee; vertical-align: top; }
    th { background: #fafafa; font-size: 12px; text-transform: uppercase; color: #6b7280; }
    textarea { width: 100%; min-height: 260px; font-family: Consolas, monospace; font-size: 13px; padding: 12px; border: 1px solid #d1d5db; border-radius: 9px; box-sizing: border-box; }
    button { background: #d62828; color: #fff; border: 0; padding: 12px 20px; border-radius: 9px; font-size: 15px; font-weight: 600; cursor: pointer; }
    button.sec { background: #111827; }
    code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; }
    .log { font-family: Consolas, monospace; font-size: 12.5px; white-space: pre-wrap; padding: 6px 10px; border-radius: 6px; margin: 3px 0; }
    .log.ok { margin: 3px 0; padding: 6px 10px; } .log.err { margin: 3px 0; padding: 6px 10px; }
</style>
</head>
<body>
<div class="box">
    <h1>Auditi i databazës – Puna<span>Kosova</span></h1>
    <p>Databaza e projektit (<code>DB_NAME</code>): <b><?= $h(DB_NAME) ?></b> · Serveri: <code><?= $h($server ?? '-') ?></code>
       · Struktura e pritur: <code>database/punakosova.sql</code> (<?= count($schema) ?> tabela, <?= array_sum(array_map(fn($t) => count($t['columns']), $schema)) ?> kolona)</p>
    <?php if (DB_NAME === BACKUP_DB): ?>
        <div class="err"><b>Kujdes:</b> <code>DB_NAME</code> është <b><?= BACKUP_DB ?></b> (databaza backup). Vendoseni <code>punakosova2</code> në <code>config/config.php</code>. Ky mjet nuk aplikon ndryshime në backup.</div>
    <?php endif; ?>
    <?php if ($fatal): ?><div class="err"><?= $h($fatal) ?></div><?php endif; ?>
</div>

<?php if ($applyLog): ?>
<div class="box">
    <h2>Rezultati i aplikimit</h2>
    <?php foreach ($applyLog as [$type, $msg]): ?><div class="log <?= $type ?>"><?= $h($msg) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$fatal): ?>
<div class="box">
    <h2>1. Rezultati i auditit për <code><?= $h(DB_NAME) ?></code></h2>
    <?php if (!$issues): ?>
        <div class="ok"><b>✔ Databaza përputhet plotësisht me kodin.</b> Të gjitha <?= count($schema) ?> tabelat, kolonat, vlerat ENUM dhe çelësat UNIQUE ekzistojnë. Projekti nuk ka nevojë për këtë mjet; mund ta fshini folderin <code>tools/</code>.</div>
        <?php if ($stats): ?>
            <table><?php foreach ($stats as $k => $v): ?><tr><td><?= $h($k) ?></td><td><b><?= $h($v) ?></b></td></tr><?php endforeach; ?></table>
        <?php endif; ?>
    <?php else: ?>
        <div class="warn">U gjetën <b><?= count($issues) ?></b> mospërputhje. Këto shkaktojnë gabime si <code>Unknown column</code>.</div>
        <table>
            <tr><th>Lloji</th><th>Tabela</th><th>Detaje</th></tr>
            <?php foreach ($issues as [$type, $table, $detail]): ?>
                <tr><td><?= $h($type) ?></td><td><code><?= $h($table) ?></code></td><td><?= $h($detail) ?></td></tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<?php if ($fixSql): ?>
<div class="box">
    <h2>2. SQL rregullues (vetëm shton/zgjeron – nuk fshin asgjë)</h2>
    <p>Mund ta kopjoni dhe ekzekutoni te phpMyAdmin → <b><?= $h(DB_NAME) ?></b> → <b>SQL</b>, ose ta aplikoni direkt me butonin më poshtë.
       Rekomandohet: <b>Export</b> i <code><?= $h(DB_NAME) ?></code> para aplikimit.</p>
    <textarea readonly onclick="this.select()"><?= $h($fixSql) ?></textarea>
    <?php if ($autoCount && DB_NAME !== BACKUP_DB): ?>
        <form method="post" onsubmit="return confirm('Të aplikohen <?= $autoCount ?> ndryshime në databazën <?= $h(DB_NAME) ?>? Nuk fshihet asnjë e dhënë.');">
            <input type="hidden" name="token" value="<?= $h($_SESSION['audit_token']) ?>">
            <p><button type="submit">Apliko <?= $autoCount ?> rregullime në <?= $h(DB_NAME) ?></button></p>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="box">
    <h2>3. Tabelat aktuale në <code><?= $h(DB_NAME) ?></code></h2>
    <table>
        <tr><th>Tabela</th><th>Rreshta (afërsisht)</th><th>Përdoret nga kodi</th></tr>
        <?php foreach ($tablesInfo as $t => $rows): ?>
            <tr><td><code><?= $h($t) ?></code></td><td><?= (int) $rows ?></td><td><?= isset($schema[strtolower($t)]) ? '✔' : '— (tabelë e vjetër, nuk preket)' ?></td></tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="box">
    <h2>4. Databaza backup <code><?= BACKUP_DB ?></code> (vetëm lexim)</h2>
    <?php if ($backupInfo === null): ?>
        <div class="warn">Databaza <code><?= BACKUP_DB ?></code> nuk u gjet në këtë server.</div>
    <?php elseif ($backupInfo['markers'] && $backupInfo['testUsers']): ?>
        <div class="err"><b>Kujdes:</b> <code><?= BACKUP_DB ?></code> përmban tabelat e versionit të ri (<?= $h(implode(', ', $backupInfo['markers'])) ?>) dhe përdoruesit testues
            (p.sh. <code>admin@punakosova.com</code>). Kjo tregon se mund të jetë mbishkruar nga një import i mëhershëm i <code>punakosova.sql</code>
            (versioni i mëparshëm i atij file-i fillonte me <code>DROP DATABASE punakosova</code>). Kontrolloni nëse keni një eksport/backup tjetër të të dhënave të vjetra.</div>
    <?php else: ?>
        <div class="ok"><code><?= BACKUP_DB ?></code> ekziston (<?= (int) $backupInfo['tables'] ?> tabela) dhe nuk duket e mbishkruar. Ky mjet nuk e prek.</div>
    <?php endif; ?>
</div>
<?php endif; ?>
</body>
</html>
