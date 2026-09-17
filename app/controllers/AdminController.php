<?php

/**
 * The panel's front door, and every screen behind it.
 *
 * `/admin` had no route at all, so the one URL every administrator types
 * answered 404. 5.0 gave it a sign-in; 5.2 gives it the forty-one screens,
 * through one action rather than forty-one.
 *
 * A screen exists if its shell exists — `app/page/admin/<screen>.php`, written
 * by `tools/scaffold-admin.php`. There is deliberately no second list of valid
 * screens here: a route table and a directory that have to agree are a route
 * table and a directory that eventually do not, and the failure is a 404 on a
 * page somebody can see in the sidebar.
 *
 * Authentication is not reimplemented here. The form posts to
 * `POST api/auth/login`, which is what the panel's own JavaScript calls too —
 * rate limited, session-based, and already the only place that knows how a
 * password is checked. This controller renders a page and asks Auth whether
 * there is a session; nothing more.
 */
class AdminController extends SiteController
{
    /* The panel is not the site. No nav item is lit, and neither the header's
       department menu nor the pre-footer belongs on it. */
    protected string $active = '';

    /** Where `/admin` and a fresh sign-in land. */
    private const HOME = 'dashboard';

    /**
     * `/admin` — the sign-in screen, or the panel once there is a session.
     *
     * Not a redirect to `/admin/login`: a bookmark to `/admin` should open the
     * panel for somebody already signed in, and asking them to follow a
     * redirect to a form they do not need is a round trip to nothing.
     */
    public function index(): void
    {
        if (Auth::isAuthenticated()) {
            $this->redirect(base_url('admin/' . $this->landingScreen()));
        }

        $this->loginPage();
    }

    /**
     * Where this account opens the panel — the profile screen's "Open the
     * panel on", or the dashboard.
     *
     * The select stores a bare screen name, and the rows that held the
     * design's `dashboard.html` spelling were cleaned by migration 025. The
     * extension is still stripped here because a restored backup predates
     * that migration, and two characters are cheaper than a support call. An
     * unknown value falls back rather than 404s: a screen can be renamed, and
     * a stale preference should not shut somebody out of their own panel.
     */
    private function landingScreen(): string
    {
        $stored = (string) (Auth::user()['landing_page'] ?? '');
        $screen = preg_replace('/\.html$/i', '', trim($stored)) ?? '';

        return $this->shellExists($screen) ? $screen : self::HOME;
    }

    /**
     * `/admin/{screen}` — one of the forty-one.
     *
     * Three things and no more: refuse anyone without a session, find the
     * shell, render it. Everything the screen shows still comes from
     * `assets/admin/js/` (docs/php/06-decisions.md §1).
     */
    public function screen(): void
    {
        $screen = (string) $this->param('screen', '');

        if (($canonical = $this->stripHtmlSuffix($screen)) !== null) {
            $this->redirect($canonical, 301);
        }

        /* Anchored, and no dots: `$screen` goes straight into a file path. */
        if (!preg_match('/^[a-z0-9-]+$/', $screen) || !$this->shellExists($screen)) {
            $this->screenNotFound();
            return;
        }

        if (!Auth::isAuthenticated()) {
            $this->redirect(base_url('admin/login?next=' . urlencode($screen)));
        }

        render_view('/app/page/admin/' . $screen . '.php');
    }

    /**
     * `/admin/login` — the same screen under the name people link to.
     *
     * Signed in, it goes to `/admin` rather than rendering the form again: a
     * login page shown to somebody who is already logged in is a page that
     * invites them to log in twice.
     */
    public function login(): void
    {
        if (Auth::isAuthenticated()) {
            $this->redirect(base_url('admin'));
        }

        $this->loginPage();
    }

    /**
     * `/admin/logout` — sign out, then back to the form.
     *
     * A GET, unlike `POST api/auth/logout`. The API's version is CSRF-guarded
     * and is what a fetch would call; this one is a link, which is what the
     * account menu in the topbar needs. It ends a session and can create
     * nothing, so the worst a forged link achieves is signing somebody out.
     */
    public function logout(): void
    {
        if (Auth::isAuthenticated()) {
            $user = Auth::user();

            if ($user) {
                ActivityLog::record('logout', 'auth', null, (string) $user['email']);
            }
        }

        Auth::logout();

        $this->redirect(base_url('admin/login'));
    }

