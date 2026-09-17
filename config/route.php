<?php
require_once __DIR__ . '/bootstrap.php';

/* Models are function files, not classes — there is no class name to find
   them by, so they are required outright. Recursive, so app/models/Site/
   works without another glob. */
foreach (['app/models'] as $dir) {
    $path = __BASEDIR__ . '/' . $dir;

    if (!is_dir($path)) {
        continue;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            require_once $file->getPathname();
        }
    }
}

/**
 * Controllers are found by class name rather than required by directory, so
 * that adding a controller is still adding a file — and so that the order they
 * are loaded in cannot matter.
 *
 * It used to matter. Two controllers extend ResourceController, and a
 * directory walk hands files back in whatever order the filesystem lists them,
 * which is not alphabetical and is not stable across machines. Requiring
 * ApplicationController before its parent is a fatal error at compile time,
 * and nothing about the repository says which order a given server will pick.
 * An autoloader has no order to get wrong: `class ApplicationController
 * extends ResourceController` asks for the parent, and it arrives.
 *
 * The map is built once, on the first class that is not already loaded — a
 * request that only touches core classes never scans the directories at all.
 */
spl_autoload_register(static function (string $class): void {
    static $map = null;

    if ($map === null) {
        $map = [];

        foreach (['app/controllers', 'api/controllers'] as $dir) {
            $path = __BASEDIR__ . '/' . $dir;

            if (!is_dir($path)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    /* Keyed on the basename, which is the convention every
                       controller in this repository already follows. */
                    $map[$file->getBasename('.php')] ??= $file->getPathname();
                }
            }
        }
    }

    if (isset($map[$class])) {
        require_once $map[$class];
    }
});

RouteManager::dispatch($routes);
