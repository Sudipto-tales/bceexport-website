<?php

/**
 * BCE Export Admin Sign-in Screen.
 */
?>
<style>
.adm-shell {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg, #f7f1f3);
    padding: 24px;
}
.adm-auth {
    width: 100%;
    max-width: 420px;
}
.adm-auth__card {
    background: var(--surface, #ffffff);
    border: 1px solid var(--hairline-strong, #e3d0d8);
    border-radius: 12px;
    padding: 36px 32px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.adm-auth__brand {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-dark, #2c2028);
    margin-bottom: 8px;
    text-decoration: none;
}
.adm-auth__brand img {
    border-radius: 8px;
}
.adm-auth__card h1 {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
    color: var(--text-dark, #2c2028);
}
.adm-auth__lead {
    color: var(--text-mid, #6b5a62);
    font-size: 0.875rem;
    line-height: 1.4;
    margin: 0 0 8px 0;
}
.adm-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.adm-field span {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-dark, #2c2028);
}
.adm-field input {
    padding: 10px 14px;
    border: 1px solid var(--hairline-strong, #e3d0d8);
    border-radius: 8px;
    font-size: 0.9375rem;
    font-family: inherit;
    background: var(--surface, #fff);
    color: var(--text-dark, #2c2028);
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.adm-field input:focus {
    border-color: var(--brand-blue, #2e6bb8);
    box-shadow: 0 0 0 3px rgba(46, 107, 184, 0.15);
}
.adm-check {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.8125rem;
    color: var(--text-mid, #6b5a62);
    cursor: pointer;
    user-select: none;
}
.adm-auth__submit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: var(--brand-blue, #2e6bb8);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 12px 20px;
    font-size: 0.9375rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s, transform 0.1s;
    margin-top: 8px;
}
.adm-auth__submit:hover {
    background: #23589b;
}
.adm-auth__submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.adm-auth__note {
    padding: 10px 14px;
    border-radius: 8px;
    background: var(--bad-bg, #fbeaea);
    color: var(--bad, #c62828);
    font-size: 0.8125rem;
    margin: 0;
}
.adm-auth__back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--text-muted, #9c8b93);
    font-size: 0.8125rem;
    text-decoration: none;
    margin-top: 8px;
    align-self: center;
}
.adm-auth__back:hover {
    color: var(--brand-blue, #2e6bb8);
}
</style>

        <section class="adm-auth">
            <form class="adm-auth__card" id="adminLogin" method="post" action="<?= e($action ?? '') ?>" novalidate>
                <a class="adm-auth__brand" href="<?= e($home ?? '/') ?>">
<?php if (($logo ?? '') !== ''): ?>
                    <img src="<?= e($logo) ?>" alt="<?= e($siteName ?? '') ?>" width="48" height="48">
<?php endif; ?>
                    <span><?= e($siteName ?? 'BCE Export') ?></span>
                </a>

                <h1>Sign in</h1>
                <p class="adm-auth__lead">Control panel for BCE Export. Ask an administrator if you do not have an account.</p>

                <input type="hidden" name="_token" value="<?= e($csrf ?? '') ?>">

                <label class="adm-field" for="admEmail">
                    <span>Email</span>
                    <input type="email" id="admEmail" name="email" placeholder="admin@bceexport.com" autocomplete="username" required autofocus>
                </label>

                <label class="adm-field" for="admPassword">
                    <span>Password</span>
                    <input type="password" id="admPassword" name="password" placeholder="••••••••" autocomplete="current-password" required>
                </label>

                <label class="adm-check">
                    <input type="checkbox" name="remember" value="1"> Keep me signed in on this device
                </label>

                <button type="submit" class="btn-primary adm-auth__submit">
                    <i class="fa-solid fa-right-to-bracket"></i> Sign in
                </button>

                <p class="adm-auth__note" id="admNote" role="status" hidden></p>

                <a class="adm-auth__back" href="<?= e($home ?? '/') ?>"><i class="fa-solid fa-arrow-left"></i> Back to the website</a>
            </form>
        </section>

        <script>
            (function () {
                var form = document.getElementById('adminLogin');
                var note = document.getElementById('admNote');
                var button = form.querySelector('button[type="submit"]');
                var next = <?= json_encode($next ?? '/', JSON_UNESCAPED_SLASHES) ?>;

                var say = function (message) {
                    note.textContent = message;
                    note.hidden = !message;
                };

                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    if (!form.reportValidity()) return;

                    say('');
                    button.disabled = true;

                    var data = new FormData(form);

                    fetch(form.action, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-Token': data.get('_token') || ''
                        },
                        body: JSON.stringify({
                            email: data.get('email') || '',
                            password: data.get('password') || '',
                            remember: data.get('remember') === '1'
                        })
                    })
                        .then(function (res) {
                            return res.json().catch(function () { return {}; })
                                .then(function (body) { return { res: res, body: body }; });
                        })
                        .then(function (r) {
                            if (r.res.ok) {
                                window.location.assign(next);
                                return;
                            }

                            var error = r.body.error || {};
                            var fields = error.fields || {};
                            var first = Object.keys(fields)[0];

                            say(first ? fields[first] : (error.message || 'That did not work. Check the address and password.'));
                            button.disabled = false;
                        })
                        .catch(function () {
                            say('Could not reach the server.');
                            button.disabled = false;
                        });
                });
            }());
        </script>
