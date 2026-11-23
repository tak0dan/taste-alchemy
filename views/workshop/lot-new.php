<?php
/** @var array<int, array<string, mixed>> $recipes */
?>
<div class="page-head">
  <p class="eyebrow">Taller · nuevo lote</p>
  <h1>Una barrica con su primera lectura</h1>
  <p class="lede">Un lote no existe hasta que tiene volumen, graduación y una fecha. La primera lectura es obligatoria; la coherencia se calcula al guardar.</p>
</div>

<?php if ($recipes === []): ?>
  <?= partial('empty', [
      'title' => 'Necesitas una receta primero',
      'message' => 'Un lote se vincula a una versión concreta de una receta tuya. Publica una receta antes de registrar la barrica.',
      'actionUrl' => url('/workshop/recipes/new'),
      'actionLabel' => 'Publicar una receta',
  ]) ?>
<?php else: ?>
  <form class="grid grid-side" method="post" action="<?= e(url('/workshop/lots')) ?>">
    <?= csrf_field() ?>
    <div>
      <div class="card">
        <h2>Origen</h2>
        <div class="field">
          <label for="recipe_version_id">Versión de receta</label>
          <select id="recipe_version_id" name="recipe_version_id" required>
            <?php foreach ($recipes as $recipe): ?>
              <option value="<?= (int) $recipe['version_id'] ?>">
                <?= e($recipe['title']) ?> · v<?= (int) $recipe['version_no'] ?> · <?= num($recipe['abv'], 1) ?> % · <?= e($recipe['style']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="hint">Sólo aparecen las recetas de tu taller, en su versión vigente.</span>
        </div>
        <div class="form-row">
          <div class="field">
            <label for="code">Código de lote</label>
            <input type="text" id="code" name="code" value="<?= e(old('code')) ?>" placeholder="LOT-2026-0001">
            <span class="hint">Si lo dejas vacío, se genera uno.</span>
          </div>
          <div class="field">
            <label for="barrel_ref">Referencia de barrica</label>
            <input type="text" id="barrel_ref" name="barrel_ref" value="<?= e(old('barrel_ref')) ?>" placeholder="Roble francés 225 L · lote 7">
          </div>
        </div>
        <div class="form-row">
          <div class="field">
            <label for="territory">Territorio</label>
            <input type="text" id="territory" name="territory" required value="<?= e(old('territory', 'Comunitat Valenciana')) ?>">
          </div>
          <div class="field">
            <label for="status">Estado inicial</label>
            <select id="status" name="status">
              <option value="en_maceracion">En maceración</option>
              <option value="en_reposo">En reposo</option>
              <option value="embotellado">Embotellado</option>
              <option value="conservado">Conservado</option>
            </select>
          </div>
        </div>
      </div>

      <div class="card">
        <h2>Física y formato</h2>
        <div class="form-row-3">
          <div class="field">
            <label for="initial_volume_l">Volumen inicial (L)</label>
            <input type="number" step="0.1" min="0.1" id="initial_volume_l" name="initial_volume_l" required value="<?= e(old('initial_volume_l', '10')) ?>">
          </div>
          <div class="field">
            <label for="initial_abv">Graduación inicial (%)</label>
            <input type="number" step="0.1" min="0.1" max="90" id="initial_abv" name="initial_abv" required value="<?= e(old('initial_abv', '24')) ?>">
          </div>
          <div class="field">
            <label for="started_at">Fecha de inicio</label>
            <input type="date" id="started_at" name="started_at" required value="<?= e(old('started_at', date('Y-m-d'))) ?>" max="<?= date('Y-m-d') ?>">
          </div>
        </div>
        <div class="form-row-3">
          <div class="field">
            <label for="units_total">Unidades totales</label>
            <input type="number" step="1" min="1" id="units_total" name="units_total" required value="<?= e(old('units_total', '30')) ?>">
          </div>
          <div class="field">
            <label for="unit_ml">Mililitros por unidad</label>
            <input type="number" step="10" min="50" id="unit_ml" name="unit_ml" value="<?= e(old('unit_ml', '700')) ?>">
          </div>
          <div class="field">
            <label for="price_per_unit">Precio por unidad (€)</label>
            <input type="number" step="0.01" min="0" id="price_per_unit" name="price_per_unit" value="<?= e(old('price_per_unit', '18.00')) ?>">
          </div>
        </div>
        <div class="field">
          <label for="temperature_c">Temperatura de la lectura (°C, opcional)</label>
          <input type="number" step="0.1" id="temperature_c" name="temperature_c" value="<?= e(old('temperature_c', '18')) ?>">
        </div>
        <label class="checkline">
          <input type="checkbox" name="is_last_of_barrel" value="1">
          <span>Es el último lote de esta barrica</span>
        </label>
        <div class="field mt-2">
          <label for="notes">Notas de elaboración</label>
          <textarea id="notes" name="notes" rows="3" placeholder="Fruta de temporada, reposo previsto, incidencias…"><?= e(old('notes')) ?></textarea>
        </div>
      </div>
    </div>

    <aside>
      <div class="card">
        <h3>Qué va a pasar</h3>
        <ul class="list">
          <li><span class="title">Se registra la primera lectura</span><div class="sub">Con la fecha de inicio, el volumen y la graduación.</div></li>
          <li><span class="title">Se calcula la coherencia</span><div class="sub">Evaporación, trayectoria, cadencia y declaración.</div></li>
          <li><span class="title">El lote nace sin anuncio</span><div class="sub">Sólo se anuncia si supera el umbral de confianza.</div></li>
        </ul>
        <div class="notice">
          <b>Un lote no es un producto.</b> Es una barrica concreta. Cuando se agota, no vuelve.
        </div>
        <button class="btn btn-gold btn-block" type="submit">Registrar lote y lectura</button>
        <a class="btn btn-ghost btn-block mt-1" href="<?= e(url('/workshop')) ?>">Cancelar</a>
      </div>
    </aside>
  </form>
<?php endif; ?>
