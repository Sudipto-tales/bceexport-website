<?php

/**
 * The sidebar mount point, and nothing else.
 *
 * It stays client-rendered. `assets/admin/js/core/layout.js` does
 *
 *     sidebarSlot.outerHTML = sidebarHtml(activeKey);
 *
 * when it mounts, so anything PHP wrote here would be replaced before the
 * first paint finished — a server-rendered nav would be work done twice and
 * thrown away once. The navigation itself lives in `core/nav.js`, which is the
 * panel's single source of truth for it, and two of its entries carry badge
 * counts read from the data layer after it loads. Moving the markup to PHP
 * would mean maintaining that list in two languages to gain nothing: the panel
 * is behind a login and does not have to render without JavaScript
 * (docs/php/06-decisions.md §1).
 */
?>
        <div id="sidebar"></div>
