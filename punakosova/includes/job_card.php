<?php
/**
 * Karta e një pune. Pret: $job (me company_name, logo, category_name) dhe $savedIds (array).
 */
$savedIds = $savedIds ?? [];
$isSaved = in_array((int) $job['id'], $savedIds, true);
$left = days_left($job['deadline']);
?>
<article class="job-card">
    <div class="job-card-head">
        <?= company_logo($job['logo'] ?? null, $job['company_name']) ?>
        <div style="flex:1;min-width:0">
            <h3><a href="<?= url('jobs/view.php?id=' . (int) $job['id']) ?>"><?= e($job['title']) ?></a></h3>
            <div class="job-company"><?= e($job['company_name']) ?></div>
        </div>
        <?php if (has_role('candidate')): ?>
            <form action="<?= url('jobs/save.php') ?>" method="post" data-save-job data-job="<?= (int) $job['id'] ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
                <button type="submit" class="save-btn<?= $isSaved ? ' saved' : '' ?>" title="<?= $isSaved ? 'Hiqe nga të ruajturat' : 'Ruaje punën' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                </button>
            </form>
        <?php endif; ?>
    </div>
    <div class="job-meta">
        <span class="chip">📍 <?= e($job['city']) ?></span>
        <span class="chip chip-red"><?= e(label(employment_types(), $job['employment_type'])) ?></span>
        <?php if (!empty($job['category_name'])): ?><span class="chip"><?= e($job['category_name']) ?></span><?php endif; ?>
    </div>
    <div class="job-card-foot">
        <span class="salary"><?= e(format_salary($job['salary_min'], $job['salary_max'])) ?></span>
        <span class="deadline<?= $left <= 5 ? ' urgent' : '' ?>">
            <?= $left < 0 ? 'Afati ka kaluar' : ($left === 0 ? 'Afati: sot' : 'Edhe ' . $left . ' ditë') ?>
        </span>
    </div>
</article>