    /* ---------------------------------------------------------
       Screens
       --------------------------------------------------------- */

    private function loginPage(): void
    {
        $this->shell('admin/login', [
            'head' => ['title' => 'Sign in', 'noindex' => true],
            'csrf' => Csrf::token(),
            'action' => base_url('api/auth/login'),
            'next' => base_url('admin/' . $this->nextScreen()),
        ]);
    }

    /**
     * Where a successful sign-in lands: the screen the guard turned somebody
     * away from, or the dashboard.
     *
     * `next` is a screen name, not a URL, and it is checked against the shells
     * on disk before it is used. A login page that redirects to whatever it is
     * handed is a phishing link that starts on the hospital's own domain.
     */
    private function nextScreen(): string
    {
        $next = (string) ($_GET['next'] ?? '');

        return preg_match('/^[a-z0-9-]+$/', $next) && $this->shellExists($next)
            ? $next
            : self::HOME;
    }

    private function shellExists(string $screen): bool
    {
        return is_file(__BASEDIR__ . '/app/page/admin/' . $screen . '.php')
            && !in_array($screen, ['login'], true);
    }

    /**
     * `doctors.html` → the URL for `doctors`, or null when there is no suffix
     * to strip.
     *
     * Nothing in the panel emits this spelling any more: `core/nav.js` and
     * the forty page scripts beside it link to `doctors`, which resolves
     * against `/admin/` without a base or a helper. What is left is the
     * bookmark a member of staff made during the prototype era, and the
     * address in an email somebody sent themselves.
     *
     * Kept rather than deleted because it costs nothing — a path that works
     * never reaches the router's not-found arm — and because a 404 on a
     * bookmark is a support call. `doctor-form.html?id=x` arrives here and
     * leaves as `/admin/doctor-form?id=x`.
     */
    private function stripHtmlSuffix(string $screen): ?string
    {
        if (!str_ends_with($screen, '.html')) {
            return null;
        }

        /* Rebuilt from $_GET rather than read from QUERY_STRING, because under
           Apache the rewrite has already put `route` in there and it would
           come back around in the redirect. */
        $params = $_GET;
        unset($params['route']);

        return base_url('admin/' . substr($screen, 0, -5))
            . ($params ? '?' . http_build_query($params) : '');
    }

    /**
     * Plain text, not the site's 404: the panel's own error page would need
     * the chrome, the chrome needs the JavaScript and a session, and a mistyped
     * admin URL is seen by a member of staff rather than by a crawler.
     */
    private function screenNotFound(): void
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "No such admin screen.\n";
    }

    /**
     * The sign-in screen's chrome: the site's head, and nothing between it and
     * the form.
     *
     * The forty-one screens behind the login use `app/components/admin/`, which
     * is the panel's own chrome — its stylesheets, its sidebar, its scripts.
     * The login has none of those: there is no session to render a sidebar for
     * and no page script to load, so it borrows the site's head instead and is
     * the one screen the scaffolder does not know about.
     *
     * SiteController::page() is the wrong wrapper for it too — it renders the
     * public header, the department mega menu, the pre-footer and both popups,
     * none of which belong on a sign-in form. This takes the one part that
     * does. `site/layout/scripts` is deliberately not rendered either: it loads
     * GSAP, Lenis and website.js, all of which exist to animate markup that is
     * not here.
     */
    private function shell(string $body, array $data): void
    {
        $head = array_merge($this->head(), $data['head'] ?? []);
        $head['noindex'] = true;

        App::render('site/layout/head', $head);

        echo "\n    <main id=\"top\" class=\"adm-shell\">\n";
        render_view('/app/page/' . $body . '.php', $data + [
            'siteName' => (string) setting('general', 'name', 'Teresa Memorial Hospital'),
            'logo' => site_url((string) setting('general', 'logo', ''), base_url('assets/logo-teresa.png')),
            'home' => base_url('/'),
        ]);
        echo "\n    </main>\n</body>\n\n</html>\n";
    }
}
