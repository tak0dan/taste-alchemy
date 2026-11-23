<?php
/** @var array<string, mixed> $detail */
$requestRow = $detail['request'];
$proposals = $detail['proposals'];
$open = $requestRow['status'] === 'abierta';
$pending = array_values(array_filter($proposals, static fn(array $p): bool => $p['status'] === 'pendiente'));
?>
<div class="page-head">
  <p class="eyebrow"><?= e($requestRow['style']) ?> · <?= e(status_label((string) $requestRow['status'])) ?></p>
  <h1><?= e($requestRow['title']) ?></h1>
  <div class="meta-line">
    <span><?= e($requestRow['territory']) ?></span>
    <span>Objetivo <?= e(format_date($requestRow['target_date'])) ?></span>
    <span><?= (int) $requestRow['units_wanted'] ?> unidades</span>
    <span><?= count($proposals) ?> propuestas</span>
    <?php if (!empty($requestRow['exclusivity_requested'])): ?><span class="badge">Exclusividad solicitada</span><?php endif; ?>
  </div>
  <div class="actions">
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/bar')) ?>">Volver al bar</a>
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/requests/' . $requestRow['id'])) ?>">Vista pública</a>
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
        <div class="p"><div class="k">Publicada</div><div class="v"><?= e(format_date($requestRow['created_at'], 'd/m')) ?></div></div>
      </div>
    </article>

    <article class="card">
      <div class="card-head">
        <h2>Propuestas recibidas</h2>
        <span class="muted small"><?= count($pending) ?> pendientes de <?= count($proposals) ?></span>
      </div>
      <?php if ($proposals === []): ?>
        <p class="muted">Aún no hay propuestas. Los artesanos han recibido el aviso; puede tardar.</p>
      <?php else: ?>
        <div class="grid" style="gap:14px">
          <?php foreach ($proposals as $proposal): ?>
            <div class="card flat" style="margin:0">
              <div class="flex between">
                <div>
                  <a href="<?= e(url('/lots/' . $proposal['lot_id'])) ?>" class="gold" style="font-family:var(--serif);font-size:1.25rem"><?= e($proposal['lot_code']) ?></a>
                  <div class="muted small"><?= e($proposal['recipe_title']) ?> · v<?= (int) $proposal['version_no'] ?></div>
                </div>
                <span class="badge <?= e(status_class((string) $proposal['status'])) ?>"><?= e(status_label((string) $proposal['status'])) ?></span>
              </div>
              <div class="meta-line mt-1">
                <span>Taller <b><?= e($proposal['studio_name'] ?: $proposal['artisan_name']) ?></b></span>
                <span>Coherencia <b><?= (int) $proposal['coherence_score'] ?>/100</b></span>
                <span>Ofrece <b><?= (int) $proposal['units'] ?></b> uds.</span>
                <span>Libres <b><?= (int) $proposal['units_available'] ?></b></span>
                <span><b><?= money($proposal['price_per_unit']) ?></b> / ud.</span>
                <span><?= e(status_label((string) $proposal['mode'])) ?></span>
                <?php if (!empty($proposal['exclusivity'])): ?><span class="badge">Exclusividad</span><?php endif; ?>
              </div>
              <?php if (!empty($proposal['message'])): ?>
                <p class="dim small">«<?= e($proposal['message']) ?>»</p>
              <?php endif; ?>
              <?php if ($open && $proposal['status'] === 'pendiente'): ?>
                <div class="divider"></div>
                <form method="post" action="<?= e(url('/bar/proposals/' . $proposal['id'] . '/accept')) ?>" data-confirm="¿Aceptar esta propuesta? Se reservarán <?= (int) $proposal['units'] ?> unidades y la petición se cerrará.">
                  <?= csrf_field() ?>
                  <div class="form-row">
                    <div class="field">
                      <label for="mode-<?= (int) $proposal['id'] ?>">Modo de suministro</label>
                      <select id="mode-<?= (int) $proposal['id'] ?>" name="mode">
                        <?php if (in_array($proposal['mode'], ['venta', 'ambas'], true)): ?><option value="venta">Venta directa con factura</option><?php endif; ?>
                        <?php if (in_array($proposal['mode'], ['consignacion', 'ambas'], true)): ?><option value="consignacion">Consignación con plazo</option><?php endif; ?>
                      </select>
                    </div>
                    <div class="field" style="align-self:end">
                      <button class="btn btn-primary btn-block" type="submit">Aceptar y reservar</button>
                    </div>
                  </div>
                </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </article>
  </div>

  <aside>
    <div class="card">
      <h3>Tu petición</h3>
      <dl class="kv">
        <dt>Estado</dt><dd><?= e(status_label((string) $requestRow['status'])) ?></dd>
        <dt>Unidades</dt><dd><?= (int) $requestRow['units_wanted'] ?></dd>
        <dt>Objetivo</dt><dd><?= e(format_date($requestRow['target_date'])) ?></dd>
        <dt>Propuestas</dt><dd><?= count($proposals) ?></dd>
      </dl>
      <div class="divider"></div>
      <p class="muted small">Aceptar una propuesta reserva unidades de forma atómica, cierra la petición y crea la comisión. Las propuestas perdedoras se marcan como rechazadas.</p>
    </div>

    <div class="card">
      <h3>Cómo comparar</h3>
      <ul class="list">
        <li><span class="title">Coherencia primero</span><div class="sub">Es el historial físico del lote, no una nota de cata.</div></li>
        <li><span class="title">Precio por unidad</span><div class="sub">Compáralo con el formato y las unidades libres.</div></li>
        <li><span class="title">Modo de suministro</span><div class="sub">Venta exige factura; consignación exige plazo y devolución.</div></li>
      </ul>
    </div>
  </aside>
</div>
