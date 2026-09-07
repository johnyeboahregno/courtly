<?php

/** @var string $base */
/** @var \Illuminate\Support\Collection $players */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title>Rankings - Courtly</title>
    <link rel="icon" type="image/png" href="<?= e($base ?? '') ?>/assets/favicon.png?v=<?= e(config('courtly.app.version', '1.0.0')) ?>">
    <link rel="stylesheet" href="<?= e($base ?? '') ?>/css/courtly.css?v=<?= e(config('courtly.app.version', '1.0.0')) ?>">
    <style>
        .rankings-wrap { width: 100%; padding: 24px 20px 64px; }
        .rankings-head { display: flex; align-items: center; gap: 14px; margin-bottom: 22px; }
        .rankings-head h1 { font-size: 1.4rem; margin: 0; }
    </style>
</head>
<body>
<div class="rankings-wrap">
    <?php $active = 'rankings'; include resource_path('views/partials/app-header.php'); ?>

    <?php include resource_path('views/partials/rankings-content.php'); ?>
</div>
</body>
</html>
