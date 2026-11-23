<?php
/** @var array<string, mixed> $detail */
/** @var array<string, mixed>|null $listing */
/** @var bool $canPublish */
/** @var float $minScore */
$lot = $detail['lot'];
$readings = $detail['readings'];
$verdict = $detail['verdict'];
$breakdown = $detail['breakdown'];
$checks = $breakdown['checks'] ?? [];
$history = $detail['verdict_history'];
$statusFlow = [
    'en_maceracion' => ['en_reposo' => 'Pasar a reposo', 'descartado' => 'Descartar', 'bloqueado' => 'Bloquear'],
    'en_reposo' => ['embotellado' => 'Embotellar', 'descartado' => 'Descartar', 'bloqueado' => 'Bloquear'],
    'embotellado' => ['conservado' => 'Conservar', 'descartado' => 'Descartar'],
    'conservado' => ['descartado' => 'Descartar'],
];
$available = (int) $lot['units_total'] - (int) $lot['units_reserved'] - (int) $lot['units_sold'];
?>
<div class="page-head">
  <p class="eyebrow">Taller · lote <?= e($lot['code']) ?> · <?= e(status_label((string) $lot['status'])) ?></p>
  <h1><?= e($lot['recipe_title']) ?></h1>
  <div class="meta-line">
    <span>v<?= (int) $lot['version_no'] ?></span>
    <span><?= e($lot['territory']) ?></span>
    <span>Iniciado <?= e(format_date($lot['started_at'])) ?></span>
    <?php if (!empty($lot['barrel_ref'])): ?><span>Barrica <?= e($lot['barrel_ref']) ?></span><?php endif; ?>
    <span><?= (int) $lot['reading_count'] ?> lecturas</span>
  </div>
  <div class="actions">
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/lots/' . $lot['id'])) ?>">Ver ficha pública</a>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/workshop')) ?>">Volver al taller</a>
  </div>
</div>

