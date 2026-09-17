<?php
/**
 * Page header / breadcrumb banner component.
 *
 * Variables: $pageTitle (string) — displayed in the banner
 *            $breadcrumbs (array) — ['Label' => 'url', ...], last item has no link
 */
$pageTitle = $pageTitle ?? 'Page';
$breadcrumbs = $breadcrumbs ?? [];
?>
    <!-- Page Header Start -->
    <div class="container-fluid page-header py-5 mb-5">
        <div class="container py-5">
            <h1 class="display-3 text-white mb-3 animated slideInDown"><?= e($pageTitle) ?></h1>
            <nav aria-label="breadcrumb animated slideInDown">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a class="text-white" href="<?= base_url('/') ?>">Home</a></li>
<?php
$keys = array_keys($breadcrumbs);
$lastKey = end($keys);
foreach ($breadcrumbs as $label => $url):
    if ($label === $lastKey): ?>
                    <li class="breadcrumb-item text-white active" aria-current="page"><?= e($label) ?></li>
<?php else: ?>
                    <li class="breadcrumb-item"><a class="text-white" href="<?= e($url) ?>"><?= e($label) ?></a></li>
<?php endif;
endforeach; ?>
                </ol>
            </nav>
        </div>
    </div>
    <!-- Page Header End -->
