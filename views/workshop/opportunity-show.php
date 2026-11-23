<?php
/** @var array<string, mixed> $detail */
/** @var array<int, array<string, mixed>> $myLots */
/** @var float $minScore */
$requestRow = $detail['request'];
$proposals = $detail['proposals'];
$eligible = array_values(array_filter($myLots, static fn(array $lot): bool =>
    (int) $lot['coherence_score'] >= $minScore && !in_array($lot['status'], ['en_maceracion', 'descartado', 'bloqueado'], true)
));
$user = auth_user();
?>
<div class="page-head">
  <p class="eyebrow"><?= e($requestRow['style']) ?> · <?= e($requestRow['territory']) ?></p>
  <h1><?= e($requestRow['title']) ?></h1>
  <div class="meta-line">
    <span>por <b><?= e($requestRow['venue_name']) ?></b></span>
    <span>Objetivo <?= e(format_date($requestRow['target_date'])) ?></span>
    <span><?= (int) $requestRow['units_wanted'] ?> unidades</span>
    <?php if (!empty($requestRow['exclusivity_requested'])): ?><span class="badge">Pide exclusividad</span><?php endif; ?>
  </div>
</div>

<div class="grid grid-side">
  <div>
    <article class="card">
      <h2>La necesidad</h2>
      <p class="dim"><?= nl2br(e((string) ($requestRow['description'] ?? 'Sin descripción adicional.'))) ?></p>
      <div class="phys">
        <div class="p"><div class="k">Unidades</div><div class="v"><?= (int) $requestRow['units_wanted'] ?></div></div>
        <div class="p"><div class="k">ABV mínimo</div><div class="v"><?= $requestRow['abv_min'] !== null ? num($requestRow['abv_min'], 1) . ' %' : '—' ?></div></div>
        <div class="p"><div class="k">ABV máximo</div><div class="v"><?= $requestRow['abv_max'] !== null ? num($requestRow['abv_max'], 1) . ' %' : '—' ?></div></div>
        <div class="p"><div class="k">Licencia</div><div class="v"><?= e(status_label((string) $requestRow['license_status'])) ?></div></div>
      </div>
    </article>

    <article class="card">
      <div class="card-head">
        <h2>Presentar una propuesta</h2>
        <span class="muted small">Sólo lotes propios con coherencia ≥ <?= num($minScore, 0) ?></span>
      </div>
      <?php if ($eligible === []): ?>
        <div class="notice">
          <b>No tienes lotes elegibles.</b> Necesitas un lote propio con coherencia de al menos <?= num($minScore, 0) ?>/100 y estado servible. Revisa tus lotes o registra lecturas nuevas.
        </div>
        <a class="btn btn-sm btn-ghost" href="<?= e(url('/workshop')) ?>">Ir a tus lotes</a>
      <?php else: ?>
        <form method="post" action="<?= e(url('/workshop/opportunities/' . $requestRow['id'] . '/proposals')) ?>">
          <?= csrf_field() ?>
          <div class="field">
            <label for="lot_id">Lote</label>
            <select id="lot_id" name="lot_id" required>
              <?php foreach ($eligible as $lot): ?>
                <option value="<?= (int) $lot['id'] ?>">
                  <?= e($lot['code']) ?> · <?= e($lot['recipe_title']) ?> · coherencia <?= (int) $lot['coherence_score'] ?> · <?= (int) $lot['units_available'] ?> libres
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-row-3">
            <div class="field">
              <label for="units">Unidades ofrecidas</label>
              <input type="number" step="1" min="1" id="units" name="units" required value="<?= (int) $requestRow['units_wanted'] ?>">
            </div>
            <div class="field">
              <label for="price_per_unit">Precio por unidad (€)</label>
              <input type="number" step="0.01" min="0.01" id="price_per_unit" name="price_per_unit" required>
            </div>
            <div class="field">
              <label for="mode">Modo</label>
              <select id="mode" name="mode">
                <option value="ambas">Venta o consignación</option>
                <option value="venta">Venta</option>
                <option value="consignacion">Consignación</option>
              </select>
            </div>
          </div>
          <label class="checkline">
            <input type="checkbox" name="exclusivity" value="1">
            <span>Ofrezco exclusividad territorial para este lote</span>
          </label>
          <div class="field mt-1">
            <label for="exclusivity_fee">Prima de exclusividad (€, opcional)</label>
            <input type="number" step="0.01" min="0" id="exclusivity_fee" name="exclusivity_fee" value="0">
          </div>
          <div class="field">
            <label for="message">Mensaje al local</label>
            <textarea id="message" name="message" rows="3" placeholder="Qué hace especial este lote, cuándo puede servirse…"></textarea>
          </div>
          <button class="btn btn-gold" type="submit">Enviar propuesta</button>
        </form>
      <?php endif; ?>
    </article>

    <article class="card">
      <div class="card-head">
        <h2>Tus propuestas a esta petición</h2>
        <span class="muted small"><?= count($proposals) ?></span>
      </div>
      <?php if ($proposals === []): ?>
        <p class="muted small">Aún no has propuesto ningún lote aquí.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Lote</th><th class="num">Coherencia</th><th class="num">Unidades</th><th class="num">Precio</th><th>Estado</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($proposals as $proposal): ?>
                <tr>
                  <td><a href="<?= e(url('/lots/' . $proposal['lot_id'])) ?>"><?= e($proposal['lot_code']) ?></a></td>
                  <td class="num"><?= (int) $proposal['coherence_score'] ?>/100</td>
                  <td class="num"><?= (int) $proposal['units'] ?></td>
                  <td class="num"><?= money($proposal['price_per_unit']) ?></td>
                  <td><span class="badge <?= e(status_class((string) $proposal['status'])) ?>"><?= e(status_label((string) $proposal['status'])) ?></span></td>
                  <td>
                    <?php if ($proposal['status'] === 'pendiente'): ?>
                      <form class="inline-form" method="post" action="<?= e(url('/workshop/proposals/' . $proposal['id'] . '/withdraw')) ?>" data-confirm="¿Retirar esta propuesta?">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-ghost" type="submit">Retirar</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </article>
  </div>

  <aside>
    <div class="card">
      <h3>El local</h3>
      <p class="gold" style="font-family:var(--serif);font-size:1.3rem;margin-bottom:4px"><?= e($requestRow['venue_name']) ?></p>
      <p class="muted small"><?= e($requestRow['territory']) ?></p>
      <span class="badge <?= e(status_class((string) $requestRow['license_status'])) ?>">Licencia <?= e(status_label((string) $requestRow['license_status'])) ?></span>
      <div class="divider"></div>
      <p class="muted small">La licencia se verifica manualmente antes de que el local pueda pedir. Tú no necesitas verificarla: la plataforma ya lo hizo.</p>
    </div>

    <div class="card">
      <h3>Reglas de la propuesta</h3>
      <ul class="list">
        <li><span class="title">Lote real</span><div class="sub">La propuesta apunta a una barrica registrada, no a una intención.</div></li>
        <li><span class="title">Unidades disponibles</span><div class="sub">No puedes ofrecer más de las que el lote tiene libres.</div></li>
        <li><span class="title">Coherencia mínima</span><div class="sub">El mercado exige <?= num($minScore, 0) ?>/100 para proponer.</div></li>
        <li><span class="title">Una propuesta por lote</span><div class="sub">No se puede duplicar la misma oferta en la misma petición.</div></li>
      </ul>
    </div>
  </aside>
</div>
