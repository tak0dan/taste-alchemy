<?php
/** @var array<string, mixed> $result */
/** @var array<string, mixed> $filters */
$items = $result['items'];
?>
<div class="page-head">
  <p class="eyebrow">Repositorio público · versionado y forkable</p>
  <h1>Recetas que se pueden ejecutar</h1>
  <p class="lede">Cada receta conserva su historial de versiones y su linaje de bifurcaciones. Abrir una receta es abrir una forma de trabajar, no una lista de ingredientes.</p>
</div>

<form class="card" method="get" action="<?= e(url('/recipes')) ?>" role="search">
  <div class="form-row-3">
    <div class="field">
      <label for="q">Buscar</label>
      <input type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="título, estilo, taller…">
    </div>
    <div class="field">
      <label for="style">Estilo</label>
      <select id="style" name="style">
        <option value="">Todos</option>
        <?php foreach ($result['styles'] as $style): ?>
          <option value="<?= e($style) ?>" <?= $filters['style'] === $style ? 'selected' : '' ?>><?= e($style) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="territory">Territorio</label>
      <input type="text" id="territory" name="territory" value="<?= e($filters['territory']) ?>" placeholder="Comunitat Valenciana">
    </div>
  </div>
  <div class="form-row-3">
    <div class="field">
      <label for="abv_min">ABV mínimo</label>
      <input type="number" step="0.1" min="0" max="90" id="abv_min" name="abv_min" value="<?= e($filters['abv_min']) ?>">
    </div>
    <div class="field">
      <label for="abv_max">ABV máximo</label>
      <input type="number" step="0.1" min="0" max="90" id="abv_max" name="abv_max" value="<?= e($filters['abv_max']) ?>">
    </div>
    <div class="field">
      <label for="sort">Orden</label>
      <select id="sort" name="sort">
        <option value="recent" <?= $filters['sort'] === 'recent' ? 'selected' : '' ?>>Más recientes</option>
        <option value="coherence" <?= $filters['sort'] === 'coherence' ? 'selected' : '' ?>>Mayor coherencia</option>
        <option value="forks" <?= $filters['sort'] === 'forks' ? 'selected' : '' ?>>Más bifurcadas</option>
        <option value="abv" <?= $filters['sort'] === 'abv' ? 'selected' : '' ?>>Mayor graduación</option>
      </select>
    </div>
  </div>
  <div class="actions">
    <button class="btn btn-primary" type="submit">Filtrar</button>
    <a class="btn btn-ghost" href="<?= e(url('/recipes')) ?>">Limpiar</a>
    <span class="muted small right"><?= (int) $result['total'] ?> recetas públicas</span>
  </div>
</form>

<?php if ($items === []): ?>
  <?= partial('empty', [
      'title' => 'Ninguna receta coincide',
      'message' => 'Prueba con otro estilo, otro territorio o menos filtros.',
      'actionUrl' => url('/recipes'),
      'actionLabel' => 'Ver todo el catálogo',
  ]) ?>
<?php else: ?>
  <div class="grid grid-3 mt-2">
    <?php foreach ($items as $recipe): ?>
      <?= partial('recipe-card', ['recipe' => $recipe]) ?>
    <?php endforeach; ?>
  </div>
  <?= partial('pagination', ['page' => $result['page'], 'pages' => $result['pages']]) ?>
<?php endif; ?>
