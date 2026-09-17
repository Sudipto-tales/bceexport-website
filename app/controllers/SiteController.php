<?php

/**
 * Everything every public page needs, so that a page controller is only the
 * part that differs.
 *
 * The chrome — the document head, the header, the footer, the two popups —
 * is the same twelve queries on every one of the twenty pages, and it is all
 * settings, navigation and departments. Assembling it once here is what keeps
 * a page controller down to "fetch the rows this page is about".
 *
 * `page()` renders the chrome around the body template rather than each
 * template repeating the eight component calls. A page template is therefore
 * only the inside of <main>, which is also exactly what a block in
 * tools/build-pages.mjs was.
 */
abstract class SiteController extends BaseController
{
    /** Which nav link is lit: home | about | departments | facilities | careers | contact. */
    protected string $active = '';

    /** Cached for the request — the footer and the mega menu ask for the same rows. */
    private ?array $menuDepartments = null;

    /* ---------------------------------------------------------
       Rendering
       --------------------------------------------------------- */

    /**
     * Render a page: chrome, then the body template, then the rest of the
     * chrome.
     *
     * @param string $body  A file under app/page/site/, without the extension
     * @param array  $data  Everything the body template reads, plus any of
     *                      `head`, `header`, `footer`, `scripts` to override
     */
    protected function page(string $body, array $data = []): void
    {
        /* Through head() rather than merged over it: the page's own schema
           nodes have to be appended to the site-wide graph, and a merge here
           would replace it — leaving a Physician with no hospital to work for. */
        $head = $this->head($data['head'] ?? []);
        $header = array_merge($this->header(), $data['header'] ?? []);
        $footer = array_merge($this->footer(), $data['footer'] ?? []);
        $scripts = array_merge(['pages' => true], $data['scripts'] ?? []);

        /* The hospital's own contact details, on every page whether or not its
           controller thought to pass them. Eight of the twelve templates print
           the reception number somewhere — usually in the closing band — and a
           controller that forgets does not render an empty button, it renders
           the number written into the component as a default. Which is the
           repo-wide find-and-replace this conversion exists to end. */
        $data += [
            'phone' => site_primary_phone(),
            'email' => site_primary_email(),
        ];

        /* The closing band's second button, ready-made: `tel:` when there is a
           number and an empty array when there is not, which is what the
           component reads as "print one button, not two". */
        $data += ['callAction' => $this->callAction($data['phone'])];

        App::render('site/layout/head', $head);
        App::render('site/layout/header', $header);

        echo "\n    <main id=\"top\">\n";
        render_view('/app/page/site/' . $body . '.php', $data);
        echo "\n    </main>\n\n";

        if (($data['prefooter'] ?? true) !== false) {
            App::render('site/layout/prefooter', is_array($data['prefooter'] ?? null) ? $data['prefooter'] : []);
        }

        App::render('site/layout/footer', $footer);

        /* Above the scripts, always: popups.js reads window.TMH_POPUPS once,
           at parse time, and a config echoed after it is a config it never
           sees. Both widgets print a <script> and no markup. */
        $popups = $this->popups();

        App::render('site/widget/cookie-bar', $popups['cookie']);
        App::render('site/widget/ads-popup', $popups['ads']);

        App::render('site/layout/scripts', array_merge($scripts, [
            'popups' => $popups['cookie']['enabled'] || $popups['ads']['enabled'],
        ]));
    }

    /* ---------------------------------------------------------
       Chrome
       --------------------------------------------------------- */