<div class="grid grid-side">
  <div>
    <div class="card">
      <div class="card-head">
        <h2>Coherencia actual</h2>
        <?= partial('score', ['score' => (int) $lot['coherence_score'], 'size' => 'lg']) ?>
      </div>
      <div class="phys">
        <div class="p"><div class="k">Volumen inicial</div><div class="v"><?= num($lot['initial_volume_l'], 1) ?> L</div></div>
        <div class="p"><div class="k">Volumen actual</div><div class="v"><?= num($lot['current_volume_l'], 1) ?> L</div></div>
        <div class="p"><div class="k">ABV inicial</div><div class="v"><?= num($lot['initial_abv'], 1) ?> %</div></div>
        <div class="p"><div class="k">ABV declarado</div><div class="v"><?= num($lot['declared_abv'], 1) ?> %</div></div>
      </div>
      <?php if ($checks !== []): ?>
        <div class="breakdown mt-2">
          <?php foreach ($checks as $check): ?>
            <div class="item">
              <span class="name"><?= e($check['label']) ?></span>
              <span class="val"><?= (int) $check['score'] ?>/100 · peso <?= num($check['weight'] * 100, 0) ?> %</span>
              <span class="meter"><i class="<?= e($check['status']) ?>" style="width:<?= max(0, min(100, (int) $check['score'])) ?>%"></i></span>
              <span class="why"><?= e($check['explanation']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if (!$canPublish): ?>
        <div class="notice mt-2">
          <b>Umbral no alcanzado.</b> Este lote tiene <?= (int) $lot['coherence_score'] ?>/100 y necesita al menos <?= num($minScore, 0) ?> para anunciarse o proponerse. Registra lecturas periódicas y ajusta la declaración a la última medición.
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-head">
        <h2>Registrar lectura</h2>
        <span class="muted small">La cadencia importa: el silencio también resta.</span>
      </div>
      <form method="post" action="<?= e(url('/workshop/lots/' . $lot['id'] . '/readings')) ?>">
        <?= csrf_field() ?>
        <div class="form-row-3">
          <div class="field">
            <label for="volume_l">Volumen (L)</label>
            <input type="number" step="0.01" min="0.01" id="volume_l" name="volume_l" required value="<?= e(num($lot['current_volume_l'], 2)) ?>">
          </div>
          <div class="field">
            <label for="abv">Graduación (%)</label>
            <input type="number" step="0.01" min="0.1" max="90" id="abv" name="abv" required value="<?= e(num($lot['declared_abv'], 2)) ?>">
          </div>
          <div class="field">
            <label for="taken_at">Fecha</label>
            <input type="date" id="taken_at" name="taken_at" required value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
          </div>
        </div>
        <div class="form-row">
          <div class="field">
            <label for="temperature_c">Temperatura (°C)</label>
            <input type="number" step="0.1" id="temperature_c" name="temperature_c" placeholder="18">
          </div>
          <div class="field" style="align-self:end">
            <button class="btn btn-primary btn-block" type="submit">Registrar y recalcular</button>
          </div>
        </div>
      </form>
      <div class="divider"></div>
      <?php if ($readings === []): ?>
        <p class="muted small">Sin lecturas registradas.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Fecha</th><th class="num">Volumen</th><th class="num">ABV</th><th class="num">Temperatura</th></tr></thead>
            <tbody>
              <?php foreach (array_reverse($readings) as $reading): ?>
                <tr>
                  <td><?= e(format_date($reading['taken_at'])) ?></td>
                  <td class="num"><?= num($reading['volume_l'], 2) ?> L</td>
                  <td class="num"><?= num($reading['abv'], 2) ?> %</td>
                  <td class="num"><?= $reading['temperature_c'] !== null ? num($reading['temperature_c'], 1) . ' °C' : '—' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-head">
        <h2>Anuncio en el mercado</h2>
        <span class="muted small"><?= $canPublish ? 'Elegible' : 'Bloqueado por coherencia' ?></span>
      </div>
      <?php if ($listing !== null): ?>
        <div class="flex between mb-2">
          <div>
            <div class="title"><?= e($listing['title']) ?></div>
            <div class="muted small"><?= (int) $listing['units_available'] ?> unidades · <?= money($listing['price_per_unit']) ?></div>
          </div>
          <span class="badge <?= e(status_class((string) $listing['status'])) ?>"><?= e(status_label((string) $listing['status'])) ?></span>
        </div>
      <?php endif; ?>
      <form method="post" action="<?= e(url('/workshop/lots/' . $lot['id'] . '/listing')) ?>">
        <?= csrf_field() ?>
        <div class="field">
          <label for="title">Título del anuncio</label>
          <input type="text" id="title" name="title" required value="<?= e($listing['title'] ?? ($lot['recipe_title'] . ' · ' . $lot['code'])) ?>">
        </div>
        <div class="field">
          <label for="description">Descripción</label>
          <textarea id="description" name="description" rows="3"><?= e($listing['description'] ?? '') ?></textarea>
        </div>
        <div class="form-row">
          <div class="field">
            <label for="units_available">Unidades a anunciar</label>
            <input type="number" step="1" min="1" max="<?= $available ?>" id="units_available" name="units_available" required value="<?= (int) ($listing['units_available'] ?? $available) ?>">
            <span class="hint"><?= $available ?> unidades libres de <?= (int) $lot['units_total'] ?>.</span>
          </div>
          <div class="field">
            <label for="price_per_unit">Precio por unidad (€)</label>
            <input type="number" step="0.01" min="0" id="price_per_unit" name="price_per_unit" required value="<?= e($listing['price_per_unit'] ?? $lot['price_per_unit'] ?? '') ?>">
          </div>
        </div>
        <button class="btn btn-gold" type="submit" <?= $canPublish ? '' : 'disabled' ?>>
          <?= $listing !== null ? 'Actualizar anuncio' : 'Publicar anuncio' ?>
        </button>
      </form>
    </div>
  </div>

  <aside>
    <div class="card">
      <h3>Inventario</h3>
      <dl class="kv">
        <dt>Unidades totales</dt><dd><?= (int) $lot['units_total'] ?></dd>
        <dt>Reservadas</dt><dd><?= (int) $lot['units_reserved'] ?></dd>
        <dt>Vendidas</dt><dd><?= (int) $lot['units_sold'] ?></dd>
        <dt>Libres</dt><dd><?= $available ?></dd>
        <dt>Formato</dt><dd><?= (int) $lot['unit_ml'] ?> ml</dd>
        <dt>Precio base</dt><dd><?= money($lot['price_per_unit']) ?></dd>
      </dl>
    </div>

    <div class="card">
      <h3>Cambiar estado</h3>
      <?php if (isset($statusFlow[$lot['status']])): ?>
        <form method="post" action="<?= e(url('/workshop/lots/' . $lot['id'] . '/status')) ?>">
          <?= csrf_field() ?>
          <div class="field">
            <label for="status">Nuevo estado</label>
            <select id="status" name="status">
              <?php foreach ($statusFlow[$lot['status']] as $value => $label): ?>
                <option value="<?= e($value) ?>"><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button class="btn btn-sm btn-block" type="submit" data-confirm="¿Cambiar el estado del lote? La transición queda auditada.">Aplicar transición</button>
        </form>
      <?php else: ?>
        <p class="muted small">El lote está en un estado final; no admite más transiciones.</p>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3>Historial de coherencia</h3>
      <?php if ($history === []): ?>
        <p class="muted small">Sin cálculos previos.</p>
      <?php else: ?>
        <ul class="list">
          <?php foreach ($history as $entry): ?>
            <li>
              <div class="flex between">
                <span class="title"><?= (int) $entry['score'] ?>/100</span>
                <span class="badge <?= e(verdict_class((int) $entry['score'])) ?>"><?= e(verdict_word((int) $entry['score'])) ?></span>
              </div>
              <div class="sub"><?= e(format_date($entry['computed_at'], 'd/m/Y H:i')) ?></div>
              <div class="sub">E <?= (int) $entry['evaporation_score'] ?> · A <?= (int) $entry['abv_score'] ?> · C <?= (int) $entry['cadence_score'] ?> · D <?= (int) $entry['declaration_score'] ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </aside>
</div>
