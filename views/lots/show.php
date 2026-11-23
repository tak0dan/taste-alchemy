<?php
/** @var array<string, mixed> $detail */
$lot = $detail['lot'];
$readings = $detail['readings'];
$verdict = $detail['verdict'];
$breakdown = $detail['breakdown'];
$checks = $breakdown['checks'] ?? [];
$physics = $breakdown['physics'] ?? [];
$history = $detail['verdict_history'];
$listing = $detail['listing'];
$reviews = $detail['reviews'];
$artisan = $detail['artisan'];
$user = auth_user();
$isOwner = $user !== null && (int) $user['id'] === (int) $lot['artisan_id'];
?>
<div class="page-head">
  <div class="lot-hero">
    <?= partial('score', ['score' => (int) $lot['coherence_score'], 'size' => 'lg']) ?>
    <div class="ident">
      <p class="sub"><?= e($lot['code']) ?> · <?= e(status_label((string) $lot['status'])) ?></p>
      <h1><?= e($lot['recipe_title']) ?></h1>
      <div class="meta-line">
        <span>por <a href="<?= e(url('/artisans/' . ($artisan['slug'] ?? ''))) ?>"><b><?= e($lot['studio_name'] ?: $lot['artisan_name']) ?></b></a></span>
        <span><?= e($lot['territory']) ?></span>
        <span>Iniciado <?= e(format_date($lot['started_at'])) ?></span>
        <?php if (!empty($lot['barrel_ref'])): ?><span>Barrica <?= e($lot['barrel_ref']) ?></span><?php endif; ?>
      </div>
    </div>
    <div class="right text-right">
      <div class="gold" style="font-family:var(--serif);font-size:2rem;line-height:1"><?= (int) $lot['units_available'] ?></div>
      <div class="muted small">unidades libres de <?= (int) $lot['units_total'] ?></div>
      <?php if ($lot['price_per_unit'] !== null): ?>
        <div class="mt-1"><?= money($lot['price_per_unit']) ?> <span class="muted small">/ unidad de <?= (int) $lot['unit_ml'] ?> ml</span></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="grid grid-side">
  <div>
    <div class="card">
      <div class="card-head">
        <h2>Física declarada</h2>
        <span class="muted small">Último cálculo <?= e(format_date($verdict['computed_at'] ?? null, 'd/m/Y H:i')) ?></span>
      </div>
      <div class="phys">
        <div class="p"><div class="k">Volumen inicial</div><div class="v"><?= num($lot['initial_volume_l'], 1) ?> L</div></div>
        <div class="p"><div class="k">Volumen actual</div><div class="v"><?= num($lot['current_volume_l'], 1) ?> L</div></div>
        <div class="p"><div class="k">ABV inicial</div><div class="v"><?= num($lot['initial_abv'], 1) ?> %</div></div>
        <div class="p"><div class="k">ABV declarado</div><div class="v"><?= num($lot['declared_abv'], 1) ?> %</div></div>
      </div>
      <?php if ($physics !== []): ?>
        <div class="meta-line mt-2">
          <span>Edad juzgada: <b><?= num($physics['years'] ?? 0, 2) ?> años</b></span>
          <span>Lecturas: <b><?= (int) ($physics['reading_count'] ?? 0) ?></b></span>
          <span>Hueco máximo: <b><?= (int) ($physics['max_gap_days'] ?? 0) ?> días</b></span>
          <span>Volumen esperado: <b><?= num($physics['expected_volume_l'] ?? 0, 1) ?> L</b></span>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-head">
        <h2>Por qué esta coherencia</h2>
        <?= partial('score', ['score' => (int) $lot['coherence_score']]) ?>
      </div>
      <?php if ($checks === []): ?>
        <div class="notice">Este lote aún no tiene veredicto calculado.</div>
      <?php else: ?>
        <div class="breakdown">
          <?php foreach ($checks as $check): ?>
            <div class="item">
              <span class="name"><?= e($check['label']) ?></span>
              <span class="val"><?= (int) $check['score'] ?>/100 · peso <?= num($check['weight'] * 100, 0) ?> %</span>
              <span class="meter"><i class="<?= e($check['status']) ?>" style="width:<?= max(0, min(100, (int) $check['score'])) ?>%"></i></span>
              <span class="why"><?= e($check['explanation']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="notice mt-2"><?= e($breakdown['explanation'] ?? '') ?></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-head">
        <h2>Lecturas registradas</h2>
        <span class="muted small"><?= count($readings) ?> lecturas fechadas</span>
      </div>
      <?php if ($readings === []): ?>
        <div class="empty"><p>Sin lecturas: no hay evidencia que verificar.</p></div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Fecha</th><th class="num">Volumen</th><th class="num">ABV</th><th class="num">Temperatura</th><th>Registrada por</th></tr></thead>
            <tbody>
              <?php foreach ($readings as $reading): ?>
                <tr>
                  <td><?= e(format_date($reading['taken_at'])) ?></td>
                  <td class="num"><?= num($reading['volume_l'], 2) ?> L</td>
                  <td class="num"><?= num($reading['abv'], 2) ?> %</td>
                  <td class="num"><?= $reading['temperature_c'] !== null ? num($reading['temperature_c'], 1) . ' °C' : '—' ?></td>
                  <td><?= e($reading['recorded_by_name'] ?: '—') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-head">
        <h2>Valoraciones</h2>
        <?php if ($lot['review_avg'] !== null): ?>
          <span class="badge ok"><?= num($lot['review_avg'], 1) ?>/10 · <?= (int) $lot['venue_review_count'] ?> locales</span>
        <?php endif; ?>
      </div>
      <?php if ($reviews === []): ?>
        <p class="muted">Sin valoraciones todavía. Sólo se puntúa después de una comisión real y 72 horas de reflexión.</p>
      <?php else: ?>
        <?php foreach ($reviews as $review): ?>
          <div class="review">
            <div class="who">
              <span class="avatar" aria-hidden="true"><?= e(initials((string) ($review['venue_name'] ?: $review['author_name']))) ?></span>
              <div>
                <b><?= e($review['venue_name'] ?: $review['author_name']) ?></b>
                <span><?= $review['kind'] === 'comisario' ? 'Comisario' : 'Local verificado' ?> · <?= e(format_date($review['published_at'] ?: $review['created_at'])) ?></span>
              </div>
              <?php if ($review['score_total'] !== null): ?>
                <span class="badge ok right"><?= num($review['score_total'], 1) ?>/10</span>
              <?php endif; ?>
            </div>
            <blockquote>«<?= e($review['body']) ?>»</blockquote>
            <div class="scores">
              <?php foreach (['aroma' => 'Aroma', 'texture' => 'Textura', 'balance' => 'Equilibrio', 'finish' => 'Final'] as $key => $label): ?>
                <?php if ($review['score_' . $key] !== null): ?>
                  <span class="s"><?= $label ?> <?= (int) $review['score_' . $key] ?>/10</span>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <aside>
    <div class="card">
      <h3>Estado del mercado</h3>
      <dl class="kv">
        <dt>Unidades totales</dt><dd><?= (int) $lot['units_total'] ?></dd>
        <dt>Reservadas</dt><dd><?= (int) $lot['units_reserved'] ?></dd>
        <dt>Vendidas</dt><dd><?= (int) $lot['units_sold'] ?></dd>
        <dt>Libres</dt><dd><?= (int) $lot['units_available'] ?></dd>
        <dt>Formato</dt><dd><?= (int) $lot['unit_ml'] ?> ml</dd>
      </dl>
      <?php if ($listing !== null): ?>
        <div class="divider"></div>
        <p class="eyebrow">Anuncio</p>
        <p class="small"><?= e($listing['title']) ?></p>
        <span class="badge <?= e(status_class((string) $listing['status'])) ?>"><?= e(status_label((string) $listing['status'])) ?></span>
      <?php endif; ?>
      <div class="divider"></div>
      <a class="btn btn-primary btn-block" href="<?= e(url('/marketplace')) ?>">Buscar una petición compatible</a>
      <?php if ($isOwner): ?>
        <a class="btn btn-ghost btn-block mt-1" href="<?= e(url('/workshop/lots/' . $lot['id'])) ?>">Gestionar este lote</a>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3>Ficha del taller</h3>
      <?php if ($artisan !== null): ?>
        <p class="gold" style="font-family:var(--serif);font-size:1.3rem;margin-bottom:4px"><?= e($artisan['studio_name']) ?></p>
        <p class="muted small"><?= e($artisan['territory']) ?><?= !empty($artisan['founded_year']) ? ' · desde ' . (int) $artisan['founded_year'] : '' ?></p>
        <?php if (!empty($artisan['bio'])): ?><p class="dim small"><?= e(mb_strimwidth((string) $artisan['bio'], 0, 220, '…')) ?></p><?php endif; ?>
        <a class="btn btn-sm btn-ghost btn-block" href="<?= e(url('/artisans/' . $artisan['slug'])) ?>">Ver taller</a>
      <?php else: ?>
        <p class="muted">Sin perfil público.</p>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3>Historial del veredicto</h3>
      <?php if ($history === []): ?>
        <p class="muted small">Aún no hay cálculos previos.</p>
      <?php else: ?>
        <ul class="list">
          <?php foreach ($history as $entry): ?>
            <li>
              <div class="flex between">
                <span class="title"><?= (int) $entry['score'] ?>/100</span>
                <span class="badge <?= e(verdict_class((int) $entry['score'])) ?>"><?= e(verdict_word((int) $entry['score'])) ?></span>
              </div>
              <div class="sub"><?= e(format_date($entry['computed_at'], 'd/m/Y H:i')) ?> · E <?= (int) $entry['evaporation_score'] ?> · A <?= (int) $entry['abv_score'] ?> · C <?= (int) $entry['cadence_score'] ?> · D <?= (int) $entry['declaration_score'] ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <?php if ($user !== null): ?>
      <div class="card">
        <h3>Denunciar</h3>
        <details class="disclosure">
          <summary>Reportar este lote</summary>
          <form method="post" action="<?= e(url('/report')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="entity_type" value="lot">
            <input type="hidden" name="entity_id" value="<?= (int) $lot['id'] ?>">
            <input type="hidden" name="return_to" value="/lots/<?= (int) $lot['id'] ?>">
            <div class="field">
              <label for="reason">Motivo</label>
              <input type="text" id="reason" name="reason" required>
            </div>
            <div class="field">
              <label for="details">Detalle</label>
              <textarea id="details" name="details" rows="3"></textarea>
            </div>
            <button class="btn btn-sm btn-danger btn-block" type="submit">Enviar denuncia</button>
          </form>
        </details>
      </div>
    <?php endif; ?>
  </aside>
</div>