    /**
     * The document head.
     *
     * SEO defaults come from the settings group so that a page which says
     * nothing still has a description and an OG image. A page that has its own
     * — a department, a post — passes them in and they win.
     */
    protected function head(array $overrides = []): array
    {
        $seo = settings_group('seo');

        $head = array_merge([
            'siteName' => (string) setting('general', 'name', 'Teresa Memorial Hospital'),
            'description' => (string) ($seo['defaultDescription'] ?? ''),
            'keywords' => (string) ($seo['defaultKeywords'] ?? ''),
            'ogImage' => (string) ($seo['defaultOgImage'] ?? ''),
            'ogType' => 'website',
            'canonical' => $this->canonical(),
            'robots' => (string) ($seo['robots'] ?? 'index, follow'),
            'noindex' => str_contains(strtolower((string) ($seo['robots'] ?? '')), 'noindex'),
            'verification' => (string) ($seo['googleVerification'] ?? ''),
            'twitterSite' => (string) ($seo['twitterSite'] ?? ''),
            'favicon' => site_url((string) setting('general', 'favicon', ''), base_url('assets/logo-teresa.png')),
            'themeColor' => (string) setting('theme', 'primaryColor', '#0d9488'),
            /* 'system' is a browser decision, and the server cannot make it —
               the pre-paint script in the component reads the OS. What the
               setting can decide is the value before any of that runs. */
            'theme' => ((string) setting('theme', 'defaultTheme', 'system')) === 'dark' ? 'dark' : 'light',
        ], $this->geoMeta(), $overrides);

        /* Every page carries the hospital and the site; a page that has its own
           nodes — a doctor, a department — appends rather than replaces, so the
           graph is never a Physician with no employer in it. */
        $head['schema'] = array_merge(
            [Schema::organisation(), Schema::website()],
            (array) ($overrides['schema'] ?? [])
        );

        return $head;
    }

    /**
     * The three geo metas, from the same contact settings the map reads.
     *
     * Long superseded by the `Hospital` node's `geo` — kept because they cost
     * nothing, some local directories still parse them, and this hospital is
     * exactly the one-town case they were written for.
     */
    protected function geoMeta(): array
    {
        $city = trim((string) setting('contact', 'city', ''));
        $state = trim((string) setting('contact', 'state', ''));
        $lat = (float) setting('contact', 'mapLat', 0);
        $lng = (float) setting('contact', 'mapLng', 0);

        return [
            'geoRegion' => 'IN-WB',
            'geoPlacename' => trim($city . ($state !== '' ? ', ' . $state : '')),
            'geoPosition' => ($lat !== 0.0 && $lng !== 0.0) ? $lat . ';' . $lng : '',
        ];
    }

    /**
     * The breadcrumb node for a page below the home page.
     *
     * @param array $trail label => path, without the home entry
     */
    protected function crumbs(array $trail): array
    {
        $items = ['Home' => Schema::siteUrl()];

        foreach ($trail as $label => $path) {
            $items[$label] = Schema::siteUrl($path);
        }

        return Schema::breadcrumbs($items);
    }

    protected function header(array $overrides = []): array
    {
        $phone = site_primary_phone();

        return array_merge([
            'active' => $this->active,
            'departments' => $this->menuDepartments(),
            'siteName' => (string) setting('general', 'name', 'Teresa Memorial Hospital'),
            'logo' => site_url((string) setting('general', 'logo', ''), base_url('assets/logo-teresa.png')),
            'email' => site_primary_email(),
            'phone' => $phone['number'],
            'tel' => $phone['digits'],
            'whatsapp' => site_digits((string) setting('contact', 'whatsapp', '')),
        ], $overrides);
    }

    /**
     * The footer's five columns.
     *
     * Three of them are nav_items — the panel's Navigation screen edits
     * `footer-1`, `footer-2` and `footer-3`. A location with no rows falls
     * through to the component's own defaults rather than rendering an empty
     * column, so the footer never has a hole in it while somebody is still
     * filling the screen in.
     */
    protected function footer(array $overrides = []): array
    {
        $columns = [];

        foreach ([
            'footer-1' => 'Community',
            'footer-2' => 'About',
            'footer-3' => 'Support',
        ] as $location => $title) {
            $items = nav_for_location($location);

            if (!$items) {
                continue;
            }

            $columns[] = [
                'title' => $title,
                'links' => array_map(static fn ($item) => [
                    'label' => $item['label'] ?? '',
                    'href' => site_url((string) ($item['href'] ?? '')),
                ], $items),
            ];
        }

        $footer = [
            'siteName' => (string) setting('general', 'name', 'Teresa Memorial Hospital'),
            'logo' => site_url((string) setting('general', 'logo', ''), base_url('assets/logo-teresa.png')),
            'home' => base_url('/'),
            'address' => site_address_lines(),
            'hours' => $this->openingHours(),
            'social' => $this->social(),
            'tagline' => (string) setting('general', 'taglineBn', 'মানুষের সাথে ..... মানুষের পাশে'),
            'copyright' => '© ' . date('Y') . ' '
                . setting('general', 'name', 'Teresa Memorial Hospital') . '. All rights reserved.',
        ];

        if ($columns) {
            $footer['columns'] = $columns;
        }

        return array_merge($footer, $overrides);
    }

