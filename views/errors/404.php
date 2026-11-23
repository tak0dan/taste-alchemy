<?php
/** @var int $status */
/** @var string $message */
?>
<div class="page-head center" style="padding-top:40px">
  <p class="eyebrow">Error <?= (int) $status ?></p>
  <h1><?= (int) $status === 404 ? 'Esta página no está en la barrica' : 'Algo se ha torcido' ?></h1>
  <p class="lede" style="margin:0 auto"><?= e($message) ?></p>
  <div class="rule"></div>
  <div class="actions" style="justify-content:center">
    <a class="btn btn-gold" href="<?= e(url('/')) ?>">Volver al inicio</a>
    <a class="btn btn-ghost" href="<?= e(url('/recipes')) ?>">Ver recetas</a>
  </div>
</div>
