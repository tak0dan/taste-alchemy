<?php
/** @var array<string, mixed> $request */
$href = $href ?? ('/requests/' . $request['id']);
?>
<article class="card request-card">
  <div class="flex between">
    <span class="style"><?= e($request['style']) ?></span>
    <?php if (!empty($request['exclusivity_requested'])): ?>
      <span class="badge">Exclusividad</span>
    <?php endif; ?>
  </div>
  <h3><a href="<?= e(url($href)) ?>"><?= e($request['title']) ?></a></h3>
  <div class="meta-line">
    <span><b><?= e($request['venue_name']) ?></b></span>
    <span><?= e($request['territory']) ?></span>
    <span>Objetivo <?= e(format_date($request['target_date'])) ?></span>
  </div>
  <p class="dim small"><?= e(mb_strimwidth((string) ($request['description'] ?? ''), 0, 130, '…')) ?></p>
  <div class="spacer"></div>
  <div class="meta-line">
    <span><b><?= (int) $request['units_wanted'] ?></b> unidades</span>
    <?php if ($request['abv_min'] !== null || $request['abv_max'] !== null): ?>
      <span>ABV <?= $request['abv_min'] !== null ? num($request['abv_min'], 0) : '—' ?>–<?= $request['abv_max'] !== null ? num($request['abv_max'], 0) : '—' ?> %</span>
    <?php endif; ?>
    <span><b><?= (int) ($request['pending_proposals'] ?? 0) ?></b> propuestas pendientes</span>
  </div>
  <div class="bottom">
    <span class="muted small"><?= (int) ($request['total_proposals'] ?? 0) ?> propuestas en total</span>
    <a class="btn btn-sm btn-ghost" href="<?= e(url($href)) ?>">Ver petición</a>
  </div>
</article>