    /**
     * Visiting hours, collapsed into the two or three lines the footer prints.
     *
     * The panel stores one row per day because that is how somebody edits it,
     * and printing seven lines in a footer column is not what anybody wants to
     * read. Consecutive days that keep the same hours are joined into a range.
     */
    protected function openingHours(): array
    {
        $rows = [];

        foreach ((array) setting('general', 'openingHours', []) as $day) {
            if (!is_array($day) || trim((string) ($day['day'] ?? '')) === '') {
                continue;
            }

            $from = trim((string) ($day['from'] ?? ''));
            $to = trim((string) ($day['to'] ?? ''));

            /* A round-the-clock day is stored as the widest span the panel can
               express, which is also what schema.org wants. Nobody reads
               "00:00 – 23:59" as "always open", so say it in words. */
            $isAllDay = $from === '00:00' && in_array($to, ['23:59', '24:00'], true);

            $value = !empty($day['closed'])
                ? 'Closed'
                : ($isAllDay ? 'Open 24 hours' : $from . ' – ' . $to);

            $last = count($rows) - 1;

            if ($last >= 0 && $rows[$last]['value'] === $value) {
                $rows[$last]['days'][] = (string) $day['day'];
                continue;
            }

            $rows[] = ['days' => [(string) $day['day']], 'value' => $value];
        }

        return array_map(static function (array $row): array {
            $days = $row['days'];
            $label = count($days) === 1
                ? $days[0]
                /* A literal dash, not `&ndash;` — the footer runs the label
                   through e() and an entity would come out double-escaped. */
                : $days[0] . ' – ' . $days[count($days) - 1];

            return ['label' => $label, 'value' => $row['value']];
        }, $rows);
    }

    /** The footer's social row, from the settings repeater. */
    protected function social(): array
    {
        $icons = [
            'facebook' => 'fa-brands fa-facebook-f',
            'x' => 'fa-brands fa-x-twitter',
            'twitter' => 'fa-brands fa-x-twitter',
            'youtube' => 'fa-brands fa-youtube',
            'linkedin' => 'fa-brands fa-linkedin-in',
            'instagram' => 'fa-brands fa-instagram',
            'whatsapp' => 'fa-brands fa-whatsapp',
        ];

        $out = [];

        foreach ((array) setting('social', 'social', []) as $row) {
            $url = trim((string) ($row['url'] ?? ''));
            $platform = trim((string) ($row['platform'] ?? ''));

            if ($url === '' || $platform === '') {
                continue;
            }

            $out[] = [
                'icon' => $icons[strtolower($platform)] ?? 'fa-solid fa-link',
                'url' => $url,
                'label' => $platform,
            ];
        }

        return $out;
    }

    /**
     * Both popup records, in the shape their widgets take.
     *
     * Returned together rather than rendered here because the caller needs to
     * know whether either is on: with both off, assets/popups.js is a request
     * for nothing.
     */
    protected function popups(): array
    {
        $p = settings_group('popups');

        return [
            'cookie' => [
                'enabled' => !empty($p['cookieEnabled']),
                'message' => (string) ($p['cookieMessage'] ?? ''),
                'acceptLabel' => (string) ($p['cookieAcceptLabel'] ?? 'Got it'),
                'declineLabel' => (string) ($p['cookieDeclineLabel'] ?? ''),
                'policyUrl' => site_url((string) ($p['cookiePolicyUrl'] ?? '')),
                'remember' => (int) ($p['cookieRemember'] ?? 180),
            ],
            'ads' => [
                'enabled' => !empty($p['adsEnabled']),
                'title' => (string) ($p['adsTitle'] ?? ''),
                'body' => (string) ($p['adsBody'] ?? ''),
                'image' => site_url((string) ($p['adsImage'] ?? '')),
                'link' => site_url((string) ($p['adsLink'] ?? '')),
                'linkLabel' => (string) ($p['adsLinkLabel'] ?? 'Learn more'),
                'start' => (string) ($p['adsStart'] ?? ''),
                'end' => (string) ($p['adsEnd'] ?? ''),
                'frequency' => (string) ($p['adsFrequency'] ?? 'session'),
                'dismissible' => !isset($p['adsDismissible']) || (bool) $p['adsDismissible'],
            ],
        ];
    }

