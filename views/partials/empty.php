<?php
$emptyTitle = $title ?? 'Nada por aquí todavía';
$emptyMessage = $message ?? 'Cuando haya contenido aparecerá en este espacio.';
?>
<div class="empty">
  <?= sigil(40) ?>
  <h3><?= e($emptyTitle) ?></h3>
  <p><?= e($emptyMessage) ?></p>
  <?php if (!empty($actionUrl) && !empty($actionLabel)): ?>
    <a class="btn btn-sm" href="<?= e($actionUrl) ?>"><?= e($actionLabel) ?></a>
  <?php endif; ?>
</div>
