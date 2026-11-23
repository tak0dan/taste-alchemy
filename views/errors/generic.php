<?php
/** @var int $status */
/** @var string $message */
?>
<div class="page-head center" style="padding-top:40px">
  <p class="eyebrow">Error <?= (int) $status ?></p>
  <h1>No se pudo completar la operación</h1>
  <p class="lede" style="margin:0 auto"><?= e($message) ?></p>
  <div class="rule"></div>
  <div class="actions" style="justify-content:center">
    <a class="btn btn-gold" href="<?= e(url('/')) ?>">Volver al inicio</a>
  </div>
</div>
