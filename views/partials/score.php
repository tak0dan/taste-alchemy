<?php
$score = (int) ($score ?? 0);
$label = $label ?? 'coherencia';
$size = ($size ?? '') === 'lg' ? 'lg' : '';
?>
<span class="score">
  <span class="score-ring <?= e(verdict_class($score)) ?> <?= e($size) ?>" style="--pct: <?= max(0, min(100, $score)) ?>" aria-hidden="true">
    <span><?= $score ?></span>
  </span>
  <span class="score-text"><b><?= e(verdict_word($score)) ?></b><?= e($label) ?></span>
</span>
