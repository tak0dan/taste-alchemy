<?php
/** @var array<string, array<string, mixed>> $recipePresets */
/** @var array<string, array<string, mixed>> $extractions */
/** @var array<string, array<string, mixed>> $syrups */
?>
<div class="page-head">
  <p class="eyebrow">Taller · nueva receta</p>
  <h1>Publica una receta ejecutable</h1>
  <p class="lede">La primera versión se calcula al publicar. Si después cambias algo, no se sobrescribe: se abre una versión nueva.</p>
</div>

<form method="post" action="<?= e(url('/workshop/recipes')) ?>" class="grid grid-side">
  <?= csrf_field() ?>
  <div>
    <div class="card">
      <h2>Identidad</h2>
      <div class="form-row">
        <div class="field">
          <label for="title">Título</label>
          <input type="text" id="title" name="title" required minlength="4" value="<?= e(old('title')) ?>" placeholder="Cereza negra en barrica de roble">
        </div>
        <div class="field">
          <label for="style">Estilo</label>
          <input type="text" id="style" name="style" list="style-list" value="<?= e(old('style', 'Licor de fruta')) ?>">
          <datalist id="style-list">
            <option value="Licor de fruta"></option>
            <option value="Licor de hierbas"></option>
            <option value="Licor de flor"></option>
            <option value="Aperitivo"></option>
            <option value="Digestivo"></option>
            <option value="Crema de licor"></option>
          </datalist>
        </div>
      </div>
      <div class="field">
        <label for="description">Descripción</label>
        <textarea id="description" name="description" rows="4" placeholder="Qué buscabas, qué fruta, qué temperatura de servicio…"><?= e(old('description')) ?></textarea>
      </div>
      <div class="form-row">
        <div class="field">
          <label for="tags">Etiquetas (separadas por comas)</label>
          <input type="text" id="tags" name="tags" value="<?= e(old('tags')) ?>" placeholder="cereza, roble, invierno">
        </div>
        <div class="field">
          <label for="visibility">Visibilidad</label>
          <select id="visibility" name="visibility">
            <option value="publica">Pública</option>
            <option value="no_listada">No listada</option>
            <option value="privada">Privada</option>
          </select>
        </div>
      </div>
    </div>

    <div class="card">
      <h2>Base y extracción</h2>
      <p class="eyebrow">Paso 1 · elige la fruta</p>
      <div class="choices">
        <?php foreach ($recipePresets as $code => $preset): ?>
          <label class="choice <?= $code === 'cereza' ? 'selected' : '' ?>">
            <input type="radio" name="base" value="<?= e($code) ?>" <?= $code === 'cereza' ? 'checked' : '' ?>>
            <span class="name"><?= e($preset['emoji'] . ' ' . $preset['label']) ?></span>
            <span class="sub"><?= e($preset['fruit_name']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="form-row mt-2">
        <div class="field">
          <label for="extraction">Método de extracción</label>
          <select id="extraction" name="extraction">
            <?php foreach ($extractions as $code => $extraction): ?>
              <option value="<?= e($code) ?>" <?= $code === 'limoncello' ? 'selected' : '' ?>>
                <?= e($extraction['label']) ?> · <?= (int) $extraction['days'] ?> días · pérdida <?= num($extraction['loss_pct'], 0) ?> %
              </option>
            <?php endforeach; ?>
          </select>
          <span class="hint">La pérdida de extracción se aplica al alcohol útil.</span>
        </div>
        <div class="field">
          <label for="syrup">Almíbar</label>
          <select id="syrup" name="syrup">
            <?php foreach ($syrups as $code => $syrup): ?>
              <option value="<?= e($code) ?>" <?= $code === 'homemade' ? 'selected' : '' ?>>
                <?= e($syrup['label']) ?> · <?= num($syrup['brix'], 0) ?> °Bx
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row-3">
        <div class="field">
          <label for="base_abv_pct">Graduación del alcohol base (%)</label>
          <input type="number" step="0.1" min="0" max="96" id="base_abv_pct" name="base_abv_pct" value="<?= e(old('base_abv_pct', '40')) ?>">
        </div>
        <div class="field">
          <label for="rest_days">Días de reposo</label>
          <input type="number" step="1" min="0" max="365" id="rest_days" name="rest_days" value="<?= e(old('rest_days', '14')) ?>">
        </div>
        <div class="field">
          <label for="mode">Modo de cálculo</label>
          <select id="mode" name="mode">
            <option value="ingredientes">Fijar ingredientes</option>
            <option value="volumen">Fijar volumen final</option>
          </select>
        </div>
      </div>
    </div>

    <div class="card">
      <h2>Cantidades</h2>
      <div class="form-row-3">
        <div class="field">
          <label for="vodka_ml">Alcohol (ml)</label>
          <input type="number" step="1" min="0" id="vodka_ml" name="vodka_ml" value="<?= e(old('vodka_ml', '500')) ?>">
        </div>
        <div class="field">
          <label for="ingredient_g">Fruta (g)</label>
          <input type="number" step="1" min="0" id="ingredient_g" name="ingredient_g" value="<?= e(old('ingredient_g', '500')) ?>">
        </div>
        <div class="field">
          <label for="desired_volume_ml">Volumen deseado (ml)</label>
          <input type="number" step="10" min="1" id="desired_volume_ml" name="desired_volume_ml" value="<?= e(old('desired_volume_ml', '1000')) ?>">
          <span class="hint">Sólo se usa en modo «fijar volumen».</span>
        </div>
      </div>
      <div class="form-row-3">
        <div class="field">
          <label for="sugar_ratio">Azúcar por g de fruta</label>
          <input type="number" step="0.01" min="0" max="2" id="sugar_ratio" name="sugar_ratio" value="<?= e(old('sugar_ratio', '0.40')) ?>">
        </div>
        <div class="field">
          <label for="water_ratio">Agua por g de fruta</label>
          <input type="number" step="0.01" min="0" max="3" id="water_ratio" name="water_ratio" value="<?= e(old('water_ratio', '0.50')) ?>">
        </div>
        <div class="field">
          <label for="extraction_loss_pct">Pérdida de extracción (%)</label>
          <input type="number" step="0.1" min="0" max="50" id="extraction_loss_pct" name="extraction_loss_pct" placeholder="según método">
        </div>
      </div>
      <label class="checkline">
        <input type="checkbox" name="blend_enabled" value="1">
        <span>Mezclar con frutos rojos (sólo si la base lo permite)</span>
      </label>
      <div class="field mt-1">
        <label for="blend_pct">Porcentaje de frutos rojos</label>
        <input type="number" step="1" min="0" max="90" id="blend_pct" name="blend_pct" value="15">
      </div>
    </div>
  </div>

  <aside>
    <div class="card builder-aside">
      <h3>Antes de publicar</h3>
      <ul class="list">
        <li><span class="title">La versión 1 es inmutable</span><div class="sub">Podrás añadir versiones, nunca reescribir la primera.</div></li>
        <li><span class="title">El motor calcula al guardar</span><div class="sub">ABV, Brix, volumen, congelación y cronograma.</div></li>
        <li><span class="title">Sin promesas</span><div class="sub">La receta no presume de calidad; sólo de ser reproducible.</div></li>
      </ul>
      <div class="notice">
        <b>Consejo.</b> Si dudas, publica primero en <em>no listada</em>. Podrás hacerla pública después.
      </div>
      <button class="btn btn-gold btn-block" type="submit">Publicar receta</button>
      <a class="btn btn-ghost btn-block mt-1" href="<?= e(url('/workshop')) ?>">Cancelar</a>
    </div>
  </aside>
</form>
