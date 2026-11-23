<?php
/** @var string $content */
$user = auth_user();
$appName = (string) config('APP_NAME', 'Taste Alchemy');
$pageTitle = isset($title) ? $title . ' · ' . $appName : $appName;
$description = $description ?? 'El taller y el mercado de los licores de élite: recetas versionadas, lotes con evidencia física y comisiones entre locales y artesanos.';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta name="theme-color" content="#0a0705">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 48 48'%3E%3Ccircle cx='24' cy='24' r='21' fill='%230a0705' stroke='%23c9a15a'/%3E%3Ccircle cx='24' cy='24' r='7' fill='none' stroke='%237a1128' stroke-width='2'/%3E%3C/svg%3E">
</head>
<body>
<div class="shell">
  <header class="site-header">
    <div class="inner">
      <a class="brand" href="<?= e(url('/')) ?>" aria-label="Taste Alchemy, inicio">
        <?= sigil(34) ?>
        <span>Taste Alchemy<small>taller &amp; mercado</small></span>
      </a>

      <button class="nav-toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="site-nav">
        Menú
      </button>

      <nav id="site-nav" class="site-nav" aria-label="Navegación principal">
        <a class="navlink <?= nav_active('/recipes') ?>" href="<?= e(url('/recipes')) ?>">Recetas</a>
        <a class="navlink <?= nav_active('/lots') ?>" href="<?= e(url('/lots')) ?>">Lotes</a>
        <a class="navlink <?= nav_active('/marketplace') ?>" href="<?= e(url('/marketplace')) ?>">Mercado</a>
        <a class="navlink <?= nav_active('/artisans') ?>" href="<?= e(url('/artisans')) ?>">Talleres</a>
        <a class="navlink <?= nav_active('/calculator') ?>" href="<?= e(url('/calculator')) ?>">Calculadora</a>
        <a class="navlink <?= nav_active('/manifesto') ?>" href="<?= e(url('/manifesto')) ?>">Manifiesto</a>

        <?php if ($user === null): ?>
          <a class="navlink <?= nav_active('/login') ?>" href="<?= e(url('/login')) ?>">Entrar</a>
          <a class="btn btn-sm btn-primary" href="<?= e(url('/register')) ?>">Abrir cuenta</a>
        <?php else: ?>
          <?php
          $home = \App\Service\AuthService::homeFor($user);
          $roles = $user['roles'] ?? [];
          ?>
          <a class="navlink" href="<?= e(url($home)) ?>">Panel</a>
          <a class="navlink <?= nav_active('/account') ?>" href="<?= e(url('/account')) ?>">Cuenta</a>
          <div class="nav-user">
            <span class="avatar" aria-hidden="true"><?= e(initials((string) $user['display_name'])) ?></span>
            <span class="who">
              <b><?= e($user['display_name']) ?></b>
              <?= e(implode(' · ', $roles)) ?>
            </span>
          </div>
          <form class="inline-form" method="post" action="<?= e(url('/logout')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-ghost" type="submit">Salir</button>
          </form>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <main class="page">
    <?= partial('flashes') ?>
    <?= $content ?>
  </main>

  <footer class="site-footer">
    <div class="inner">
      <div>
        <a class="brand" href="<?= e(url('/')) ?>"><?= sigil(26) ?> <span>Taste Alchemy</span></a>
        <p>Un repositorio público de recetas ejecutables y un mercado donde cada lote enseña su historial físico. Sin pasarela de pago, sin transportistas, sin app nativa: sólo el oficio y su evidencia.</p>
      </div>
      <nav class="footer-links" aria-label="Enlaces del pie">
        <a href="<?= e(url('/recipes')) ?>">Recetas</a>
        <a href="<?= e(url('/lots')) ?>">Lotes</a>
        <a href="<?= e(url('/marketplace')) ?>">Mercado</a>
        <a href="<?= e(url('/artisans')) ?>">Talleres</a>
        <a href="<?= e(url('/api/v1/openapi.json')) ?>">API</a>
        <a href="<?= e(url('/manifesto')) ?>">Manifiesto</a>
      </nav>
    </div>
  </footer>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
