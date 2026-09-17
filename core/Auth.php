<?php

/**
 * Session authentication for the admin panel.
 *
 * Two changes from the framework's original:
 *
 * 1. The `require_once 'config/db.php'` and `require_once 'Mailer.php'` at the
 *    top resolved against the include path, i.e. the working directory, so
 *    they only ever found their targets by accident. bootstrap.php already
 *    loads both before this file — the requires are gone.
 *
 * 2. Email verification no longer blocks login. Panel accounts are created by
 *    an administrator, not self-registered; a verification mail that has to
 *    arrive before the first login is a way to lock everyone out of a new
 *    installation when SMTP is not configured yet.
 *
 * Roles are stored and returned but not enforced — see
 * docs/php/06-decisions.md §2.
 */
class Auth
{
    private const COOKIE = 'tmh_remember';
    private const COOKIE_TTL = 30 * 24 * 60 * 60;

    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            /* Only over TLS in production; forcing it in development would
               mean the cookie is never set and nobody can log in locally. */
            'secure' => APP_ENV === 'production',
        ]);

        session_start();
    }

    public static function isAuthenticated(): bool
    {
        self::start();

        if (isset($_SESSION['user_id'])) {
            return true;
        }

        return self::validateRememberToken();
    }

    /**
     * The role name is joined in rather than stored on the user, so renaming a
     * role is one row. A user whose role was deleted still signs in — roles
     * are displayed, not enforced (docs/php/06-decisions.md §2), so a missing
     * one is a blank label and not a locked account.
     */
    private const SELECT = 'SELECT u.*, r.name AS role, r.public_id AS role_key
        FROM users u LEFT JOIN roles r ON r.id = u.role_id';

    /** The signed-in user, or null. Never includes the password hash. */
    public static function user(): ?array
    {
        if (!self::isAuthenticated()) {
            return null;
        }

        $user = db_fetch_one(self::SELECT . ' WHERE u.id = ?', [$_SESSION['user_id']]);

        if (!$user) {
            return null;
        }

        unset($user['password'], $user['remember_token'], $user['reset_token']);

        return $user;
    }

    public static function id(): ?string
    {
        self::start();
        return isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : null;
    }

    /**
     * @return array{status: bool, message: string}
     */
    public static function login(string $email, string $password, bool $remember = false): array
    {
        self::start();

        $user = db_fetch_one(self::SELECT . ' WHERE u.email = ?', [trim($email)]);

        /* One message for "no such account" and for "wrong password". Telling
           the two apart hands an attacker a list of who has an account. */
        if (!$user || !password_verify($password, (string) $user['password'])) {
            return ['status' => false, 'message' => 'Those details do not match an account'];
        }

        if (($user['status'] ?? 'active') === 'suspended') {
            return ['status' => false, 'message' => 'That account is suspended'];
        }

        self::createSession($user);

        if ($remember) {
            self::setRememberToken((string) $user['id']);
        }

        db_execute('UPDATE users SET last_active_at = ? WHERE id = ?', [now_iso(), $user['id']]);

        return ['status' => true, 'message' => 'Signed in'];
    }

    public static function logout(?string $redirectPath = null): void
    {
        self::start();

        if (!empty($_SESSION['user_id'])) {
            db_execute('UPDATE users SET remember_token = NULL WHERE id = ?', [$_SESSION['user_id']]);
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
            ]);
        }

        session_destroy();

        if (isset($_COOKIE[self::COOKIE])) {
            setcookie(self::COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
        }

        if ($redirectPath !== null) {
            header('Location: ' . base_url($redirectPath));
            exit;
        }
    }

    private static function createSession(array $user): void
    {
        /* A new session id at the moment privilege changes, so a fixated one
           handed to the user before login is worthless afterwards. */
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'] ?? '';
        $_SESSION['logged_in'] = true;
        $_SESSION['last_activity'] = time();
    }

    private static function setRememberToken(string $userId): void
    {
        $token = bin2hex(random_bytes(32));

        /* The hash is stored, not the token — a leaked database should not
           hand out working sessions. */
        db_execute('UPDATE users SET remember_token = ? WHERE id = ?', [hash('sha256', $token), $userId]);

        setcookie(self::COOKIE, $userId . ':' . $token, [
            'expires' => time() + self::COOKIE_TTL,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => APP_ENV === 'production',
        ]);
    }

    private static function validateRememberToken(): bool
    {
        if (empty($_COOKIE[self::COOKIE])) {
            return false;
        }

        $parts = explode(':', (string) $_COOKIE[self::COOKIE], 2);
        if (count($parts) !== 2) {
            return false;
        }

        [$userId, $token] = $parts;

        $user = db_fetch_one(self::SELECT . ' WHERE u.id = ?', [$userId]);
        if (!$user || empty($user['remember_token'])) {
            return false;
        }

        if (!hash_equals((string) $user['remember_token'], hash('sha256', $token))) {
            return false;
        }

        if (($user['status'] ?? 'active') === 'suspended') {
            return false;
        }

        self::createSession($user);
        return true;
    }

    /** Hash a password for storage. One place, so the algorithm is one change. */
    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }
}
