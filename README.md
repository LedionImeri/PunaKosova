# PunaKosova
PunaKosova – Web platform for job management and online applications with an AI-powered assistant (PunaBot).
# 🇽🇰 PunaKosova

### Web Platform for Job Management, Online Applications and AI-Powered Assistance

**PunaKosova** është një platformë web e zhvilluar për menaxhimin dhe publikimin e vendeve të punës, kërkimin e mundësive të punës dhe aplikimin online nga kandidatët. Platforma përfshin gjithashtu një asistent të bazuar në inteligjencë artificiale, **PunaBot**, i cili ndihmon përdoruesit me pyetje dhe informacione rreth përdorimit të platformës dhe procesit të aplikimit.

Projekti është zhvilluar si pjesë e punimit të diplomës në **AAB – Fakulteti i Shkencave Kompjuterike, Programim Profesional**.

---

## 📌 Për projektin

PunaKosova është krijuar me qëllim që të ofrojë një mënyrë më të thjeshtë dhe të organizuar për lidhjen e **kandidatëve** me **punëdhënësit**.

Kandidatët mund të krijojnë profilin e tyre, të plotësojnë të dhënat personale, të shtojnë arsimin dhe përvojën e punës, të ngarkojnë CV-në dhe të aplikojnë në pozita të ndryshme.

Punëdhënësit mund të krijojnë profilin e kompanisë, të publikojnë vende të reja pune, të menaxhojnë pozitat e publikuara dhe të shikojnë aplikimet e kandidatëve.

Platforma përmban gjithashtu sistem të njoftimeve, ruajtjes së vendeve të punës, menaxhimit të aplikimeve dhe një chatbot të integruar me inteligjencë artificiale.

---

## 🎯 Qëllimi

Qëllimi kryesor i PunaKosova është të krijojë një platformë moderne dhe të lehtë për përdorim ku:

- Kandidatët mund të kërkojnë dhe aplikojnë në vende pune.
- Punëdhënësit mund të publikojnë dhe menaxhojnë vende pune.
- Aplikimet mund të menaxhohen në mënyrë të centralizuar.
- Kandidatët mund të ndërtojnë një profil profesional.
- Përdoruesit mund të marrin ndihmë përmes PunaBot.
- Informacioni ruhet dhe menaxhohet përmes një databaze relacionale.
- Siguria e aplikacionit trajtohet përmes mekanizmave të ndryshëm të mbrojtjes.

---

# 🚀 Karakteristikat kryesore

## 👤 Për kandidatët

Kandidatët mund të:

- Regjistrohen dhe kyçen në platformë.
- Krijojnë dhe menaxhojnë profilin personal.
- Shtojnë të dhënat e arsimit.
- Shtojnë përvojën e punës.
- Menaxhojnë aftësitë profesionale.
- Ngarkojnë CV në format PDF.
- Kërkojnë vende pune.
- Filtrojnë punët sipas kategorisë dhe kritereve të ndryshme.
- Shikojnë detajet e një pozite.
- Ruajnë vendet e preferuara të punës.
- Aplikojnë online.
- Dërgojnë CV dhe letër motivimi.
- Kontrollojnë statusin e aplikimeve.
- Marrin njoftime rreth aplikimeve.
- Përdorin asistentin AI PunaBot.

---

## 🏢 Për punëdhënësit

Punëdhënësit mund të:

- Krijojnë profil të kompanisë.
- Menaxhojnë informacionet e kompanisë.
- Shtojnë logon e kompanisë.
- Publikojnë vende të reja pune.
- Përditësojnë pozitat ekzistuese.
- Menaxhojnë vendet e publikuara.
- Shikojnë kandidatët që kanë aplikuar.
- Shikojnë CV-të e kandidatëve.
- Menaxhojnë aplikimet.
- Ndryshojnë statusin e aplikimit.
- Organizojnë procesin e rekrutimit përmes dashboard-it.

---

# 🤖 PunaBot – AI Assistant

Një nga funksionalitetet kryesore të PunaKosova është **PunaBot**, një asistent i integruar me inteligjencë artificiale.

PunaBot është krijuar për t'u ofruar përdoruesve ndihmë të shpejtë gjatë përdorimit të platformës.

