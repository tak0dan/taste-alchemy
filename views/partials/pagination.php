<?php
/** @var int $page */
/** @var int $pages */
if (($pages ?? 1) <= 1) {
    return;
}
$current = max(1, (int) $page);
$total = max(1, (int) $pages);
$start = max(1, $current - 2);
$end = min($total, $current + 2);
?>
<nav class="pagination" aria-label="Paginación">
  <?php if ($current > 1): ?>
    <a href="<?= e(query_string(['page' => $current - 1])) ?>" rel="prev">← Anterior</a>
  <?php endif; ?>
  <?php if ($start > 1): ?>
    <a href="<?= e(query_string(['page' => 1])) ?>">1</a>
    <?php if ($start > 2): ?><span aria-hidden="true">…</span><?php endif; ?>
  <?php endif; ?>
  <?php for ($i = $start; $i <= $end; $i++): ?>
    <?php if ($i === $current): ?>
      <span class="current" aria-current="page"><?= $i ?></span>
    <?php else: ?>
      <a href="<?= e(query_string(['page' => $i])) ?>"><?= $i ?></a>
    <?php endif; ?>
  <?php endfor; ?>
  <?php if ($end < $total): ?>
    <?php if ($end < $total - 1): ?><span aria-hidden="true">…</span><?php endif; ?>
    <a href="<?= e(query_string(['page' => $total])) ?>"><?= $total ?></a>
  <?php endif; ?>
  <?php if ($current < $total): ?>
    <a href="<?= e(query_string(['page' => $current + 1])) ?>" rel="next">Siguiente →</a>
  <?php endif; ?>
</nav>
