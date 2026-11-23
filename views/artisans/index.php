<?php
/** @var array<int, array<string, mixed>> $artisans */
?>
<div class="page-head">
  <p class="eyebrow">Talleres · oficio con historial</p>
  <h1>Quién está detrás de cada lote</h1>
  <p class="lede">Un taller no se presenta con un logo: se presenta con sus recetas públicas, sus lotes y la coherencia media de su evidencia.</p>
</div>

<?php if ($artisans === []): ?>
  <?= partial('empty', ['title' => 'Aún no hay talleres', 'message' => 'El primer artesano en registrarse abrirá esta página.']) ?>
<?php else: ?>
  <div class="grid grid-2">
    <?php foreach ($artisans as $artisan): ?>
      <article class="card">
        <div class="flex between">
          <span class="style"><?= e($artisan['territory']) ?></span>
          <?php if ((float) ($artisan['avg_coherence'] ?? 0) > 0): ?>
            <?= partial('score', ['score' => (int) round((float) $artisan['avg_coherence'])]) ?>
          <?php else: ?>
            <span class="badge neutral">Sin lotes aún</span>
          <?php endif; ?>
        </div>
        <h2 class="mt-1"><a href="<?= e(url('/artisans/' . $artisan['slug'])) ?>"><?= e($artisan['studio_name']) ?></a></h2>
        <div class="muted small"><?= e($artisan['display_name']) ?><?= !empty($artisan['founded_year']) ? ' · desde ' . (int) $artisan['founded_year'] : '' ?></div>
        <div class="meta-line mt-2">
          <span><b><?= (int) $artisan['public_recipes'] ?></b> recetas</span>
          <span><b><?= (int) $artisan['lot_count'] ?></b> lotes</span>
          <span><b><?= (int) $artisan['completed_commissions'] ?></b> comisiones</span>
          <span><b><?= (int) $artisan['units_sold'] ?></b> unidades servidas</span>
        </div>
        <div class="bottom">
          <span class="muted small"><?= $artisan['avg_review'] !== null ? 'Cata media ' . num($artisan['avg_review'], 1) . '/10' : 'Sin catas todavía' ?></span>
          <a class="btn btn-sm btn-ghost" href="<?= e(url('/artisans/' . $artisan['slug'])) ?>">Ver taller</a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
