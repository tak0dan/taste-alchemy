<?php
/** @var array<string, mixed> $lot */
$score = (int) ($lot['coherence_score'] ?? 0);
?>
<article class="card lot-card">
  <div class="flex between">
    <span class="style"><?= e($lot['code']) ?></span>
    <span class="badge <?= e(status_class((string) $lot['status'])) ?>"><?= e(status_label((string) $lot['status'])) ?></span>
  </div>
  <h3><a href="<?= e(url('/lots/' . $lot['id'])) ?>"><?= e($lot['recipe_title']) ?></a></h3>
  <div class="meta-line">
    <span><b><?= e($lot['studio_name'] ?: $lot['artisan_name']) ?></b></span>
    <span><?= e($lot['territory']) ?></span>
  </div>
  <div class="flex between mt-1">
    <?= partial('score', ['score' => $score]) ?>
    <div class="text-right">
      <div class="gold" style="font-family:var(--serif);font-size:1.5rem;line-height:1"><?= (int) $lot['units_available'] ?></div>
      <div class="muted small">unidades libres</div>
    </div>
  </div>
  <div class="spacer"></div>
  <div class="meta-line">
    <span><b><?= num($lot['initial_abv'], 1) ?> %</b> vol</span>
    <span><b><?= (int) $lot['unit_ml'] ?></b> ml/unidad</span>
    <span><b><?= (int) $lot['reading_count'] ?></b> lecturas</span>
    <?php if ($lot['review_avg'] !== null): ?><span><b><?= num($lot['review_avg'], 1) ?>/10</b> cata</span><?php endif; ?>
  </div>
  <?php if (!empty($lot['is_last_of_barrel'])): ?>
    <div class="notice small" style="margin:8px 0 0">Último lote de barrica. Cuando se agota, no vuelve.</div>
  <?php endif; ?>
  <div class="bottom">
    <span class="muted small">Iniciado <?= e(format_date($lot['started_at'], 'm/Y')) ?></span>
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/lots/' . $lot['id'])) ?>">Ver evidencia</a>
  </div>
</article>
