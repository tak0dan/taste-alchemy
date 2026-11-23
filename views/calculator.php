<?php
/** @var array<string, array<string, mixed>> $presets */
/** @var array<string, array<string, mixed>> $extractions */
/** @var array<string, array<string, mixed>> $syrups */
/** @var array<string, mixed> $initial */
$input = $initial['input'] ?? [];
$computed = $initial;
$selectedBase = (string) ($input['base'] ?? 'cereza');
$mode = (string) ($input['mode'] ?? 'ingredientes');
$selectedExtraction = (string) ($input['extraction'] ?? 'limoncello');
$selectedSyrup = (string) ($input['syrup'] ?? 'homemade');
?>
<div class="page-head">
  <p class="eyebrow">Constructor · el mismo motor que valida la plataforma</p>
  <h1>Construye, calcula, entiende</h1>
  <p class="lede">Seis decisiones. El motor devuelve graduación, dulzor, volumen, punto de congelación, frascos y cronograma. Nada se guarda hasta que tú lo publiques.</p>
</div>

<div class="grid grid-side">
  <form id="builder-form" method="post" action="<?= e(url('/calculator')) ?>">
    <?= csrf_field() ?>

    <section class="builder-step">
      <div class="flex between">
        <h3><span class="step-no">1</span> La base</h3>
        <button class="btn btn-sm btn-ghost" type="button" data-accordion aria-expanded="true" aria-controls="step-1">Ocultar paso</button>
      </div>
      <p class="muted small">La fruta manda. El resto son proporciones.</p>
      <div id="step-1">
        <div class="choices">
          <?php foreach ($presets as $code => $preset): ?>
            <label class="choice <?= $code === $selectedBase ? 'selected' : '' ?>">
              <input type="radio" name="base" value="<?= e($code) ?>" <?= $code === $selectedBase ? 'checked' : '' ?>>
              <span class="name"><?= e($preset['emoji'] . ' ' . $preset['label']) ?></span>
              <span class="sub"><?= e($preset['fruit_name']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="builder-step">
      <div class="flex between">
        <h3><span class="step-no">2</span> Qué fijas</h3>
        <button class="btn btn-sm btn-ghost" type="button" data-accordion aria-expanded="true" aria-controls="step-2">Ocultar paso</button>
      </div>
      <p class="muted small">Puedes partir de los ingredientes o retroceder desde el volumen final.</p>
      <div id="step-2">
        <div class="radio-pills" role="group" aria-label="Modo de cálculo">
          <label class="radio-pill" aria-pressed="<?= $mode === 'ingredientes' ? 'true' : 'false' ?>">
            <input type="radio" name="mode" value="ingredientes" <?= $mode === 'ingredientes' ? 'checked' : '' ?> hidden>
            Fijar ingredientes
          </label>
          <label class="radio-pill" aria-pressed="<?= $mode === 'volumen' ? 'true' : 'false' ?>">
            <input type="radio" name="mode" value="volumen" <?= $mode === 'volumen' ? 'checked' : '' ?> hidden>
            Fijar volumen final
          </label>
        </div>

        <div data-mode-panel="ingredientes" <?= $mode !== 'ingredientes' ? 'hidden' : '' ?>>
          <div class="form-row">
            <div class="field">
              <label for="vodka_ml">Alcohol base (ml)</label>
              <input type="number" step="10" min="0" max="100000" id="vodka_ml" name="vodka_ml" value="<?= e($input['vodka_ml'] ?? 500) ?>">
            </div>
            <div class="field">
              <label for="ingredient_g">Fruta (g)</label>
              <input type="number" step="10" min="0" max="100000" id="ingredient_g" name="ingredient_g" value="<?= e($input['ingredient_g'] ?? 500) ?>">
            </div>
          </div>
        </div>

        <div data-mode-panel="volumen" <?= $mode !== 'volumen' ? 'hidden' : '' ?>>
          <div class="field">
            <label for="desired_volume_ml">Volumen final deseado (ml)</label>
            <input type="number" step="50" min="1" max="1000000" id="desired_volume_ml" name="desired_volume_ml" value="<?= e($input['desired_volume_ml'] ?? 1000) ?>">
            <span class="hint">El motor retrocede a la fruta y al alcohol necesarios sin romper el equilibrio.</span>
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label for="base_abv_pct">Graduación del alcohol (%)</label>
            <input type="number" step="0.5" min="0" max="96" id="base_abv_pct" name="base_abv_pct" value="<?= e($input['base_abv_pct'] ?? 40) ?>">
          </div>
          <div class="field">
            <label for="rest_days">Reposo (días)</label>
            <input type="number" step="1" min="0" max="365" id="rest_days" name="rest_days" value="<?= e($input['rest_days'] ?? 14) ?>">
          </div>
        </div>
      </div>
    </section>

    <section class="builder-step">
      <div class="flex between">
        <h3><span class="step-no">3</span> La extracción</h3>
        <button class="btn btn-sm btn-ghost" type="button" data-accordion aria-expanded="true" aria-controls="step-3">Ocultar paso</button>
      </div>
      <p class="muted small">Tiempo, calor y pérdida. La pérdida se descuenta del alcohol útil.</p>
      <div id="step-3">
        <div class="field">
          <label for="extraction">Método</label>
          <select id="extraction" name="extraction">
            <?php foreach ($extractions as $code => $extraction): ?>
              <option value="<?= e($code) ?>" <?= $code === $selectedExtraction ? 'selected' : '' ?>>
                <?= e($extraction['label']) ?> · <?= (int) $extraction['days'] ?> días · pérdida <?= num($extraction['loss_pct'], 0) ?> %
              </option>
            <?php endforeach; ?>
          </select>
          <span class="hint"><?= e($extractions[$selectedExtraction]['summary'] ?? '') ?></span>
        </div>
        <div class="field">
          <label for="extraction_loss_pct">Pérdida de extracción (%)</label>
          <input type="number" step="0.5" min="0" max="50" id="extraction_loss_pct" name="extraction_loss_pct" value="<?= e($input['extraction_loss_pct'] ?? $extractions[$selectedExtraction]['loss_pct'] ?? 0) ?>">
        </div>
      </div>
    </section>

    <section class="builder-step">
      <div class="flex between">
        <h3><span class="step-no">4</span> Dulzor y cuerpo</h3>
        <button class="btn btn-sm btn-ghost" type="button" data-accordion aria-expanded="true" aria-controls="step-4">Ocultar paso</button>
      </div>
      <p class="muted small">Azúcar y agua por gramo de fruta. El almíbar define el Brix de servicio.</p>
      <div id="step-4">
        <div class="form-row">
          <div class="field">
            <label for="sugar_ratio">Azúcar (g por g de fruta)</label>
            <input type="number" step="0.01" min="0" max="2" id="sugar_ratio" name="sugar_ratio" value="<?= e($input['sugar_ratio'] ?? 0.40) ?>">
          </div>
          <div class="field">
            <label for="water_ratio">Agua (ml por g de fruta)</label>
            <input type="number" step="0.01" min="0" max="3" id="water_ratio" name="water_ratio" value="<?= e($input['water_ratio'] ?? 0.50) ?>">
          </div>
        </div>
        <div class="field">
          <label for="syrup">Almíbar</label>
          <select id="syrup" name="syrup">
            <?php foreach ($syrups as $code => $syrup): ?>
              <option value="<?= e($code) ?>" <?= $code === $selectedSyrup ? 'selected' : '' ?>><?= e($syrup['label']) ?> · <?= num($syrup['brix'], 0) ?> °Bx</option>
            <?php endforeach; ?>
          </select>
        </div>
        <label class="checkline">
          <input type="checkbox" name="blend_enabled" value="1" <?= !empty($input['blend_enabled']) ? 'checked' : '' ?>>
          <span>Mezclar con frutos rojos (sólo bases que lo permiten)</span>
        </label>
        <div class="field mt-1" data-blend-pct <?= empty($input['blend_enabled']) ? 'hidden' : '' ?>>
          <label for="blend_pct">Porcentaje de frutos rojos</label>
          <input type="number" step="1" min="0" max="90" id="blend_pct" name="blend_pct" value="<?= e($input['blend_pct'] ?? 15) ?>">
        </div>
      </div>
    </section>

    <section class="builder-step">
      <div class="flex between">
        <h3><span class="step-no">5</span> El matiz</h3>
        <button class="btn btn-sm btn-ghost" type="button" data-accordion aria-expanded="true" aria-controls="step-5">Ocultar paso</button>
      </div>
      <p class="muted small">Un acento, no un disfraz. La fruta debe seguir hablando.</p>
      <div id="step-5">
        <div class="form-row">
          <div class="field">
            <label for="flavor">Acento</label>
            <select id="flavor" name="flavor">
              <option value="original" <?= ($input['flavor'] ?? 'original') === 'original' ? 'selected' : '' ?>>Original</option>
              <option value="citrus" <?= ($input['flavor'] ?? '') === 'citrus' ? 'selected' : '' ?>>Cítrico</option>
              <option value="spice" <?= ($input['flavor'] ?? '') === 'spice' ? 'selected' : '' ?>>Especiado</option>
              <option value="herbal" <?= ($input['flavor'] ?? '') === 'herbal' ? 'selected' : '' ?>>Herbal</option>
            </select>
          </div>
          <div class="field">
            <label for="custom_spice">Especia o hierba propia</label>
            <input type="text" id="custom_spice" name="custom_spice" value="<?= e($input['custom_spice'] ?? '') ?>" placeholder="canela, romero, piel de naranja…">
          </div>
        </div>
        <label class="checkline">
          <input type="checkbox" name="split" value="1" <?= !empty($input['split']) ? 'checked' : '' ?>>
          <span>Dividir en dos frascos para comparar dos tiempos de maceración</span>
        </label>
        <details class="disclosure">
          <summary>Ajustes avanzados del modelo</summary>
          <div class="form-row">
            <div class="field">
              <label for="juice_yield">Rendimiento de jugo (ml por g)</label>
              <input type="number" step="0.01" min="0" max="1" id="juice_yield" name="juice_yield" value="<?= e($input['juice_yield'] ?? 0.18) ?>">
            </div>
            <div class="field">
              <label for="sugar_volume_factor">Volumen aparente del azúcar (ml por g)</label>
              <input type="number" step="0.01" min="0" max="1" id="sugar_volume_factor" name="sugar_volume_factor" value="<?= e($input['sugar_volume_factor'] ?? 0.15) ?>">
            </div>
          </div>
        </details>
      </div>
    </section>

    <section class="builder-step">
      <div class="flex between">
        <h3><span class="step-no">6</span> Calcular</h3>
        <button class="btn btn-sm btn-ghost" type="button" data-accordion aria-expanded="true" aria-controls="step-6">Ocultar paso</button>
      </div>
      <div id="step-6">
        <p class="muted small">El panel de la derecha se actualiza al cambiar cualquier valor. También puedes recalcular en el servidor.</p>
        <div class="actions">
          <button class="btn btn-gold" type="submit">Recalcular en el servidor</button>
          <a class="btn btn-ghost" href="<?= e(url('/recipes')) ?>">Ver recetas publicadas</a>
          <a class="btn btn-ghost" href="<?= e(url('/api/v1/calculator/presets')) ?>">Presets en JSON</a>
        </div>
      </div>
    </section>
  </form>

  <aside>
    <div class="card builder-aside">
      <div id="builder-result" aria-live="polite" aria-busy="false">
        <div class="result-hero">
          <div class="caption">Graduación estimada</div>
          <div class="abv"><?= num($computed['abv_pct'], 1) ?><small> % vol</small></div>
          <div class="muted small"><?= e($computed['preset']['label']) ?> · <?= e($computed['extraction']['label']) ?></div>
        </div>
        <dl class="kv">
          <dt>ABV</dt><dd><?= num($computed['abv_pct'], 1) ?> %</dd>
          <dt>Brix</dt><dd><?= num($computed['brix'], 1) ?> °Bx</dd>
          <dt>Azúcar</dt><dd><?= num($computed['sugar_gl'], 0) ?> g/L</dd>
          <dt>Volumen final</dt><dd><?= num($computed['volumes']['final_ml'], 0) ?> ml</dd>
          <dt>Punto de congelación</dt><dd><?= num($computed['freeze']['freezing_point_c'], 1) ?> °C</dd>
          <dt>Servicio</dt><dd><?= num($computed['freeze']['serving_temperature_c'], 0) ?> °C</dd>
          <dt>Botellas de 700 ml</dt><dd><?= num($computed['bottles_700_ml'], 2) ?></dd>
          <dt>Dulzor</dt><dd><?= e($computed['sweetness']) ?></dd>
        </dl>
        <div class="divider"></div>
        <div class="small gold">Frascos de fruta</div>
        <div class="jars">
          <?php foreach ($computed['jars']['fruit'] as $jar): ?>
            <span class="jar" title="<?= e(num($jar['total_g'], 0)) ?> g"><i style="height:<?= max(4, min(100, (float) $jar['fill_pct'])) ?>%"></i><span><?= num($jar['fill_pct'], 0) ?>%</span></span>
          <?php endforeach; ?>
        </div>
        <div class="small gold mt-2">Frascos de alcohol</div>
        <div class="jars">
          <?php foreach ($computed['jars']['vodka'] as $jar): ?>
            <span class="jar" title="<?= e(num($jar['ml'], 0)) ?> ml"><i style="height:<?= max(4, min(100, (float) $jar['fill_pct'])) ?>%"></i><span><?= num($jar['fill_pct'], 0) ?>%</span></span>
          <?php endforeach; ?>
        </div>
        <?php if ($computed['warnings'] !== []): ?>
          <div class="notice mt-2">
            <b>Atención.</b>
            <ul style="margin:8px 0 0;padding-left:18px">
              <?php foreach ($computed['warnings'] as $warning): ?><li><?= e($warning) ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
        <div class="divider"></div>
        <div class="small gold">Cronograma</div>
        <div class="timeline">
          <?php foreach ($computed['timeline']['items'] as $item): ?>
            <div class="event">
              <div class="when"><?= e($item['label']) ?> · día <?= (int) $item['at_day'] ?></div>
              <div class="what"><?= e($item['title']) ?></div>
              <div class="why"><?= e($item['body']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="card">
      <h3>Lo que el motor no decide</h3>
      <p class="muted small">La calidad del fruto, la mano del artesano y el tiempo real de la barrica. El cálculo sólo evita errores de proporción y avisa cuando el resultado no es estable.</p>
      <dl class="kv">
        <dt>Azúcar / fruta</dt><dd><?= num($computed['input']['sugar_ratio'], 2) ?></dd>
        <dt>Agua / fruta</dt><dd><?= num($computed['input']['water_ratio'], 2) ?></dd>
        <dt>Pérdida</dt><dd><?= num($computed['input']['extraction_loss_pct'], 1) ?> %</dd>
        <dt>Equivalencia</dt><dd><?= isset($computed['equivalents']) ? 'Δ ' . num($computed['equivalents']['delta_abv'], 2) . ' ABV' : '—' ?></dd>
      </dl>
    </div>
  </aside>
</div>
