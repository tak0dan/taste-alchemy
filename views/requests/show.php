<?php
/** @var array<string, mixed> $detail */
$requestRow = $detail['request'];
$proposals = $detail['proposals'];
$user = auth_user();
$isOwner = $user !== null && (int) $user['id'] === (int) $requestRow['venue_id'];
?>
<div class="page-head">
  <p class="eyebrow"><?= e($requestRow['style']) ?> · <?= e(status_label((string) $requestRow['status'])) ?></p>
  <h1><?= e($requestRow['title']) ?></h1>
  <div class="meta-line">
    <span>por <b><?= e($requestRow['venue_name']) ?></b></span>
    <span><?= e($requestRow['territory']) ?></span>
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
        <div class="p"><div class="k">Publicada</div><div class="v"><?= e(format_date($requestRow['created_at'], 'd/m')) ?></div></div>
      </div>
    </article>

    <article class="card">
      <div class="card-head">
        <h2>Propuestas</h2>
        <span class="muted small"><?= count($proposals) ?> visibles para ti</span>
      </div>
      <?php if ($proposals === []): ?>
        <p class="muted">Aún no hay propuestas visibles. Un artesano sólo puede proponer lotes propios que superen el umbral de coherencia.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Lote</th><th class="num">Coherencia</th><th class="num">Unidades</th><th class="num">Precio</th><th>Modo</th><th>Taller</th></tr></thead>
            <tbody>
              <?php foreach ($proposals as $proposal): ?>
                <tr>
                  <td><a href="<?= e(url('/lots/' . $proposal['lot_id'])) ?>"><?= e($proposal['lot_code']) ?></a><br><span class="muted small"><?= e($proposal['recipe_title']) ?></span></td>
                  <td class="num"><?= (int) $proposal['coherence_score'] ?>/100</td>
                  <td class="num"><?= (int) $proposal['units'] ?></td>
                  <td class="num"><?= money($proposal['price_per_unit']) ?></td>
                  <td><?= e(status_label((string) $proposal['mode'])) ?></td>
                  <td><?= e($proposal['studio_name'] ?: $proposal['artisan_name']) ?></td>
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
      <p class="muted small">La licencia se verifica manualmente. Sin verificación, el local no puede abrir peticiones.</p>
    </div>

    <div class="card">
      <h3>¿Y ahora?</h3>
      <?php if ($isOwner): ?>
        <p class="dim small">Esta es tu petición. Gestiona las propuestas y acepta la que mejor encaje.</p>
        <a class="btn btn-primary btn-block" href="<?= e(url('/bar/requests/' . $requestRow['id'])) ?>">Gestionar propuestas</a>
      <?php elseif ($user !== null && has_role('artesano')): ?>
        <p class="dim small">Si tienes un lote elegible, presenta una propuesta concreta.</p>
        <a class="btn btn-primary btn-block" href="<?= e(url('/workshop/opportunities/' . $requestRow['id'])) ?>">Proponer un lote</a>
      <?php else: ?>
        <p class="dim small">Para proponer necesitas una cuenta de artesano con lotes registrados.</p>
        <a class="btn btn-primary btn-block" href="<?= e(url('/register?kind=artesano')) ?>">Abrir taller</a>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3>Reglas de la casa</h3>
      <ul class="list">
        <li><span class="title">Sólo lotes reales</span><div class="sub">La propuesta apunta a un lote registrado, no a una intención.</div></li>
        <li><span class="title">Coherencia mínima 70</span><div class="sub">Por debajo del umbral, el lote no puede proponerse.</div></li>
        <li><span class="title">Reserva atómica</span><div class="sub">Aceptar una propuesta descuenta unidades de verdad.</div></li>
        <li><span class="title">Sin pago en plataforma</span><div class="sub">La factura se emite entre NIFs; el cobro se acuerda fuera.</div></li>
      </ul>
    </div>
  </aside>
</div>
