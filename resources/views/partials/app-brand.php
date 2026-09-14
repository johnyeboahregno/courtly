<?php

/**
 * Shared Courtly brand lockup — the mark plus the COURTLY wordmark.
 *
 * Rendered by `partials/app-header.php` (every standard screen) and by the live
 * session header, so the logo is identical everywhere instead of each screen
 * inventing its own.
 *
 * Expects an optional $base; falls back to the current request's base path.
 */
$base = $base ?? rtrim(request()->getBasePath(), '/');
?>
<a class="app-brand" href="<?= e($base) ?>/circles" title="Circles home">
    <img src="<?= e($base) ?>/assets/courtly-mark.png" alt="Courtly" class="app-brand__img app-brand__img--light">
    <img src="<?= e($base) ?>/assets/courtly-mark-dark.png" alt="Courtly" class="app-brand__img app-brand__img--dark">
    <span>COURT<b>LY</b><em class="app-version"><?= e(config('courtly.app.version_label', '.beta')) ?></em></span>
</a>
