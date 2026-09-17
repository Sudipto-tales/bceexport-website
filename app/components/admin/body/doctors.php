<?php

/**
 * The Doctors list screen's markup.
 *
 * Doctors was one of the two screens the prototype scaffolder never generated,
 * because its <main> is not the empty #pageHead / #view pair: doctors.js
 * paints a stat strip above the table and mounts the table into #listCard.
 * Copied from the prototype's doctors screen unchanged — the ids are what
 * the page script looks for.
 */
?>
                <div id="pageHead"></div>

                <div class="bento">
                    <div id="statStrip" class="c12 bento" style="gap:var(--s5)"></div>
                    <article class="card card--flush c12" id="listCard"></article>
                </div>
