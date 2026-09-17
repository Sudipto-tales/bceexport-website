<?php

/**
 * Reads config/resources.php and turns its shorthand into full field specs.
 *
 * The registry is written to be read by a person — `'name' => 'string'` rather
 * than `'name' => ['column' => 'name', 'type' => 'string', ...]` twenty times
 * over. Everything that expands that shorthand is here, so the controller
 * never sees two shapes for the same thing.
 */
class ResourceRegistry
{
    private static ?array $resources = null;

    /** Columns every resource has, whatever its registry entry says. */
    public const COMMON = ['id', 'order', 'status', 'createdAt', 'updatedAt', 'updatedBy'];

    public static function all(): array
    {
        if (self::$resources === null) {
            self::$resources = require __BASEDIR__ . '/config/resources.php';
        }

        return self::$resources;
    }

    public static function has(string $name): bool
    {
        return isset(self::all()[$name]);
    }

    public static function get(string $name): ?array
    {
        $resource = self::all()[$name] ?? null;

        if ($resource === null) {
            return null;
        }

        $resource['name'] = $name;
        $resource['fields'] = self::fields($resource);

        /* A resource may opt out of the publish workflow — a role and a
           redirect have no draft state. Those that keep it may still use their
           own vocabulary: an enquiry is new/replied/closed/spam, not
           draft/published/hidden. */
        $resource['hasStatus'] = ($resource['status'] ?? true) !== false;
        $resource['statusValues'] = $resource['statusValues'] ?? ['draft', 'published', 'hidden'];
        $resource['readonly'] = $resource['readonly'] ?? false;
        $resource['canCreate'] = !$resource['readonly'] && (($resource['create'] ?? true) !== false);
        $resource['required'] = $resource['required'] ?? [];
        $resource['unique'] = $resource['unique'] ?? [];
        $resource['dependents'] = $resource['dependents'] ?? [];
        $resource['filters'] = self::filters($resource);
        $resource['seo'] = $resource['seo'] ?? null;
        $resource['defaultSort'] = $resource['defaultSort'] ?? null;
        $resource['defaultDir'] = $resource['defaultDir'] ?? null;

        return $resource;
    }

    /* ---------------------------------------------------------
       Normalising
       --------------------------------------------------------- */

    private static function fields(array $resource): array
    {
        $out = [];

        foreach ($resource['fields'] ?? [] as $name => $spec) {
            if (is_string($spec)) {
                $spec = ['type' => $spec];
            }

            $spec['name'] = $name;
            $spec['type'] = $spec['type'] ?? 'string';
            $spec['column'] = $spec['column'] ?? self::snake($name);
            $spec['readonly'] = $spec['readonly'] ?? false;
            $spec['writeonly'] = $spec['writeonly'] ?? false;

            /* A join has no column of its own — it is a table. Leaving a
               column name on it would put `departments` in an UPDATE. */
            if ($spec['type'] === 'join') {
                $spec['column'] = null;
                $spec['target'] = $spec['target'] ?? null;
            }

            $out[$name] = $spec;
        }

        return $out;
    }

    private static function filters(array $resource): array
    {
        $out = [];

        foreach ($resource['filters'] ?? [] as $name => $spec) {
            if (is_string($spec)) {
                $spec = ['type' => $spec];
            }

            $spec['name'] = $name;
            $spec['type'] = $spec['type'] ?? 'string';

            if ($spec['type'] !== 'join') {
                $spec['column'] = $spec['column'] ?? self::snake($name);
            }

            $out[$name] = $spec;
        }

        return $out;
    }

    /** 'experienceYears' → 'experience_years' */
    public static function snake(string $value): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value);
    }

    /** 'experience_years' → 'experienceYears' */
    public static function camel(string $value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value))));
    }

    /**
     * The column a sort key names.
     *
     * Sorting is whitelisted per resource, so this only ever resolves a key
     * the registry already allowed — an unlisted key never reaches here, which
     * is what keeps `?sort=` out of the SQL.
     */
    public static function sortColumn(array $resource, string $key): ?string
    {
        if (!in_array($key, $resource['sort'] ?? [], true)) {
            return null;
        }

        if (isset($resource['fields'][$key])) {
            return $resource['fields'][$key]['column'];
        }

        return match ($key) {
            'id' => $resource['key'],
            'order' => 'sort_order',
            default => self::snake($key),
        };
    }
}
