<?php

/**
 * The panel's document shell — <!DOCTYPE> through the opening <body>.
 *
 * A near-copy of the head the prototype's scaffolder wrote, and kept
 * matching on purpose: the stylesheet order and the pre-paint theme script are
 * what the prototype was reviewed against, and the panel's CSS depends on both.
 *
 * Props
 *   title  string  Screen title; the panel's name is appended.
 *   page   string  Goes on <body data-page>. core/layout.js reads it to light
 *                  the active sidebar item and every page script switches on
 *                  it. A form screen names its list (doctor-form → doctors).
 */
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">

    <!-- Theme resolved before the stylesheet so the first paint is already
         correct — a deferred script would flash the light panel first. -->
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('tmh-admin-theme');
                document.documentElement.dataset.theme = stored
                    || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            } catch (e) { /* private mode — stay on the light default */ }
        })();
    </script>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? '') ?> &mdash; BCE Export Admin</title>

    <meta name="robots" content="noindex,nofollow">

    <!-- The panel is a set of fetch() calls, so the CSRF token has to be
         readable from JavaScript; core/Csrf.php checks it back as the
         X-CSRF-Token header. A meta rather than an inline script because the
         value is a string, not code, and a meta cannot be mistaken for one.
         The base is here for the same reason: this application can be
         installed in a subdirectory, and a hardcoded "/api" would 404. -->
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <meta name="app-base" content="<?= e(rtrim(base_url('/'), '/')) ?>/">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Baloo+2:wght@600;700;800&family=Noto+Sans+Bengali:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<?php foreach (['tokens', 'base', 'layout', 'components'] as $sheet): ?>
    <link rel="stylesheet" href="<?= e(base_url("assets/admin/css/{$sheet}.css")) ?>">
<?php endforeach; ?>
</head>

<body data-page="<?= e($page ?? '') ?>">
