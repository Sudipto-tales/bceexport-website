<?php
App::render('admin/head', ['title' => 'Page Not Found', 'page' => '404']);
?>
    <div class="app">
<?php App::render('admin/sidebar'); ?>

        <div class="shell">
<?php App::render('admin/topbar'); ?>

            <main class="main" style="display:flex;align-items:center;justify-content:center;min-height:70vh;">
                <article class="card text-center" style="max-width:480px;padding:40px 24px;text-align:center;">
                    <div style="font-size:3.5rem;color:var(--brand-red,#FF3E41);margin-bottom:1rem;">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <h2 style="margin-bottom:0.5rem;">404 - Screen Not Found</h2>
                    <p class="muted" style="margin-bottom:1.5rem;color:var(--text-muted,#71717a);">The admin screen you requested does not exist or has been moved.</p>
                    <div>
                        <a href="<?= e(base_url('admin/dashboard')) ?>" class="btn btn--primary">
                            <i class="fa-solid fa-house"></i> Return to Dashboard
                        </a>
                    </div>
                </article>
            </main>
        </div>
    </div>
<?php App::render('admin/scripts', ['type' => 'plain', 'script' => 'dashboard']); ?>