Përdoruesi mund të bëjë pyetje në gjuhë natyrale dhe sistemi përpunon kërkesën përmes backend-it të aplikacionit.

PunaBot përdor **Groq API** për komunikimin me modelin e inteligjencës artificiale.

Arkitektura e komunikimit është:

```text
User
  ↓
PunaBot Interface
  ↓
PHP Backend
  ↓
Groq API
  ↓
AI Model
  ↓
PHP Backend
  ↓
User
```

API key nuk vendoset në frontend. Ajo ruhet në konfigurimin privat të serverit dhe përdoret nga backend-i PHP.

Kjo e parandalon ekspozimin e drejtpërdrejtë të API key në browser.

---

# 🛠️ Teknologjitë e përdorura

Projekti është zhvilluar duke përdorur teknologji web standarde, pa përdorur framework-e të jashtme për pjesën kryesore të aplikacionit.

### Frontend

- HTML5
- CSS3
- JavaScript
- AJAX
- Responsive Web Design

### Backend

- PHP 8
- PDO
- PHP Sessions
- REST API communication

### Database

- MySQL / MariaDB
- SQL
- Relational Database

### Development Environment

- XAMPP
- Apache
- MariaDB
- phpMyAdmin

### Artificial Intelligence

- Groq API
- AI-powered PunaBot

---

# 🏗️ Arkitektura e aplikacionit

PunaKosova është organizuar në tri shtresa kryesore:

```text
┌─────────────────────────────┐
│        CLIENT LAYER         │
│ HTML / CSS / JavaScript     │
└──────────────┬──────────────┘
               │
               ↓
┌─────────────────────────────┐
│        SERVER LAYER         │
│ PHP / Authentication        │
│ Business Logic / APIs       │
└──────────────┬──────────────┘
               │
               ↓
┌─────────────────────────────┐
│         DATA LAYER          │
│       MySQL / MariaDB       │
└─────────────────────────────┘
```

### Client Layer

Përgjegjës për ndërfaqen që përdoruesi sheh dhe me të cilën ndërvepron.

Përdoren:

- HTML
- CSS
- JavaScript
- AJAX

### Server Layer

Përgjegjës për logjikën e aplikacionit.

PHP përdoret për:

- autentikim;
- autorizim;
- menaxhimin e përdoruesve;
- menaxhimin e punëve;
- menaxhimin e aplikimeve;
- komunikimin me databazën;
- komunikimin me API-n e AI;
- validimin e të dhënave.

### Data Layer

Për ruajtjen e të dhënave përdoret MySQL/MariaDB.

Databaza ruan informacione për:

- përdoruesit;
- kompanitë;
- vendet e punës;
- aplikimet;
- profilet;
- arsimin;
- përvojën;
- aftësitë;
- CV-të;
- njoftimet;
- punët e ruajtura.

---

# 🗄️ Databaza

PunaKosova përdor një databazë relacionale ku tabelat janë të lidhura përmes çelësave primarë dhe të huaj.

Një shembull i lidhjes është:

```text
companies
    │
    │ company_id
    ↓
jobs
    │
    │ job_id
    ↓
applications
    │
    │ user_id
    ↓
users
```

Kjo strukturë mundëson që një kompani të ketë shumë vende pune dhe një kandidat të mund të aplikojë në vende të ndryshme pune.

Relacionet ndërmjet tabelave ndihmojnë në ruajtjen e integritetit dhe organizimit të të dhënave.

---

# 🔐 Siguria

Siguria është një pjesë e rëndësishme e aplikacionit.

## PDO & Prepared Statements

Komunikimi me databazën bëhet përmes **PDO** dhe prepared statements.

Kjo ndihmon në mbrojtjen nga SQL Injection sepse të dhënat e përdoruesit nuk bashkohen drejtpërdrejt me query-n SQL.

Shembull:

```php
$stmt = $pdo->prepare(
    "SELECT * FROM jobs WHERE id = ?"
);

$stmt->execute([$jobId]);
```

---

## CSRF Protection

Aplikacioni përdor **CSRF token** për të mbrojtur kërkesat që ndryshojnë të dhënat.

Token-i kontrollohet në backend para se të kryhet veprimi.

