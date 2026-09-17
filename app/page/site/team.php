<?php
// Team page template
?>
<!-- Team Start -->
<div class="container-xxl py-5">
    <div class="container py-5">
        <div class="text-center wow fadeInUp" data-wow-delay="0.1s">
            <h6 class="text-secondary text-uppercase">Our Team</h6>
            <h1 class="mb-5">Expert Team Members</h1>
        </div>
        <div class="row g-4">
            <?php if (empty($team)): ?>
                <div class="col-12 text-center wow fadeInUp" data-wow-delay="0.3s">
                    <p>Team information coming soon.</p>
                </div>
            <?php else: ?>
                <?php $delay = 0.3; ?>
                <?php foreach ($team as $member): ?>
                <div class="col-lg-3 col-md-6 wow fadeInUp" data-wow-delay="<?= $delay ?>s">
                    <div class="team-item p-4">
                        <div class="overflow-hidden mb-4">
                            <?php if (!empty($member['photo'])): ?>
                            <img class="img-fluid" src="<?= base_url('img/' . e($member['photo'])) ?>" alt="<?= e($member['name']) ?>">
                            <?php else: ?>
                            <img class="img-fluid" src="<?= base_url('img/placeholder.png') ?>" alt="<?= e($member['name']) ?>">
                            <?php endif; ?>
                        </div>
                        <h5 class="mb-0"><?= e($member['name']) ?></h5>
                        <p><?= e($member['role']) ?></p>
                        <?php if (!empty($member['bio'])): ?>
                        <div class="mt-2 text-muted">
                            <small><?= e($member['bio']) ?></small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php 
                $delay += 0.2; 
                if ($delay > 0.9) $delay = 0.3;
                ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- Team End -->
