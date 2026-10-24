</main>

<footer class="footer">
    <div class="container footer-grid">
        <div>
            <a href="<?= url('index.php') ?>" class="brand brand-light">
                <span class="brand-mark">P</span><span class="brand-text">Puna<strong>Kosova</strong></span>
            </a>
            <p class="footer-text">Platforma moderne që lidh kandidatët me punëdhënësit më të mirë në Kosovë.</p>
        </div>
        <div>
            <h4>Për kandidatët</h4>
            <a href="<?= url('jobs/index.php') ?>">Kërko punë</a>
            <a href="<?= url('register.php?role=candidate') ?>">Krijo llogari</a>
            <a href="<?= url('candidate/profile.php') ?>">Ngarko CV-në</a>
        </div>
        <div>
            <h4>Për punëdhënësit</h4>
            <a href="<?= url('register.php?role=employer') ?>">Regjistro kompaninë</a>
            <a href="<?= url('employer/job_form.php') ?>">Publiko një punë</a>
            <a href="<?= url('companies.php') ?>">Kompanitë</a>
        </div>
        <div>
            <h4>Kontakt</h4>
            <span>Prishtinë, Kosovë</span>
            <a href="mailto:info@punakosova.com">info@punakosova.com</a>
            <span>+383 38 000 000</span>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">&copy; <?= date('Y') ?> PunaKosova. Të gjitha të drejtat e rezervuara.</div>
    </div>
</footer>

<?php require ROOT_PATH . '/chatbot/widget.php'; ?>

<script src="<?= asset('js/main.js') ?>"></script>
<script src="<?= asset('js/chatbot.js') ?>"></script>
</body>
</html>
