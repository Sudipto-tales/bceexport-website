<?php

/**
 * Every collection, in one request, for the panel's synchronous reads.
 *
 * `store.js` served the panel from localStorage, so `allSync('departments')`
 * was a real answer at the moment a screen started painting — and sixty-four
 * calls across the forty-one page scripts rely on that. A doctor row shows its
 * departments, a post shows its author, an enquiry shows who it is assigned to;
 * none of them can await, because they run inside a table's `render(row)`.
 *
 * `api.js` cannot invent a synchronous fetch, so the cache those calls read is
 * filled before any page script runs — one request rather than eighteen, and
 * the same thing the prototype did with its nine seed files, which every screen
 * loaded whether it used them or not. The difference is that this is read from
 * the database on each page load and cannot be stale.
 *
 * A screen's own list is still `GET api/{resource}` — filtered, sorted and
 * paged by the server. This is only the lookup table beside it.
 *
 * It extends ResourceController for one method: `rows()`, which is what turns
 * database columns into the field names the panel reads. A second copy of that
 * mapping is the one thing this endpoint must not be.
 */
class BootstrapController extends ResourceController
{
    public function index(): never
    {
        $out = [];

        foreach (array_keys(ResourceRegistry::all()) as $name) {
            $r = ResourceRegistry::get($name);

            /* Not every table has a hand-ordered position — an appointment is
               not dragged into place, it has a date — so the order is the
               resource's own default, the same one its list endpoint falls back
               to when the caller names no sort. */
            $rows = db_fetch_all(
                'SELECT t.* FROM ' . $r['table'] . ' t WHERE t.deleted_at IS NULL' . $this->defaultOrder($r)
            );

            $out[$name] = $this->rows($r, $rows);
        }

        Api::ok($out, ['collections' => count($out)]);
    }
}