    /* ---------------------------------------------------------
       Shared reads
       --------------------------------------------------------- */

    protected function menuDepartments(): array
    {
        return $this->menuDepartments ??= departments_for_menu();
    }

    /**
     * A phone number as the `{href, label, icon}` a button takes.
     *
     * Empty when there is no number, rather than a `tel:` to nothing — the
     * blocks that take this all skip a button whose `href` is missing, so a
     * hospital that has not filled its contact settings in gets one button
     * instead of a dead second one.
     *
     * @param array $phone ['number', 'digits'], as site_primary_phone() returns
     */
    protected function callAction(array $phone): array
    {
        return ($phone['number'] ?? '') === ''
            ? []
            : ['href' => 'tel:' . $phone['digits'], 'label' => $phone['number'], 'icon' => 'fa-phone'];
    }

    /**
     * The canonical URL for this request.
     *
     * Built from the route rather than from REQUEST_URI, so a page reached
     * with a tracking parameter on the end does not declare itself canonical
     * at that address. `canonicalDomain` wins over APP_URL when it is set —
     * the panel has a field for it and a site behind a proxy may not know its
     * own public name.
     */
    protected function canonical(): string
    {
        $route = trim((string) ($_GET['route'] ?? ''), '/');
        $domain = rtrim(trim((string) setting('seo', 'canonicalDomain', '')), '/');

        if ($domain === '') {
            return $route === '' ? base_url('/') : base_url($route);
        }

        return $route === '' ? $domain . '/' : $domain . '/' . $route;
    }

    /**
     * The photo a page falls back to when its own is missing.
     *
     * One setting, edited on the panel's SEO screen, and already what the
     * document head uses for `og:image` — so a banner with no picture of its
     * own shows the same thing a shared link does, rather than a gap.
     */
    protected function defaultImage(): string
    {
        return (string) setting('seo', 'defaultOgImage', '');
    }

    /**
     * The SEO record a resource carries, folded into head() props.
     *
     * seo_meta is polymorphic and optional: a doctor with no row is not a
     * doctor with an empty title, it is a doctor whose title is the page's own.
     */
    protected function seoHead(string $entityType, string $entityId, array $defaults): array
    {
        $row = seo_for($entityType, $entityId) ?? [];
        $metaTitle = trim((string) ($row['metaTitle'] ?? ''));

        /* A meta title written in the panel is the whole <title>, suffix and
           all — the field shows a search-result preview beside it, so what is
           typed is what was wanted. That is `titleFull`. `title` is the page's
           own name, which the component then appends the site name to. */
        $head = $metaTitle === ''
            ? ['title' => (string) ($defaults['title'] ?? '')]
            : ['titleFull' => $metaTitle];

        $head['description'] = trim((string) ($row['metaDescription'] ?? ''))
            ?: (string) ($defaults['description'] ?? '');
        $head['ogImage'] = trim((string) ($row['ogImage'] ?? ''))
            ?: (string) ($defaults['ogImage'] ?? '');
        $head['keywords'] = trim((string) ($row['keywords'] ?? ''))
            ?: (string) ($defaults['keywords'] ?? '');

        /* Not filtered below, and deliberately: 'website' is a real value the
           caller may have chosen, and an array of nodes is neither '' nor null. */
        foreach (['ogType', 'schema'] as $passthrough) {
            if (isset($defaults[$passthrough])) {
                $head[$passthrough] = $defaults[$passthrough];
            }
        }

        if (trim((string) ($row['canonical'] ?? '')) !== '') {
            $head['canonical'] = (string) $row['canonical'];
        }

        if (!empty($row['noindex'])) {
            $head['noindex'] = true;
        }

        return array_filter($head, static fn ($value) => $value !== '' && $value !== null);
    }

    /**
     * The first sentence or so of a body of markup, for a meta description.
     *
     * Descriptions are stripped rather than escaped: this ends up in a
     * content= attribute, and a <strong> that survived into it would close it.
     */
    protected function summarise(?string $markup, int $limit = 160): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $markup)) ?? '');

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space === false ? $cut : mb_substr($cut, 0, $space), " ,.;:") . '…';
    }
}
