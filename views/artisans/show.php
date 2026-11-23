<?php
/** @var array<string, mixed> $portfolio */
$profile = $portfolio['profile'];
$scorecard = $portfolio['scorecard'];
$recipes = $portfolio['recipes'];
$lots = $portfolio['lots'];
?>
<div class="page-head">
  <p class="eyebrow">Taller · <?= e($profile['territory']) ?></p>
  <h1><?= e($profile['studio_name']) ?></h1>
  <div class="meta-line">
    <span><?= e($profile['display_name']) ?></span>
    <?php if (!empty($profile['founded_year'])): ?><span>Desde <?= (int) $profile['founded_year'] ?></span><?php endif; ?>
    <?php if (!empty($profile['website'])): ?><span><a href="<?= e($profile['website']) ?>" rel="noopener noreferrer nofollow">Sitio web</a></span><?php endif; ?>
  </div>
  <?php if (!empty($profile['bio'])): ?><p class="lede"><?= e($profile['bio']) ?></p><?php endif; ?>
</div>

<div class="stats">
  <div class="stat">
    <div class="k"><?= (int) ($scorecard['public_recipes'] ?? count($recipes)) ?></div>
    <div class="l">Recetas públicas</div>
  </div>
  <div class="stat">
    <div class="k"><?= (int) ($scorecard['lot_count'] ?? count($lots)) ?></div>
    <div class="l">Lotes registrados</div>
  </div>
  <div class="stat">
    <div class="k"><?= num($scorecard['avg_coherence'] ?? 0, 1) ?></div>
    <div class="l">Coherencia media</div>
  </div>
  <div class="stat">
    <div class="k"><?= (int) ($scorecard['units_sold'] ?? 0) ?></div>
    <div class="l">Unidades servidas</div>
  </div>
</div>

<section>
  <div class="card-head">
    <div><p class="eyebrow">Repositorio</p><h2>Recetas del taller</h2></div>
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/recipes?q=' . urlencode((string) $profile['studio_name']))) ?>">Buscar en el catálogo →</a>
  </div>
  <?php if ($recipes === []): ?>
    <?= partial('empty', ['title' => 'Sin recetas públicas', 'message' => 'Este taller aún no ha publicado ninguna receta.']) ?>
  <?php else: ?>
    <div class="grid grid-2">
      <?php foreach ($recipes as $recipe): ?>
        <?= partial('recipe-card', ['recipe' => $recipe]) ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="mt-3">
  <div class="card-head">
    <div><p class="eyebrow">Mercado</p><h2>Lotes del taller</h2></div>
  </div>
  <?php if ($lots === []): ?>
    <?= partial('empty', ['title' => 'Sin lotes públicos', 'message' => 'Los lotes aparecen cuando el taller registra su primera lectura.']) ?>
  <?php else: ?>
    <div class="grid grid-2">
      <?php foreach ($lots as $lot): ?>
        <?= partial('lot-card', ['lot' => $lot]) ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
