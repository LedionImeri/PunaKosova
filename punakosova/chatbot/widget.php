<?php
/**
 * PunaBot - HTML i dritares së bisedës (përfshihet në footer.php)
 */
$chatRole = current_role() ?? 'guest';
$chatSuggestions = [
    'guest'     => ['Punë IT në Prishtinë', 'A ka praktika?', 'Si të regjistrohem?', 'Find remote jobs'],
    'candidate' => ['Statusi i aplikimeve të mia', 'Punë në Prizren', 'Si ta përmirësoj CV-në?', 'Jobs over 800 euro'],
    'employer'  => ['Sa aplikantë kam?', 'Këshilla për një shpallje të mirë', 'Si ta ndryshoj statusin e aplikimit?'],
    'admin'     => ['Statistikat e platformës', 'Punë aktive në Prishtinë', 'Si të bllokoj një shpallje?'],
][$chatRole] ?? [];
$chatName = $_SESSION['full_name'] ?? '';
?>
<div class="pb-root" data-punabot data-endpoint="<?= e(url('chatbot/chatbot_api.php')) ?>" data-role="<?= e($chatRole) ?>">
    <button type="button" class="pb-launcher" aria-label="Hap PunaBot" aria-expanded="false" data-pb-toggle>
        <span class="pb-launcher-icon">💬</span>
        <span class="pb-launcher-close">✕</span>
    </button>

    <section class="pb-window" role="dialog" aria-label="PunaBot - asistenti virtual" hidden>
        <header class="pb-header">
            <div class="pb-avatar">🤖</div>
            <div class="pb-title">
                <strong>PunaBot</strong>
                <small><span class="pb-dot"></span> <span data-pb-mode>Asistenti juaj për punësim</span></small>
            </div>
            <button type="button" class="pb-icon-btn" title="Pastro bisedën" aria-label="Pastro bisedën" data-pb-clear>🗑</button>
            <button type="button" class="pb-icon-btn" title="Mbyll" aria-label="Mbyll" data-pb-toggle>✕</button>
        </header>

        <div class="pb-messages" data-pb-messages aria-live="polite">
            <div class="pb-msg pb-bot">
                <div class="pb-bubble">
                    Përshëndetje<?= $chatName ? ' ' . e(explode(' ', $chatName)[0]) : '' ?>! 👋 Jam <strong>PunaBot</strong>.
                    Mund t'ju gjej vende pune nga platforma dhe t'ju ndihmoj me <?= $chatRole === 'employer' ? 'shpalljet dhe aplikantët' : 'aplikimet, profilin dhe CV-në' ?>.
                    <br><small>Hi! You can also write to me in English.</small>
                </div>
            </div>
        </div>

        <div class="pb-suggestions" data-pb-suggestions>
            <?php foreach ($chatSuggestions as $s): ?>
                <button type="button" class="pb-chip" data-pb-suggest><?= e($s) ?></button>
            <?php endforeach; ?>
        </div>

        <form class="pb-input" data-pb-form autocomplete="off">
            <textarea rows="1" maxlength="500" placeholder="Shkruani mesazhin… / Type a message…" aria-label="Mesazhi" data-pb-text></textarea>
            <button type="submit" class="pb-send" aria-label="Dërgo" data-pb-send>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </button>
        </form>
        <div class="pb-footer-note">PunaBot mund të gabojë. Mos ndani të dhëna sensitive.</div>
    </section>
</div>
