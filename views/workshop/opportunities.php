<?php
/** @var array<int, array<string, mixed>> $requests */
/** @var array<string, mixed> $filters */
/** @var array<int, array<string, mixed>> $myLots */
$eligible = array_values(array_filter($myLots, static fn(array $lot): bool => (int) $lot['coherence_score'] >= 70));
?>
<div class="page-head">
  <p class="eyebrow">Taller · oportunidades</p>
  <h1>Lo que el mercado está pidiendo</h1>
  <p class="lede">Cada petición es una necesidad concreta de un local verificado. Sólo puedes proponer lotes propios que superen el umbral de coherencia.</p>
  <div class="actions">
    <span class="chip"><b><?= count($eligible) ?></b> lotes elegibles</span>
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/workshop/proposals')) ?>">Tus propuestas</a>
  </div>
</div>

<form class="card" method="get" action="<?= e(url('/workshop/opportunities')) ?>" role="search">
  <div class="form-row-3">
    <div class="field">
      <label for="q">Buscar</label>
      <input type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="título, local…">
    </div>
    <div class="field">
      <label for="territory">Territorio</label>
      <input type="text" id="territory" name="territory" value="<?= e($filters['territory']) ?>">
    </div>
    <div class="field" style="align-self:end">
      <button class="btn btn-primary btn-block" type="submit">Filtrar</button>
    </div>
  </div>
</form>

<?php if ($requests === []): ?>
  <?= partial('empty', ['title' => 'No hay peticiones abiertas', 'message' => 'Cuando un local verificado publique una necesidad, aparecerá aquí.']) ?>
<?php else: ?>
  <div class="grid grid-2">
    <?php foreach ($requests as $requestRow): ?>
      <?= partial('request-card', ['request' => $requestRow, 'href' => '/workshop/opportunities/' . $requestRow['id']]) ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
