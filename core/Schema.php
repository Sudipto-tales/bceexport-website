<?php

/**
 * schema.org JSON-LD, built from the settings the site already reads.
 *
 * Every method returns a plain array; the head component encodes the lot as
 * one `@graph`. Nothing here queries — the address, phones and hours come from
 * all_settings(), which is one read per request, so a page that emits five
 * nodes costs no more than a page that emits one.
 *
 * Nodes carry `@id` so they can point at each other rather than repeat: a
 * department's `parentOrganization` is a reference to the hospital node already
 * in the graph, not a second copy of the hospital.
 */
final class Schema
{
    /** The district, in both spellings people search. */
    private const AREA_SERVED = ['Bardhaman', 'Burdwan', 'Purba Bardhaman', 'West Bengal', 'India'];

    /**
     * The graph, ready for a <script type="application/ld+json">.
     *
     * Empty nodes are dropped rather than emitted hollow — an `Article` with
     * nothing but an `@type` tells a crawler less than no node at all.
     */
    public static function graph(array $nodes): string
    {
        $nodes = array_values(array_filter($nodes, static fn ($n) => is_array($n) && count($n) > 1));

        if (!$nodes) {
            return '';
        }

        /* HEX_TAG and HEX_AMP are the reason this is not plain json_encode:
           the result is printed inside a <script>, and an article excerpt
           containing "</script>" would otherwise close it and leave the rest as
           markup. Escaped as < they stay valid JSON and inert HTML. */
        return (string) json_encode(
            ['@context' => 'https://schema.org', '@graph' => $nodes],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        );
    }

    /** The site's own root, with the canonical domain winning over APP_URL. */
    public static function siteUrl(string $path = ''): string
    {
        $domain = rtrim(trim((string) setting('seo', 'canonicalDomain', '')), '/');
        $path = ltrim($path, '/');

        if ($domain === '') {
            return $path === '' ? base_url('/') : base_url($path);
        }

        return $path === '' ? $domain . '/' : $domain . '/' . $path;
    }

    public static function id(string $fragment): string
    {
        return self::siteUrl() . '#' . $fragment;
    }

    /**
     * The hospital itself — the node every other page node hangs off.
     *
     * `Hospital` rather than `MedicalOrganization` alone: it is the narrower
     * type, and both are declared so a consumer that only knows the broader
     * one still matches.
     */
    public static function organisation(): array
    {
        $name = (string) setting('general', 'name', 'Teresa Memorial Hospital');
        $phone = site_primary_phone();
        $email = site_primary_email();
        $logo = site_url((string) setting('general', 'logo', ''), base_url('assets/logo-teresa.png'));

        $node = [
            '@type' => ['Hospital', 'MedicalOrganization'],
            '@id' => self::id('organisation'),
            'name' => $name,
            'url' => self::siteUrl(),
            'logo' => $logo,
            'image' => (string) setting('seo', 'defaultOgImage', '') ?: $logo,
            'description' => (string) setting('seo', 'defaultDescription', ''),
            'address' => self::postalAddress(),
            'areaServed' => self::AREA_SERVED,
            'isAcceptingNewPatients' => true,
        ];

        if (($short = trim((string) setting('general', 'shortName', ''))) !== '' && $short !== $name) {
            $node['alternateName'] = $short;
        }

        if (($founded = (int) setting('general', 'establishedYear', 0)) > 0) {
            $node['foundingDate'] = (string) $founded;
        }

        if ($phone['number'] !== '') {
            $node['telephone'] = $phone['number'];
        }

        if ($email !== '') {
            $node['email'] = $email;
        }

        if ($geo = self::geo()) {
            $node['geo'] = $geo;
            $node['hasMap'] = 'https://www.google.com/maps/search/?api=1&query='
                . $geo['latitude'] . ',' . $geo['longitude'];
        }

        if ($hours = self::openingHours()) {
            $node['openingHoursSpecification'] = $hours;
        }

        if ($points = self::contactPoints()) {
            $node['contactPoint'] = $points;
        }

        if ($sameAs = self::sameAs()) {
            $node['sameAs'] = $sameAs;
        }

        if ($specialities = self::specialities()) {
            $node['medicalSpecialty'] = $specialities;
        }

        return $node;
    }

