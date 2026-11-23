<?php
/** @var array<string, mixed> $result */
/** @var array<string, mixed> $filters */
$items = $result['items'];
?>
<div class="page-head">
  <p class="eyebrow">Mercado · evidencia física</p>
  <h1>Lotes que enseñan su historial</h1>
  <p class="lede">Un lote no es una promesa de cosecha: es una barrica con volumen, graduación y lecturas fechadas. Aquí se ve antes de pedir.</p>
</div>

<form class="card" method="get" action="<?= e(url('/lots')) ?>" role="search">
  <div class="form-row-3">
    <div class="field">
      <label for="q">Buscar</label>
      <input type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="código, receta, taller…">
    </div>
    <div class="field">
      <label for="territory">Territorio</label>
      <input type="text" id="territory" name="territory" value="<?= e($filters['territory']) ?>">
    </div>
    <div class="field">
      <label for="min_coherence">Coherencia mínima</label>
      <select id="min_coherence" name="min_coherence">
        <option value="0">Cualquiera</option>
        <option value="45" <?= (int) $filters['min_coherence'] === 45 ? 'selected' : '' ?>>45 o más</option>
        <option value="70" <?= (int) $filters['min_coherence'] === 70 ? 'selected' : '' ?>>70 o más (elegible)</option>
        <option value="85" <?= (int) $filters['min_coherence'] === 85 ? 'selected' : '' ?>>85 o más (sólido)</option>
      </select>
    </div>
  </div>
  <div class="form-row-3">
    <div class="field">
      <label for="status">Estado</label>
      <select id="status" name="status">
        <option value="">Todos</option>
        <option value="en_maceracion" <?= $filters['status'] === 'en_maceracion' ? 'selected' : '' ?>>En maceración</option>
        <option value="en_reposo" <?= $filters['status'] === 'en_reposo' ? 'selected' : '' ?>>En reposo</option>
        <option value="embotellado" <?= $filters['status'] === 'embotellado' ? 'selected' : '' ?>>Embotellado</option>
        <option value="conservado" <?= $filters['status'] === 'conservado' ? 'selected' : '' ?>>Conservado</option>
      </select>
    </div>
    <div class="field">
      <label for="sort">Orden</label>
      <select id="sort" name="sort">
        <option value="coherence" <?= $filters['sort'] === 'coherence' ? 'selected' : '' ?>>Mayor coherencia</option>
        <option value="recent" <?= $filters['sort'] === 'recent' ? 'selected' : '' ?>>Más recientes</option>
        <option value="available" <?= $filters['sort'] === 'available' ? 'selected' : '' ?>>Más unidades libres</option>
        <option value="age" <?= $filters['sort'] === 'age' ? 'selected' : '' ?>>Más antiguos</option>
      </select>
    </div>
    <div class="field" style="align-self:end">
      <button class="btn btn-primary btn-block" type="submit">Filtrar</button>
    </div>
  </div>
  <div class="actions">
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/lots')) ?>">Limpiar</a>
    <span class="muted small right"><?= (int) $result['total'] ?> lotes públicos</span>
  </div>
</form>

<?php if ($items === []): ?>
  <?= partial('empty', [
      'title' => 'Ningún lote coincide',
      'message' => 'Prueba con menos filtros o consulta el mercado de peticiones abiertas.',
      'actionUrl' => url('/marketplace'),
      'actionLabel' => 'Ir al mercado',
  ]) ?>
<?php else: ?>
  <div class="grid grid-3 mt-2">
    <?php foreach ($items as $lot): ?>
      <?= partial('lot-card', ['lot' => $lot]) ?>
    <?php endforeach; ?>
  </div>
  <?= partial('pagination', ['page' => $result['page'], 'pages' => $result['pages']]) ?>
<?php endif; ?>
