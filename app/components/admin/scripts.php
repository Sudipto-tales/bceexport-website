<?php

/**
 * The script block and the closing tags.
 *
 * Order matters and is the prototype's, not a preference: these are classic,
 * non-deferred scripts that register themselves on `window.TMH` as they parse,
 * so a module always loads after the ones it calls into. `form.js` reaches for
 * the repeater, the media picker and the multi-select when it binds a record;
 * `layout.js` mounts last because it paints the shell from everything above it.
 *
 * The bundles below are the CORE table from the prototype's scaffolder,
 * checked against the script list of all 41 prototype screens, and kept as
 * data rather than baked into each shell so that adding a module to every form
 * screen stays a one-line change.
 *
 * Props
 *   type    string  Screen type; picks the bundle.
 *   script  string  Page script under assets/admin/js/pages/, without .js.
 */

$bundles = [
    'plain' => [],
    'list' => ['table'],
    'media' => ['media'],
    'form' => ['repeater', 'media', 'multiselect', 'fields', 'form'],
    'editor' => ['repeater', 'media', 'multiselect', 'editor', 'fields', 'form'],
    'listform' => ['table', 'repeater', 'media', 'multiselect', 'fields', 'form'],

    /* FAQs edits its answers through the rich-text pad, so it needs the editor
       on top of the list bundle. scaffold.mjs called this one `listeditor` and
       the prototype's checked-in FAQs screen carried the extra script — a bundle cannot be
       forgotten, a hand-edited file can. */
    'listeditor' => ['table', 'repeater', 'media', 'multiselect', 'editor', 'fields', 'form'],

    /* doctor-form is the one screen whose fields are written out rather than
       generated, so it is also the one that does not load fields.js. It exists
       as the readable reference for what the generated markup corresponds to —
       see assets/admin/js/core/fields.js. */
    'form-static' => ['repeater', 'multiselect', 'media', 'form'],
];

$type = $type ?? 'plain';

$core = array_merge(
    ['util', 'nav', 'toast', 'modal', 'api', 'session'],
    $bundles[$type] ?? [],
    ['layout']
);
?>

    <!-- core, in dependency order -->
<?php foreach ($core as $module): ?>
    <script src="<?= e(base_url("assets/admin/js/core/{$module}.js")) ?>"></script>
<?php endforeach; ?>

    <!-- The prototype loaded assets/data/*.js here to seed store.js's
         localStorage mock. api.js reads /api/* instead, and a seed file
         shipped beside it would be a second copy of the content, stale from
         the first save. -->

    <script src="<?= e(base_url("assets/admin/js/pages/{$script}.js")) ?>"></script>
</body>

</html>
