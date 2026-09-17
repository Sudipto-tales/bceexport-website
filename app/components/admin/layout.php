<?php

/**
 * The whole panel page, so that a screen shell is a single call.
 *
 * This is the point of the port. In the prototype every screen carried its own
 * copy of the head, the mount points and the script list — 45 lines repeated
 * 41 times, which is how a panel ends up with three different stylesheet
 * orders and one file whose extra <script> disappears on the next generator
 * run. Here a screen declares four facts about itself and inherits the rest.
 *
 * Props
 *   page    string  <body data-page>; the active sidebar key.
 *   title   string  Document title.
 *   type    string  Script bundle — see admin/scripts.php.
 *   script  string  Page script name; defaults to the sidebar key, which is
 *                   only wrong on a form screen (doctor-form lights `doctors`).
 *   body    string  Component holding hand-written markup, for the two screens
 *                   that have any. Omitted, the screen gets the empty
 *                   #pageHead / #view pair its page script renders into.
 */

$type = $type ?? 'plain';
$script = $script ?? $page;

App::render('admin/head', ['title' => $title ?? '', 'page' => $page ?? '']);
?>
    <div class="app">
<?php App::render('admin/sidebar'); ?>

        <div class="shell">
<?php App::render('admin/topbar'); ?>

            <main class="main">
<?php if (!empty($body)): ?>
<?php App::render($body); ?>
<?php else: ?>
                <div id="pageHead"></div>
                <div id="view"></div>
<?php endif; ?>
            </main>
        </div>
    </div>
<?php App::render('admin/scripts', ['type' => $type, 'script' => $script]); ?>