    /**
     * The site, and who built it.
     *
     * `creator` is the machine-readable half of the footer's credit link — the
     * anchor is what a person follows, this is what a crawler reads.
     */
    public static function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => self::id('website'),
            'url' => self::siteUrl(),
            'name' => (string) setting('general', 'name', 'Teresa Memorial Hospital'),
            'inLanguage' => 'en-IN',
            'publisher' => ['@id' => self::id('organisation')],
            'creator' => [
                '@type' => 'Organization',
                'name' => 'Promix',
                'url' => 'https://promix.tech/',
            ],
        ];
    }

    /**
     * The trail, as [label => url] in order, home first.
     *
     * One item is not a trail — the home page gets no breadcrumbs rather than a
     * list of itself.
     */
    public static function breadcrumbs(array $trail): array
    {
        if (count($trail) < 2) {
            return [];
        }

        $items = [];
        $position = 0;

        foreach ($trail as $label => $url) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => ++$position,
                'name' => (string) $label,
                'item' => (string) $url,
            ];
        }

        return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    /** One department, as a unit of the hospital. */
    public static function medicalClinic(array $department, string $url): array
    {
        $node = [
            '@type' => ['MedicalClinic', 'MedicalBusiness'],
            '@id' => $url . '#department',
            'name' => (string) ($department['name'] ?? ''),
            'url' => $url,
            'parentOrganization' => ['@id' => self::id('organisation')],
            'address' => self::postalAddress(),
            'areaServed' => self::AREA_SERVED,
        ];

        $lead = trim(strip_tags((string) ($department['lead'] ?? '')));

        if ($lead !== '') {
            $node['description'] = self::clip($lead, 300);
        }

        if (($banner = trim((string) ($department['banner'] ?? ''))) !== '') {
            $node['image'] = $banner;
        }

        if (($name = trim((string) ($department['name'] ?? ''))) !== '') {
            $node['medicalSpecialty'] = $name;
        }

        if ($geo = self::geo()) {
            $node['geo'] = $geo;
        }

        if ($hours = self::openingHours()) {
            $node['openingHoursSpecification'] = $hours;
        }

        return $node;
    }

    /** One article. */
    public static function article(array $post, string $url, array $author = []): array
    {
        $node = [
            '@type' => ['MedicalWebPage', 'Article'],
            '@id' => $url . '#article',
            'headline' => self::clip((string) ($post['title'] ?? ''), 110),
            'url' => $url,
            'mainEntityOfPage' => $url,
            'publisher' => ['@id' => self::id('organisation')],
            'inLanguage' => 'en-IN',
        ];

        $excerpt = trim(strip_tags((string) ($post['excerpt'] ?? '')));

        if ($excerpt !== '') {
            $node['description'] = self::clip($excerpt, 300);
        }

        if (($cover = trim((string) ($post['coverImage'] ?? ''))) !== '') {
            $node['image'] = $cover;
        }

        foreach (['datePublished' => 'publishedAt', 'dateModified' => 'updatedAt'] as $key => $field) {
            $stamp = strtotime((string) ($post[$field] ?? '')) ?: null;

            if ($stamp !== null) {
                $node[$key] = date(DATE_ATOM, $stamp);
            }
        }

        $node['dateModified'] ??= $node['datePublished'] ?? null;
        $node = array_filter($node, static fn ($v) => $v !== null);

        if (($name = trim((string) ($author['name'] ?? ''))) !== '') {
            $node['author'] = ['@type' => 'Person', 'name' => $name];
        }

        return $node;
    }

    /**
     * One vacancy.
     *
     * `validThrough` is deliberately only set when the row has a closing date:
     * Google drops a JobPosting whose date has passed, and inventing one would
     * un-list a job that is still open.
     */
    public static function jobPosting(array $job, string $url): array
    {
        $node = [
            '@type' => 'JobPosting',
            '@id' => $url . '#job',
            'title' => (string) ($job['title'] ?? ''),
            'url' => $url,
            'hiringOrganization' => ['@id' => self::id('organisation')],
            'jobLocation' => ['@type' => 'Place', 'address' => self::postalAddress()],
            'directApply' => true,
        ];

        $summary = trim(strip_tags((string) ($job['summary'] ?? $job['description'] ?? '')));

        if ($summary !== '') {
            $node['description'] = self::clip($summary, 500);
        }

        if (($location = trim((string) ($job['location'] ?? ''))) !== '') {
            $node['jobLocation']['name'] = $location;
        }

        if (($type = self::employmentType((string) ($job['type'] ?? ''))) !== '') {
            $node['employmentType'] = $type;
        }

        if (($dept = trim((string) ($job['dept'] ?? ''))) !== '') {
            $node['occupationalCategory'] = $dept;
        }

        if (($openings = (int) ($job['openings'] ?? 0)) > 0) {
            $node['totalJobOpenings'] = $openings;
        }

        if (($experience = trim((string) ($job['experience'] ?? ''))) !== '') {
            $node['experienceRequirements'] = $experience;
        }

        if ($salary = self::salary($job)) {
            $node['baseSalary'] = $salary;
        }

        foreach (['datePosted' => 'postedAt', 'validThrough' => 'closesAt'] as $key => $field) {
            $stamp = strtotime((string) ($job[$field] ?? '')) ?: null;

            if ($stamp !== null) {
                $node[$key] = date(DATE_ATOM, $stamp);
            }
        }

        return $node;
    }

    /**
     * "Full time" → FULL_TIME.
     *
     * The column is free text a panel user types, and schema.org takes an
     * enumeration. Anything outside it is dropped rather than passed through:
     * an invalid employmentType invalidates the whole posting, and no value at
     * all is valid.
     */
    private static function employmentType(string $stored): string
    {
        $key = strtoupper(preg_replace('/[^a-z]+/i', '_', trim($stored)) ?? '');

        foreach ([
            'FULL_TIME' => ['FULL_TIME', 'FULLTIME', 'PERMANENT'],
            'PART_TIME' => ['PART_TIME', 'PARTTIME'],
            'CONTRACTOR' => ['CONTRACTOR', 'CONTRACT', 'LOCUM'],
            'TEMPORARY' => ['TEMPORARY', 'TEMP', 'RELIEF'],
            'INTERN' => ['INTERN', 'INTERNSHIP', 'TRAINEE'],
            'VOLUNTEER' => ['VOLUNTEER'],
        ] as $value => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    return $value;
                }
            }
        }

        return '';
    }

    /**
     * The pay band, monthly.
     *
     * `salaryNote` is deliberately not folded in — "Plus night differential" is
     * a sentence, and MonetaryAmount has nowhere truthful to put it.
     */
    private static function salary(array $job): array
    {
        $from = (int) ($job['salaryFrom'] ?? 0);
        $to = (int) ($job['salaryTo'] ?? 0);

        if ($from <= 0 && $to <= 0) {
            return [];
        }

        $value = array_filter([
            '@type' => 'QuantitativeValue',
            'minValue' => $from ?: null,
            'maxValue' => $to ?: null,
            'unitText' => 'MONTH',
        ], static fn ($v) => $v !== null);

        return ['@type' => 'MonetaryAmount', 'currency' => 'INR', 'value' => $value];
    }

    /**
     * The questions an accordion already answers.
     *
     * @param array $faqs rows of {question, answer}
     */
    public static function faqPage(array $faqs, string $url): array
    {
        $items = [];

        foreach ($faqs as $faq) {
            $question = trim(strip_tags((string) ($faq['question'] ?? '')));
            $answer = trim(strip_tags((string) ($faq['answer'] ?? '')));

            if ($question === '' || $answer === '') {
                continue;
            }

            $items[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
            ];
        }

        if (!$items) {
            return [];
        }

        return ['@type' => 'FAQPage', '@id' => $url . '#faq', 'mainEntity' => $items];
    }

    /* ---------------------------------------------------------
       The pieces the nodes share
       --------------------------------------------------------- */

    public static function postalAddress(): array
    {
        $street = [];

        foreach ((array) setting('contact', 'addressLines', []) as $row) {
            $line = trim((string) (is_array($row) ? ($row['line'] ?? '') : $row));

            if ($line !== '') {
                $street[] = $line;
            }
        }

        return array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => implode(', ', $street),
            'addressLocality' => trim((string) setting('contact', 'city', '')),
            'addressRegion' => trim((string) setting('contact', 'state', '')),
            'postalCode' => trim((string) setting('contact', 'pincode', '')),
            'addressCountry' => 'IN',
        ], static fn ($v) => $v !== '');
    }

    private static function geo(): array
    {
        $lat = (float) setting('contact', 'mapLat', 0);
        $lng = (float) setting('contact', 'mapLng', 0);

        if ($lat === 0.0 || $lng === 0.0) {
            return [];
        }

        return ['@type' => 'GeoCoordinates', 'latitude' => $lat, 'longitude' => $lng];
    }

    /**
     * Visiting hours, one spec per day.
     *
     * The panel stores a row per day and schema.org wants the same, so this is
     * a straight map — unlike the footer, which collapses them into ranges a
     * person reads.
     */
    private static function openingHours(): array
    {
        $out = [];

        foreach ((array) setting('general', 'openingHours', []) as $row) {
            if (!is_array($row) || !empty($row['closed'])) {
                continue;
            }

            $day = trim((string) ($row['day'] ?? ''));
            $from = trim((string) ($row['from'] ?? ''));
            $to = trim((string) ($row['to'] ?? ''));

            if ($day === '' || $from === '' || $to === '') {
                continue;
            }

            $out[] = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'https://schema.org/' . $day,
                'opens' => $from,
                'closes' => $to,
            ];
        }

        return $out;
    }

    private static function contactPoints(): array
    {
        $out = [];
        $seen = [];

        foreach ((array) setting('contact', 'phones', []) as $row) {
            $number = trim((string) (is_array($row) ? ($row['number'] ?? '') : $row));

            if ($number === '' || isset($seen[$number])) {
                continue;
            }

            $seen[$number] = true;

            $out[] = array_filter([
                '@type' => 'ContactPoint',
                'telephone' => $number,
                'contactType' => trim((string) (is_array($row) ? ($row['label'] ?? '') : '')) ?: 'reception',
                'areaServed' => 'IN',
                'availableLanguage' => ['English', 'Bengali', 'Hindi'],
            ]);
        }

        return $out;
    }

    private static function sameAs(): array
    {
        $out = [];

        foreach ((array) setting('social', 'social', []) as $row) {
            $url = trim((string) (is_array($row) ? ($row['url'] ?? '') : $row));

            if ($url !== '' && $url !== '#' && str_starts_with($url, 'http')) {
                $out[] = $url;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * The specialities the hospital lists, taken from the departments.
     *
     * Guarded: this is the one place in the file that queries, and a graph is
     * not worth a fatal on a site whose departments table is missing.
     */
    private static function specialities(): array
    {
        if (!function_exists('departments_published')) {
            return [];
        }

        $names = [];

        foreach (departments_published() as $department) {
            $name = trim((string) ($department['name'] ?? ''));

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /** Trimmed at a word, for the fields with a length a crawler respects. */
    private static function clip(string $text, int $limit): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space === false ? $cut : mb_substr($cut, 0, $space), " ,.;:") . '…';
    }
}
