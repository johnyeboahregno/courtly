<?php
/**
 * PWA <head> snippet: manifest link, theme-color, iOS meta tags and
 * service-worker registration.
 *
 * No offline caching by design — the service worker is a network pass-through.
 * Requires $base (app base path) in scope; falls back to the request base path.
 */
$base = $base ?? rtrim(request()->getBasePath(), '/');
$version = config('courtly.app.version', '1.0.0');
?>
<link rel="manifest" href="<?= e($base) ?>/manifest.webmanifest?v=<?= e($version) ?>">
<meta name="theme-color" content="#0b0e2a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Courtly">
<link rel="apple-touch-icon" href="<?= e($base) ?>/assets/icons/pwa/apple-touch-icon.png?v=<?= e($version) ?>">
<script>
(function () {
    if (!('serviceWorker' in navigator)) return;
    if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') return;
    window.addEventListener('load', function () {
        navigator.serviceWorker.register(<?= json_encode($base) ?> + '/sw.js').catch(function () {});
    });
})();
</script>
