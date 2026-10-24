/**
 * PunaKosova - JavaScript kryesor
 * Navbar, dropdown, konfirmime, ruajtja e punëve me AJAX, validimi i formave.
 */
(function () {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ---- Menuja për telefon ----
    const toggle = document.querySelector('[data-nav-toggle]');
    const menu = document.querySelector('[data-nav-menu]');
    if (toggle && menu) {
        toggle.addEventListener('click', () => {
            const open = menu.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // ---- Dropdown i përdoruesit ----
    document.querySelectorAll('[data-dropdown]').forEach((dd) => {
        dd.querySelector('[data-dropdown-toggle]')?.addEventListener('click', (e) => {
            e.stopPropagation();
            dd.classList.toggle('open');
        });
    });
    document.addEventListener('click', (e) => {
        document.querySelectorAll('[data-dropdown].open').forEach((dd) => {
            if (!dd.contains(e.target)) dd.classList.remove('open');
        });
    });

    // ---- Mbyllja e alert-eve ----
    document.querySelectorAll('[data-close-alert]').forEach((btn) => {
        btn.addEventListener('click', () => btn.closest('.alert')?.remove());
    });
    document.querySelectorAll('[data-autoclose]').forEach((el) => {
        if (el.classList.contains('alert-error')) return; // gabimet mbeten
        setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 6000);
    });

    // ---- Konfirmimi para veprimeve (fshirje, etj.) ----
    document.addEventListener('submit', (e) => {
        const form = e.target;
        const msg = form.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) {
            e.preventDefault();
        }
    });

    // ---- Shfaq/fshih fjalëkalimin ----
    document.querySelectorAll('[data-toggle-pass]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = btn.parentElement.querySelector('input');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? 'Fshih' : 'Shfaq';
        });
    });

    // ---- Emri i file-it të zgjedhur ----
    document.querySelectorAll('.file-drop input[type=file]').forEach((input) => {
        input.addEventListener('change', () => {
            const target = input.closest('.file-drop').querySelector('.file-name');
            if (target) target.textContent = input.files.length ? input.files[0].name : '';
            const radio = document.querySelector('input[name="cv_option"][value="new"]');
            if (radio && input.files.length) radio.checked = true;
        });
    });

    // ---- Numëruesi i karaktereve ----
    document.querySelectorAll('textarea[data-counter]').forEach((ta) => {
        const max = parseInt(ta.getAttribute('maxlength') || '0', 10);
        const counter = document.createElement('div');
        counter.className = 'char-counter';
        ta.insertAdjacentElement('afterend', counter);
        const update = () => { counter.textContent = ta.value.length + (max ? ' / ' + max : '') + ' karaktere'; };
        ta.addEventListener('input', update);
        update();
    });

    // ---- Filtrat në telefon ----
    document.querySelector('[data-filter-toggle]')?.addEventListener('click', () => {
        document.querySelector('.filters')?.classList.toggle('open');
    });

    // ---- Ndryshimi automatik i renditjes ----
    document.querySelectorAll('[data-autosubmit]').forEach((el) => {
        el.addEventListener('change', () => el.form.submit());
    });

    // ---- "Punë aktuale" çaktivizon datën e mbarimit ----
    document.querySelectorAll('[data-current-toggle]').forEach((cb) => {
        const target = document.getElementById(cb.getAttribute('data-current-toggle'));
        const sync = () => { if (target) { target.disabled = cb.checked; if (cb.checked) target.value = ''; } };
        cb.addEventListener('change', sync);
        sync();
    });

    // ---- Ruajtja e punëve me AJAX ----
    document.addEventListener('submit', async (e) => {
        const form = e.target;
        if (!form.matches('[data-save-job]') || e.defaultPrevented) return;
        e.preventDefault();
        const btn = form.querySelector('button');
        btn.disabled = true;
        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf },
                body: new FormData(form),
            });
            const data = await res.json();
            if (data.ok) {
                document.querySelectorAll('[data-save-job][data-job="' + form.dataset.job + '"] button').forEach((b) => {
                    b.classList.toggle('saved', data.saved);
                    b.title = data.saved ? 'Hiqe nga të ruajturat' : 'Ruaje punën';
                    const lbl = b.querySelector('.save-label');
                    if (lbl) lbl.textContent = data.saved ? 'E ruajtur' : 'Ruaje';
                });
            } else if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                alert(data.error || 'Ndodhi një gabim.');
            }
        } catch (err) {
            form.submit();
        } finally {
            btn.disabled = false;
        }
    });

    // ---- Validimi bazë në klient (HTML5 + mesazh në shqip) ----
    document.querySelectorAll('form[data-validate]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            let valid = true;
            form.querySelectorAll('[required]').forEach((field) => {
                const empty = field.type === 'checkbox' ? !field.checked : !String(field.value).trim();
                field.classList.toggle('is-invalid', empty);
                if (empty) valid = false;
            });
            const pass = form.querySelector('input[name="password"]');
            const conf = form.querySelector('input[name="password_confirm"]');
            if (pass && conf && pass.value !== conf.value) {
                conf.classList.add('is-invalid');
                valid = false;
                alert('Fjalëkalimet nuk përputhen.');
            }
            if (!valid) {
                e.preventDefault();
                e.stopImmediatePropagation();
                form.querySelector('.is-invalid')?.focus();
            }
        });
        form.querySelectorAll('input, textarea, select').forEach((f) => {
            f.addEventListener('input', () => f.classList.remove('is-invalid'));
        });
    });
})();