Kjo ndihmon që kërkesat e paautorizuara nga faqe të tjera të mos ekzekutohen në emër të përdoruesit.

---

## XSS Protection

Për të reduktuar rrezikun nga **Cross-Site Scripting (XSS)**, të dhënat që shfaqen në HTML trajtohen dhe escape-ohen kur është e nevojshme.

Në këtë mënyrë, një tekst i futur nga përdoruesi nuk duhet të interpretohet si kod HTML/JavaScript.

---

## Session Authentication

Sistemi përdor PHP Sessions për të mbajtur gjendjen e përdoruesit pas login-it.

Në bazë të session-it kontrollohet nëse përdoruesi është:

- i kyçur;
- kandidat;
- punëdhënës;
- administrator, nëse aplikohet.

Kjo ndihmon në kontrollimin e qasjes në pjesë të ndryshme të platformës.

---

# 📄 Menaxhimi i aplikimeve

Procesi i aplikimit është i organizuar në mënyrë që kandidati të mund të aplikojë direkt për një pozitë.

Procesi:

```text
Candidate
   ↓
Selects Job
   ↓
Views Job Details
   ↓
Applies
   ↓
CV + Cover Letter
   ↓
Application Stored
   ↓
Employer Reviews
   ↓
Application Status
```

Statusi i aplikimit mund të ndryshohet gjatë procesit të rekrutimit, për shembull:

- Pending
- Reviewing
- Accepted
- Rejected

---

# 💾 CV dhe profili profesional

Kandidatët mund të krijojnë një profil profesional duke plotësuar:

- të dhënat personale;
- arsimin;
- përvojën;
- aftësitë;
- CV-në.

CV-ja mund të ngarkohet në format **PDF**.

Sistemi gjithashtu mund të përdorë përqindjen e kompletimit të profilit për t'i treguar kandidatit se sa të plota janë të dhënat e tij.

---

# 🔎 Kërkimi dhe filtrimi i vendeve të punës

Përdoruesit mund të kërkojnë vende pune dhe të përdorin filtra për të gjetur më lehtë pozitat që u përshtaten.

Kjo e bën më të lehtë navigimin kur platforma përmban shumë vende pune.

---

# 🔔 Njoftimet

PunaKosova përmban sistem njoftimesh për t'i informuar përdoruesit rreth aktiviteteve të rëndësishme.

Për shembull, kandidati mund të marrë njoftim kur ndryshon statusi i aplikimit.

---

# 💻 Struktura e projektit

Struktura e projektit është e organizuar në mënyrë që pjesët e ndryshme të aplikacionit të jenë të ndara dhe më të lehta për mirëmbajtje.

Një strukturë e thjeshtuar është:

```text
PunaKosova/
│
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
│
├── auth/
│   ├── login.php
│   ├── register.php
│   └── logout.php
│
├── candidate/
│   ├── profile.php
│   ├── applications.php
│   └── saved_jobs.php
│
├── employer/
│   ├── dashboard.php
│   ├── jobs.php
│   └── applicants.php
│
├── chatbot/
│   ├── PunaBot.php
│   ├── widget.php
│   └── chatbot_api.php
│
├── config/
│   └── local.php
│
├── includes/
│
├── jobs/
│
└── index.php
```

Struktura mund të ndryshojë sipas versionit të projektit dhe organizimit final të repository-t.

---

# ⚙️ Instalimi lokal

Për ta ekzekutuar projektin lokalisht nevojitet një ambient PHP/MySQL, siç është XAMPP.

### 1. Klono repository-n

```bash
git clone https://github.com/USERNAME/PunaKosova.git
```

### 2. Vendose projektin në XAMPP

Vendose folderin në:

```text
C:\xampp\htdocs\PunaKosova
```

### 3. Starto XAMPP

Aktivizo:

```text
Apache
MySQL
```

### 4. Krijo databazën

Hap:

```text
phpMyAdmin
```

dhe krijo databazën:

```text
punakosova2
```

Pastaj importo SQL dump-in e projektit.

### 5. Konfiguro databazën

Konfigurimi duhet të përmbajë të dhënat e databazës lokale, për shembull:

```text
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=punakosova2
DB_USER=root
DB_PASS=
```

### 6. Hap projektin

