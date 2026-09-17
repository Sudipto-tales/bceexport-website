<?php

/**
 * Session authentication for the panel — docs/07-api-contract.md §Auth.
 *
 * A cookie session rather than a JWT, because the panel is a browser talking
 * to its own origin. A token in JavaScript's reach is a token an injected
 * script can read and keep; an HttpOnly cookie is not. JwtAuth stays in the
 * framework for anything machine-to-machine.
 *
 * Roles are returned and displayed. They are not checked — see
 * docs/php/06-decisions.md §2.
 */
class AuthController extends ApiController
{
    /**
     * Login is the one write the CSRF middleware cannot guard: there is no
     * session yet, so there is no token to have issued. What guards it instead
     * is the rate limiter and the password.
     *
     * The response carries the CSRF token, which is how every later request
     * gets one.
     */
    public function login(): never
    {
        $body = $this->body();
        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        $fields = [];

        if ($email === '') {
            $fields['email'] = 'Required';
        }

        if ($password === '') {
            $fields['password'] = 'Required';
        }

        if ($fields) {
            Api::validationFailed($fields);
        }

        /* Keyed on the address as well as the caller: an attacker on a
           rotating IP still cannot grind one account, and one office behind
           one NAT address does not lock itself out because a colleague
           mistyped their password. */
        if (!RateLimit::attempt('login', 10, 900, strtolower($email))) {
            Api::rateLimited(900);
        }

        $result = Auth::login($email, $password, !empty($body['remember']));

        if (!$result['status']) {
            Api::fail(401, 'UNAUTHENTICATED', $result['message']);
        }

        RateLimit::clear('login', strtolower($email));
        ActivityLog::record('login', 'auth', null, $email);

        Api::ok($this->profile());
    }

    public function logout(): never
    {
        $user = Auth::user();

        if ($user) {
            ActivityLog::record('logout', 'auth', null, (string) $user['email']);
        }

        Auth::logout();

        Api::noContent();
    }

    public function me(): never
    {
        Api::ok($this->profile());
    }

    /**
     * "Confirm your current password" — the check the profile screen makes
     * before it lets somebody change their own.
     *
     * Not in the contract, and added at 5.3 because the panel asks the
     * question and nothing else could answer it. The alternative was posting
     * to `login` with the signed-in address, which regenerates the session and
     * writes a second sign-in to the activity log for something that was not
     * one.
     *
     * Rate limited per account, like login: this endpoint takes a password and
     * says whether it was right, which is the same thing worth grinding.
     */
    public function verifyPassword(): never
    {
        $user = Auth::user();

        if (!$user) {
            Api::unauthenticated();
        }

        if (!RateLimit::attempt('verify-password', 10, 900, strtolower((string) $user['email']))) {
            Api::rateLimited(900);
        }

        /* Auth::user() strips the hash, which is the right default everywhere
           else — this is the one caller that has to compare against it, so it
           asks for that column and nothing more. */
        $hash = (string) db_scalar('SELECT password FROM users WHERE id = ?', [$user['id']]);

        $ok = $hash !== '' && password_verify((string) ($this->body()['password'] ?? ''), $hash);

        if ($ok) {
            RateLimit::clear('verify-password', strtolower((string) $user['email']));
        }

        /* 200 either way. The request worked; the answer is the payload. A 401
           here would read to the panel as a dead session and send somebody to
           the sign-in screen for mistyping a password. */
        Api::ok(['ok' => $ok]);
    }

    /** Split out so login can answer with the same payload as GET me. */
    private function profile(): array
    {
        $user = Auth::user();

        if (!$user) {
            Api::unauthenticated();
        }

        return [
            'user' => [
                'id' => $user['public_id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'avatar' => $user['avatar'],
                'phone' => $user['phone'],
                'role' => $user['role'],
                'roleId' => $user['role_key'],
                'status' => $user['status'],
            ],
            'permissions' => $this->permissions($user),
            'csrfToken' => Csrf::token(),
        ];
    }

    /**
     * Sent so the panel can grey out what a role is not meant to touch. The
     * server does not act on it: see docs/php/06-decisions.md §2. Anything
     * that must actually be denied has to be denied server-side, and today
     * nothing is.
     */
    private function permissions(array $user): array
    {
        if (empty($user['role_id'])) {
            return [];
        }

        $raw = db_scalar('SELECT permissions FROM roles WHERE id = ?', [$user['role_id']]);

        return json_column($raw);
    }

    /**
     * Always 204, whether or not the address is on an account. Telling the two
     * apart turns this endpoint into a way to ask who has one.
     */
    public function forgot(): never
    {
        $email = trim((string) ($this->body()['email'] ?? ''));

        if ($email === '') {
            Api::validationFailed(['email' => 'Required']);
        }

        if (!RateLimit::attempt('forgot', 5, 900, strtolower($email))) {
            Api::rateLimited(900);
        }

        $user = db_fetch_one('SELECT id, name, email FROM users WHERE email = ?', [$email]);

        if ($user) {
            $token = bin2hex(random_bytes(32));

            /* The hash is stored, not the token — a leaked database should not
               hand out working password resets. */
            db_execute(
                'UPDATE users SET reset_token = ?, reset_expires_at = ? WHERE id = ?',
                [hash('sha256', $token), gmdate('Y-m-d H:i:s', time() + 3600), $user['id']]
            );

            $link = base_url('admin/reset-password?token=' . $token);

            Mailer::send(
                $user['email'],
                'Reset your Teresa Memorial admin password',
                '<p>Hello ' . e($user['name']) . ',</p>'
                . '<p>Somebody asked to reset the password on your admin account. '
                . 'If that was you, <a href="' . e($link) . '">choose a new one</a>. '
                . 'The link works for one hour.</p>'
                . '<p>If it was not you, nothing has changed and you can ignore this.</p>',
                true
            );
        }

        Api::noContent();
    }

    public function reset(): never
    {
        $body = $this->body();
        $token = (string) ($body['token'] ?? '');
        $password = (string) ($body['password'] ?? '');

        $fields = [];

        if ($token === '') {
            $fields['token'] = 'Required';
        }

        if (strlen($password) < 10) {
            $fields['password'] = 'Use at least 10 characters';
        }

        if ($fields) {
            Api::validationFailed($fields);
        }

        $user = db_fetch_one(
            'SELECT id, email FROM users WHERE reset_token = ? AND reset_expires_at > ?',
            [hash('sha256', $token), gmdate('Y-m-d H:i:s')]
        );

        if (!$user) {
            Api::fail(422, 'VALIDATION_FAILED', 'That reset link has expired — ask for a new one');
        }

        db_execute(
            'UPDATE users SET password = ?, reset_token = NULL, reset_expires_at = NULL,
                remember_token = NULL, updated_at = ? WHERE id = ?',
            [Auth::hash($password), now_iso(), $user['id']]
        );

        ActivityLog::record('update', 'auth', null, 'Password reset for ' . $user['email']);

        Api::noContent();
    }
}
