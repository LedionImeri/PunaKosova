-- =====================================================================
--  PunaKosova - Platformë për punësim në Kosovë
--  Databaza MySQL / MariaDB (XAMPP)
--
--  KY FILE NUK KRIJON, NUK ZGJEDH DHE NUK FSHIN ASNJË DATABAZË.
--  Përdorimi (për një databazë BOSH):
--    1. phpMyAdmin -> krijoni/zgjidhni databazën (p.sh. punakosova2, utf8mb4_unicode_ci)
--    2. Me databazën e zgjedhur -> Import -> ky file
--  Nëse databaza ka tashmë tabela, importi ndalon me gabim "Table already exists"
--  dhe NUK prek të dhënat ekzistuese. Për të përditësuar një databazë ekzistuese
--  përdorni tools/db_audit.php (shton vetëm tabelat/kolonat që mungojnë).
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. Përdoruesit (të tri rolet)
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name     VARCHAR(120) NOT NULL,
    email         VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('candidate','employer','admin') NOT NULL DEFAULT 'candidate',
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    last_login    DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. Profili i kandidatit (1:1 me users)
-- ---------------------------------------------------------------------
CREATE TABLE user_profiles (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED NOT NULL,
    phone            VARCHAR(30)  NULL,
    city             VARCHAR(80)  NULL,
    birth_date       DATE         NULL,
    headline         VARCHAR(150) NULL,
    bio              TEXT         NULL,
    skills           TEXT         NULL,
    cv_file          VARCHAR(255) NULL,
    cv_original_name VARCHAR(255) NULL,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_profiles_user (user_id),
    CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. Edukimi i kandidatit (1:N)
-- ---------------------------------------------------------------------
CREATE TABLE educations (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED NOT NULL,
    institution    VARCHAR(150) NOT NULL,
    degree         VARCHAR(120) NOT NULL,
    field_of_study VARCHAR(120) NULL,
    start_year     SMALLINT UNSIGNED NOT NULL,
    end_year       SMALLINT UNSIGNED NULL,
    description    TEXT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_edu_user (user_id),
    CONSTRAINT fk_edu_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. Përvoja e punës e kandidatit (1:N)
-- ---------------------------------------------------------------------
CREATE TABLE experiences (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    company_name VARCHAR(150) NOT NULL,
    position     VARCHAR(120) NOT NULL,
    start_date   DATE NOT NULL,
    end_date     DATE NULL,
    is_current   TINYINT(1) NOT NULL DEFAULT 0,
    description  TEXT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_exp_user (user_id),
    CONSTRAINT fk_exp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. Kompanitë (1:1 me punëdhënësin)
-- ---------------------------------------------------------------------
CREATE TABLE companies (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    name        VARCHAR(150) NOT NULL,
    logo        VARCHAR(255) NULL,
    description TEXT NULL,
    industry    VARCHAR(100) NULL,
    city        VARCHAR(80)  NULL,
    address     VARCHAR(200) NULL,
    phone       VARCHAR(30)  NULL,
    email       VARCHAR(150) NULL,
    website     VARCHAR(200) NULL,
    employees   VARCHAR(30)  NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_companies_user (user_id),
    KEY idx_companies_city (city),
    CONSTRAINT fk_companies_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. Kategoritë
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(100) NOT NULL,
    slug       VARCHAR(120) NOT NULL,
    icon       VARCHAR(10)  NULL,
    keywords   VARCHAR(255) NULL COMMENT 'Fjalë kyçe për chatbot-in (me presje)',
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_name (name),
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. Vendet e punës
-- ---------------------------------------------------------------------
CREATE TABLE jobs (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id       INT UNSIGNED NOT NULL,
    category_id      INT UNSIGNED NULL,
    title            VARCHAR(150) NOT NULL,
    description      TEXT NOT NULL,
    requirements     TEXT NULL,
    benefits         TEXT NULL,
    city             VARCHAR(80) NOT NULL,
    employment_type  ENUM('full_time','part_time','contract','internship','remote') NOT NULL DEFAULT 'full_time',
    experience_level ENUM('entry','mid','senior') NOT NULL DEFAULT 'entry',
    salary_min       DECIMAL(10,2) NULL,
    salary_max       DECIMAL(10,2) NULL,
    deadline         DATE NOT NULL,
    status           ENUM('active','closed','blocked') NOT NULL DEFAULT 'active',
    views            INT UNSIGNED NOT NULL DEFAULT 0,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_jobs_status (status),
    KEY idx_jobs_city (city),
    KEY idx_jobs_deadline (deadline),
    KEY idx_jobs_company (company_id),
    KEY idx_jobs_category (category_id),
    CONSTRAINT fk_jobs_company  FOREIGN KEY (company_id)  REFERENCES companies(id)  ON DELETE CASCADE,
    CONSTRAINT fk_jobs_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. Aplikimet (një kandidat aplikon vetëm një herë për një punë)
-- ---------------------------------------------------------------------
CREATE TABLE applications (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id           INT UNSIGNED NOT NULL,
    candidate_id     INT UNSIGNED NOT NULL,
    cover_letter     TEXT NOT NULL,
    cv_file          VARCHAR(255) NOT NULL,
    cv_original_name VARCHAR(255) NULL,
    status           ENUM('pending','reviewing','accepted','rejected') NOT NULL DEFAULT 'pending',
    employer_note    TEXT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_app_job_candidate (job_id, candidate_id),
    KEY idx_app_candidate (candidate_id),
    KEY idx_app_status (status),
    CONSTRAINT fk_app_job       FOREIGN KEY (job_id)       REFERENCES jobs(id)  ON DELETE CASCADE,
    CONSTRAINT fk_app_candidate FOREIGN KEY (candidate_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. Punët e ruajtura (të preferuarat)
-- ---------------------------------------------------------------------
CREATE TABLE saved_jobs (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    job_id     INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_saved_user_job (user_id, job_id),
    KEY idx_saved_job (job_id),
    CONSTRAINT fk_saved_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_saved_job  FOREIGN KEY (job_id)  REFERENCES jobs(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. Njoftimet
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    type       VARCHAR(40)  NOT NULL DEFAULT 'info',
    title      VARCHAR(150) NOT NULL,
    message    VARCHAR(500) NOT NULL,
    link       VARCHAR(255) NULL,
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notif_user_read (user_id, is_read),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. Mesazhet e chatbot-it PunaBot
-- ---------------------------------------------------------------------
CREATE TABLE chatbot_messages (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NULL,
    session_id VARCHAR(128) NOT NULL,
    sender     ENUM('user','bot') NOT NULL,
    message    TEXT NOT NULL,
    language   CHAR(2) NOT NULL DEFAULT 'sq',
    source     VARCHAR(20) NOT NULL DEFAULT 'user' COMMENT 'user | ai | fallback',
    ip_address VARCHAR(45) NULL,
    is_hidden  TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = fshehur nga përdoruesi (Pastro bisedën)',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_chat_session (session_id, created_at),
    KEY idx_chat_user (user_id, created_at),
    KEY idx_chat_ip (ip_address, created_at),
    CONSTRAINT fk_chat_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
--  TË DHËNA TESTUESE
--  Admin:       admin@punakosova.com       / Admin123!
--  Kandidat:    kandidat@punakosova.com    / Kandidat123!
--  Punëdhënës:  punedhenes@punakosova.com  / Punedhenes123!
--  (Të gjithë kandidatët testues kanë Kandidat123!, punëdhënësit Punedhenes123!)
-- =====================================================================

INSERT INTO users (id, full_name, email, password_hash, role) VALUES
(1, 'Administratori',     'admin@punakosova.com',      '$2y$10$CDW5EpGDYwcsgp5/TUsPcOBIviNnt5CBiUGN0wtY0jHXiMTOwWn.G', 'admin'),
(2, 'Arben Krasniqi',     'kandidat@punakosova.com',   '$2y$10$XrQbNX/qajvC0o8PhBtAee5Spqlib72OZCg4rpFPbiabPag1bvjju', 'candidate'),
(3, 'Drita Berisha',      'drita@punakosova.com',      '$2y$10$XrQbNX/qajvC0o8PhBtAee5Spqlib72OZCg4rpFPbiabPag1bvjju', 'candidate'),
(4, 'Blerim Gashi',       'punedhenes@punakosova.com', '$2y$10$01X6C.JvKwhe6pT3EZy6SOiILtX6/OifOn70gBD1B.03oMT89U5IS', 'employer'),
(5, 'Vjosa Hoxha',        'vjosa@punakosova.com',      '$2y$10$01X6C.JvKwhe6pT3EZy6SOiILtX6/OifOn70gBD1B.03oMT89U5IS', 'employer');

INSERT INTO user_profiles (user_id, phone, city, birth_date, headline, bio, skills) VALUES
(2, '+383 44 123 456', 'Prishtinë', '1999-05-14', 'Zhvillues Web Junior',
 'Diplomuar në Shkenca Kompjuterike. I pasionuar pas zhvillimit të aplikacioneve web dhe teknologjive të reja.',
 'PHP, MySQL, JavaScript, HTML, CSS, Git, Laravel'),
(3, '+383 45 987 654', 'Prizren', '1997-11-02', 'Kontabiliste',
 'Përvojë 3-vjeçare në kontabilitet dhe financa për biznese të vogla dhe të mesme.',
 'Kontabilitet, Excel, QuickBooks, Raportim financiar');

INSERT INTO educations (user_id, institution, degree, field_of_study, start_year, end_year, description) VALUES
(2, 'Universiteti i Prishtinës', 'Bachelor', 'Shkenca Kompjuterike', 2018, 2021, 'Projekt diplome: aplikacion web për menaxhim të bibliotekës.'),
(3, 'Universiteti i Prizrenit', 'Bachelor', 'Ekonomi - Kontabilitet', 2015, 2018, NULL);

INSERT INTO experiences (user_id, company_name, position, start_date, end_date, is_current, description) VALUES
(2, 'WebStudio Prishtina', 'Praktikant Web Developer', '2021-07-01', '2022-01-31', 0, 'Zhvillim i faqeve web me PHP dhe WordPress.'),
(3, 'Zyra Kontabël Prizreni', 'Kontabiliste', '2019-03-01', NULL, 1, 'Mbajtja e librave kontabël dhe deklarimet tatimore.');

INSERT INTO companies (id, user_id, name, description, industry, city, address, phone, email, website, employees, is_verified) VALUES
(1, 4, 'TechKos Solutions', 'TechKos Solutions është kompani softuerike me seli në Prishtinë që zhvillon aplikacione web dhe mobile për klientë vendorë dhe ndërkombëtarë.', 'Teknologji Informative', 'Prishtinë', 'Rr. Agim Ramadani, Nr. 15', '+383 38 222 333', 'hr@techkos.com', 'https://techkos.com', '50-100', 1),
(2, 5, 'Dardania Market', 'Rrjet supermarketesh me mbi 20 pika shitjeje në të gjithë Kosovën.', 'Shitje me pakicë', 'Prizren', 'Rr. Remzi Ademaj, Nr. 4', '+383 29 555 111', 'karriera@dardaniamarket.com', 'https://dardaniamarket.com', '200+', 1);

INSERT INTO categories (id, name, slug, icon, keywords) VALUES
(1,  'Teknologji Informative', 'teknologji-informative', '💻', 'it,programim,programer,developer,zhvillues,software,softuer,web,php,javascript,kompjuter,teknologji,tech'),
(2,  'Shitje dhe Marketing',   'shitje-marketing',       '📈', 'shitje,shites,marketing,sales,reklama,social media'),
(3,  'Financa dhe Kontabilitet','financa-kontabilitet',  '💰', 'financa,kontabilitet,kontabilist,accounting,finance,banke,bank,ekonomi'),
(4,  'Arsim',                  'arsim',                  '🎓', 'arsim,mesues,mesimdhenes,teacher,education,profesor,shkolle'),
(5,  'Shëndetësi',             'shendetesi',             '🏥', 'shendetesi,mjek,infermier,doctor,nurse,health,farmaci,farmacist'),
(6,  'Ndërtimtari',            'ndertimtari',            '🏗️', 'ndertim,ndertimtari,inxhinier,arkitekt,construction,engineer'),
(7,  'Hoteleri dhe Turizëm',   'hoteleri-turizem',       '🏨', 'hoteleri,turizem,kamarier,kuzhinier,hotel,restaurant,chef,waiter'),
(8,  'Administratë',           'administrate',           '🗂️', 'administrate,administrator,asistent,sekretar,office,admin,recepsion'),
(9,  'Transport dhe Logjistikë','transport-logjistike',  '🚚', 'transport,logjistike,shofer,driver,magazine,logistics,depo'),
(10, 'Call Center',            'call-center',            '🎧', 'call center,thirrje,agjent,customer support,suport,anglisht');

INSERT INTO jobs (id, company_id, category_id, title, description, requirements, benefits, city, employment_type, experience_level, salary_min, salary_max, deadline, status, views) VALUES
(1, 1, 1, 'Zhvillues PHP (Backend)',
 'Kërkojmë zhvillues PHP për t\'u bashkuar me ekipin tonë që ndërton platforma web për klientë ndërkombëtarë. Do të punoni me PHP 8, MySQL dhe API REST.',
 '- Së paku 1 vit përvojë me PHP\n- Njohuri të MySQL dhe PDO\n- Njohuri bazike të Git\n- Gjuha angleze në nivel të mirë',
 '- Pagë konkurruese\n- Sigurim shëndetësor\n- Trajnime të vazhdueshme',
 'Prishtinë', 'full_time', 'mid', 900, 1400, DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'active', 45),
(2, 1, 1, 'Frontend Developer (JavaScript)',
 'Pozitë për zhvillues frontend që njeh JavaScript, HTML dhe CSS. Do të punoni në ndërfaqe moderne dhe responsive.',
 '- Njohuri të mira të HTML5, CSS3 dhe JavaScript\n- Përvojë me React ose Vue është përparësi',
 '- Orar fleksibil\n- Mundësi pune nga shtëpia 2 ditë në javë',
 'Prishtinë', 'full_time', 'entry', 700, 1000, DATE_ADD(CURDATE(), INTERVAL 25 DAY), 'active', 32),
(3, 1, 1, 'Praktikant në Zhvillim Softueri',
 'Program praktike 3-mujore për studentë dhe të diplomuar të rinj në fushën e IT.',
 '- Student ose i diplomuar në Shkenca Kompjuterike\n- Dëshirë për të mësuar',
 '- Pagesë mujore për praktikë\n- Mundësi punësimi pas praktikës',
 'Prishtinë', 'internship', 'entry', 300, 400, DATE_ADD(CURDATE(), INTERVAL 20 DAY), 'active', 60),
(4, 1, 10, 'Agjent Customer Support (Anglisht)',
 'Ofrim i mbështetjes për klientët ndërkombëtarë përmes telefonit dhe email-it.',
 '- Anglisht e rrjedhshme\n- Aftësi të mira komunikimi',
 '- Bonus mujor\n- Transport i siguruar për ndërrimin e natës',
 'Prishtinë', 'full_time', 'entry', 550, 700, DATE_ADD(CURDATE(), INTERVAL 15 DAY), 'active', 28),
(5, 1, 1, 'DevOps Engineer (Remote)',
 'Kërkojmë inxhinier DevOps për menaxhimin e infrastrukturës në cloud dhe CI/CD.',
 '- 3+ vite përvojë me Linux, Docker dhe CI/CD\n- Përvojë me AWS ose Azure',
 '- Punë plotësisht nga distanca\n- Pagë mbi mesataren e tregut',
 'Prishtinë', 'remote', 'senior', 1800, 2500, DATE_ADD(CURDATE(), INTERVAL 40 DAY), 'active', 19),
(6, 2, 2, 'Shitës/e në Supermarket',
 'Dardania Market kërkon shitës për pikat tona në Prizren.',
 '- Komunikim i mirë me klientët\n- Përgjegjësi dhe korrektësi',
 '- Pagë e rregullt\n- Zbritje për punëtorët',
 'Prizren', 'full_time', 'entry', 400, 450, DATE_ADD(CURDATE(), INTERVAL 18 DAY), 'active', 25),
(7, 2, 3, 'Kontabilist/e',
 'Menaxhimi i kontabilitetit të rrjetit të supermarketeve dhe raportimi mujor.',
 '- Diplomë në Ekonomi\n- Licencë kontabiliteti është përparësi\n- Njohuri të avancuara të Excel',
 '- Kontratë afatgjatë\n- Sigurim shëndetësor',
 'Prizren', 'full_time', 'mid', 700, 900, DATE_ADD(CURDATE(), INTERVAL 22 DAY), 'active', 14),
(8, 2, 9, 'Shofer për Shpërndarje',
 'Shpërndarje e mallit në pikat tona në rajonin e Pejës dhe Gjakovës.',
 '- Patentë shoferi kategoria C\n- Përvojë si shofer është përparësi',
 '- Pagë + shtesa për terren',
 'Pejë', 'full_time', 'entry', 500, 600, DATE_ADD(CURDATE(), INTERVAL 12 DAY), 'active', 9),
(9, 2, 2, 'Menaxher/e Marketingu',
 'Planifikimi dhe realizimi i fushatave të marketingut dhe menaxhimi i rrjeteve sociale.',
 '- 2+ vite përvojë në marketing\n- Njohuri të Meta Ads dhe Google Ads',
 '- Pagë konkurruese\n- Telefon dhe laptop pune',
 'Prishtinë', 'full_time', 'mid', 800, 1100, DATE_ADD(CURDATE(), INTERVAL 28 DAY), 'active', 21),
(10, 2, 8, 'Asistent/e Administrative (Part-time)',
 'Mbështetje administrative në zyrën qendrore në Ferizaj, 4 orë në ditë.',
 '- Njohuri të MS Office\n- Organizim i mirë',
 '- Orar i përshtatshëm për studentë',
 'Ferizaj', 'part_time', 'entry', 250, 300, DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'active', 7);

INSERT INTO applications (job_id, candidate_id, cover_letter, cv_file, cv_original_name, status) VALUES
(1, 2, 'I nderuar ekip i TechKos, jam i interesuar për pozitën Zhvillues PHP. Kam përvojë me PHP, MySQL dhe PDO gjatë studimeve dhe praktikës sime. Besoj se mund të kontribuoj në ekipin tuaj.', 'sample_cv.pdf', 'CV_Arben_Krasniqi.pdf', 'reviewing'),
(7, 3, 'Përshëndetje, me përvojën time 3-vjeçare në kontabilitet besoj se jam kandidatja e duhur për pozitën e kontabilistes në Dardania Market.', 'sample_cv.pdf', 'CV_Drita_Berisha.pdf', 'pending');

INSERT INTO saved_jobs (user_id, job_id) VALUES (2, 2), (2, 5);

INSERT INTO notifications (user_id, type, title, message, link, is_read) VALUES
(2, 'status', 'Statusi i aplikimit u ndryshua', 'Aplikimi juaj për "Zhvillues PHP (Backend)" tani është: Në shqyrtim.', '/candidate/applications.php', 0),
(4, 'application', 'Aplikim i ri', 'Arben Krasniqi aplikoi për "Zhvillues PHP (Backend)".', '/employer/applicants.php?job_id=1', 0),
(5, 'application', 'Aplikim i ri', 'Drita Berisha aplikoi për "Kontabilist/e".', '/employer/applicants.php?job_id=7', 0),
(1, 'system', 'Mirë se vini në PunaKosova', 'Paneli i administratorit është gati për përdorim.', '/admin/dashboard.php', 0);
---
-- SQL rregullues për databazën `punakosova2` (gjeneruar 04.10.2026 21:34)
USE `punakosova2`;
SET FOREIGN_KEY_CHECKS = 0;
-- ---- Tabelat që mungojnë ----
CREATE TABLE IF NOT EXISTS `educations` (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED NOT NULL,
    institution    VARCHAR(150) NOT NULL,
    degree         VARCHAR(120) NOT NULL,
    field_of_study VARCHAR(120) NULL,
    start_year     SMALLINT UNSIGNED NOT NULL,
    end_year       SMALLINT UNSIGNED NULL,
    description    TEXT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_edu_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `experiences` (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    company_name VARCHAR(150) NOT NULL,
    position     VARCHAR(120) NOT NULL,
    start_date   DATE NOT NULL,
    end_date     DATE NULL,
    is_current   TINYINT(1) NOT NULL DEFAULT 0,
    description  TEXT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_exp_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `chatbot_messages` (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NULL,
    session_id VARCHAR(128) NOT NULL,
    sender     ENUM('user','bot') NOT NULL,
    message    TEXT NOT NULL,
    language   CHAR(2) NOT NULL DEFAULT 'sq',
    source     VARCHAR(20) NOT NULL DEFAULT 'user' COMMENT 'user | ai | fallback',
    ip_address VARCHAR(45) NULL,
    is_hidden  TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = fshehur nga përdoruesi (Pastro bisedën)',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_chat_session (session_id, created_at),
    KEY idx_chat_user (user_id, created_at),
    KEY idx_chat_ip (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ---- Kolonat që mungojnë ----
ALTER TABLE `users` ADD COLUMN `full_name` VARCHAR(120) NULL;
ALTER TABLE `users` ADD COLUMN `password_hash` VARCHAR(255) NULL;
ALTER TABLE `users` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE `users` ADD COLUMN `last_login` DATETIME NULL;
ALTER TABLE `users` ADD COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `user_profiles` ADD COLUMN `birth_date` DATE         NULL;
ALTER TABLE `user_profiles` ADD COLUMN `headline` VARCHAR(150) NULL;
ALTER TABLE `user_profiles` ADD COLUMN `cv_file` VARCHAR(255) NULL;
ALTER TABLE `user_profiles` ADD COLUMN `cv_original_name` VARCHAR(255) NULL;
ALTER TABLE `user_profiles` ADD COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `companies` ADD COLUMN `name` VARCHAR(150) NULL;
ALTER TABLE `companies` ADD COLUMN `industry` VARCHAR(100) NULL;
ALTER TABLE `companies` ADD COLUMN `address` VARCHAR(200) NULL;
ALTER TABLE `companies` ADD COLUMN `email` VARCHAR(150) NULL;
ALTER TABLE `companies` ADD COLUMN `employees` VARCHAR(30)  NULL;
ALTER TABLE `companies` ADD COLUMN `is_verified` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `companies` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE `companies` ADD COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `categories` ADD COLUMN `slug` VARCHAR(120) NULL;
ALTER TABLE `categories` ADD COLUMN `icon` VARCHAR(10)  NULL;
ALTER TABLE `categories` ADD COLUMN `keywords` VARCHAR(255) NULL COMMENT 'Fjalë kyçe për chatbot-in (me presje)';
ALTER TABLE `categories` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE `jobs` ADD COLUMN `benefits` TEXT NULL;
ALTER TABLE `jobs` ADD COLUMN `experience_level` ENUM('entry','mid','senior') NOT NULL DEFAULT 'entry';
ALTER TABLE `jobs` ADD COLUMN `salary_min` DECIMAL(10,2) NULL;
ALTER TABLE `jobs` ADD COLUMN `salary_max` DECIMAL(10,2) NULL;
ALTER TABLE `jobs` ADD COLUMN `views` INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE `jobs` ADD COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `applications` ADD COLUMN `candidate_id` INT UNSIGNED NULL;
ALTER TABLE `applications` ADD COLUMN `cv_file` VARCHAR(255) NULL;
ALTER TABLE `applications` ADD COLUMN `cv_original_name` VARCHAR(255) NULL;
ALTER TABLE `applications` ADD COLUMN `employer_note` TEXT NULL;
ALTER TABLE `applications` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `applications` ADD COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `notifications` ADD COLUMN `type` VARCHAR(40)  NOT NULL DEFAULT 'info';
ALTER TABLE `notifications` ADD COLUMN `link` VARCHAR(255) NULL;
-- ---- Rregullime tipesh / ENUM / NULL ----
ALTER TABLE `users` MODIFY `name` varchar(100) NULL;
ALTER TABLE `users` MODIFY `password` varchar(255) NULL;
ALTER TABLE `companies` MODIFY `company_name` varchar(150) NULL;
ALTER TABLE `jobs` MODIFY `category_id` int(11) NULL;
ALTER TABLE `jobs` MODIFY `employment_type` ENUM('full_time','part_time','contract','internship','remote','Full Time','Part Time','Remote','Internship') NULL DEFAULT 'full_time';
ALTER TABLE `applications` MODIFY `user_id` int(11) NULL;
-- ---- Migrimi i të dhënave ----
UPDATE `users` SET `password_hash` = `password` WHERE (`password_hash` IS NULL OR `password_hash` = '') AND `password` LIKE '$2y$%';
UPDATE `users` SET `full_name` = `name` WHERE `full_name` IS NULL;
UPDATE `companies` SET `name` = `company_name` WHERE `name` IS NULL;
UPDATE `applications` SET `candidate_id` = `user_id` WHERE `candidate_id` IS NULL;
UPDATE `categories` SET `slug` = CONCAT('kategori-', `id`) WHERE `slug` IS NULL OR `slug` = '';
-- ---- Çelësat UNIQUE ----
ALTER TABLE `companies` ADD UNIQUE KEY `uq_companies_user` (`user_id`);
ALTER TABLE `categories` ADD UNIQUE KEY `uq_categories_slug` (`slug`);
ALTER TABLE `applications` ADD UNIQUE KEY `uq_app_job_candidate` (`job_id`, `candidate_id`);
SET FOREIGN_KEY_CHECKS = 1;