Në browser:

```text
http://localhost/PunaKosova
```

---

# 🌐 GitHub

Repository i projektit mund të përdoret si pjesë e portfolio-s për të treguar:

- aftësitë në PHP;
- HTML/CSS/JavaScript;
- databaza relacionale;
- autentikim;
- siguri web;
- AJAX;
- API integration;
- AI integration;
- zhvillim Full-Stack.

> **Shënim:** API keys, password-at dhe konfigurimet private nuk duhet të publikohen në GitHub.

Për këtë arsye, konfigurimet sensitive duhet të mbahen jashtë repository-t ose të përdoret një `.env`/config privat dhe një `.gitignore` i përshtatshëm.

---

# 🧠 Metodologjia e zhvillimit

Projekti është zhvilluar duke përdorur një qasje **iterative dhe inkrementale**.

Në vend që i gjithë sistemi të ndërtohej në një hap, funksionalitetet janë zhvilluar në pjesë dhe janë testuar gradualisht.

Shembull:

```text
1. Authentication
       ↓
2. User Profiles
       ↓
3. Jobs
       ↓
4. Applications
       ↓
5. Employer Dashboard
       ↓
6. Notifications
       ↓
7. AI Assistant
       ↓
8. Testing & Improvements
```

Kjo qasje mundëson identifikimin dhe korrigjimin e problemeve gjatë zhvillimit.

---

# 🤖 AI Integration

PunaKosova demonstron integrimin e një shërbimi të jashtëm të inteligjencës artificiale përmes API-së.

Komunikimi me Groq bëhet nga backend-i PHP dhe jo direkt nga browser-i.

Kjo arkitekturë ndihmon në mbrojtjen e kredencialeve dhe lejon që backend-i të kontrollojë kërkesat para se ato të dërgohen te shërbimi AI.

---

# 📱 Responsive Design

Ndërfaqja është zhvilluar duke pasur parasysh pajisje me madhësi të ndryshme të ekranit.

Përdoruesit mund ta përdorin platformën në:

- Desktop
- Laptop
- Tablet
- Mobile

CSS media queries dhe strukturat responsive përdoren për përshtatjen e ndërfaqes.

---

# 🧪 Testimi

Gjatë zhvillimit janë testuar funksionalitete të ndryshme të sistemit, duke përfshirë:

- regjistrimin;
- login/logout;
- krijimin e profilit;
- publikimin e vendeve të punës;
- kërkimin e punëve;
- aplikimin;
- upload-in e CV-së;
- ndryshimin e statusit;
- njoftimet;
- autorizimin;
- validimin e inputeve;
- komunikimin me databazën;
- komunikimin me PunaBot.

---

# 📚 Koncepte kryesore

Projekti demonstron përdorimin praktik të disa koncepteve të zhvillimit web:

**PDO** – mënyrë e PHP për komunikim me databazën.

**Prepared Statements** – mënyrë më e sigurt për ekzekutimin e query-ve SQL.

**CSRF** – mekanizëm që ndihmon në mbrojtjen e kërkesave të paautorizuara.

**XSS** – sulm ku kodi JavaScript mund të injektohet në një faqe nëse inputet nuk trajtohen si duhet.

**AJAX** – mundëson dërgimin dhe marrjen e të dhënave nga serveri pa rifreskuar të gjithë faqen.

**API** – mënyrë që aplikacione të ndryshme të komunikojnë me njëra-tjetrën.

---

# 👨‍💻 Author

**Ledion Imeri**

Bachelor – Shkenca Kompjuterike – Programim Profesional  
AAB – Fakulteti i Shkencave Kompjuterike

PunaKosova është zhvilluar si projekt i punimit të diplomës.

---

# 📌 Statusi i projektit

**Project Status:** Completed / Academic Project

PunaKosova është një projekt akademik Full-Stack i zhvilluar për të demonstruar ndërtimin e një platforme web për menaxhimin e vendeve të punës dhe aplikimeve online, së bashku me integrimin e një asistenti të bazuar në inteligjencë artificiale.

---

# ⭐ PunaKosova

**Find jobs. Apply online. Build your career.**

🇽🇰 **Built in Kosovo with modern web technologies and AI assistance.**
