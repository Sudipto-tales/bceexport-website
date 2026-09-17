<?php
/**
 * Page header / breadcrumb banner.
 *
 * $pageTitle   (string)
 * $breadcrumbs (array)  label => url; last item = current (no link)
 * $headerClass (string) about | services | Contact | leather | …
 */
$pageTitle   = $pageTitle ?? 'Page';
$breadcrumbs = $breadcrumbs ?? [];
$headerClass = $headerClass ?? '';
?>
    <!-- Page Header Start -->
    <div class="container-fluid page-header <?= e($headerClass) ?> py-5" style="margin-bottom: 6rem;">
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
