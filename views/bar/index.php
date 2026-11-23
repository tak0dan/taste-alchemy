<?php
/** @var array<string, mixed>|null $profile */
/** @var array<int, array<string, mixed>> $requests */
/** @var array<int, array<string, mixed>> $commissions */
/** @var array<int, array<string, mixed>> $pendingReviews */
/** @var array<int, array<string, mixed>> $notifications */
/** @var array<string, array<string, mixed>> $stats */
/** @var bool $verified */
$user = auth_user();
?>
<div class="page-head">
  <p class="eyebrow">Local · <?= e($profile['territory'] ?? 'sin territorio') ?></p>
  <h1><?= e($profile['venue_name'] ?? $user['display_name']) ?></h1>
  <p class="lede">Compras lotes, no presentaciones. Cada propuesta llega con su coherencia y sus unidades reales.</p>
  <div class="actions">
    <?php if ($verified): ?>
      <a class="btn btn-gold" href="<?= e(url('/bar/requests/new')) ?>">Abrir petición</a>
    <?php else: ?>
      <a class="btn btn-gold" href="<?= e(url('/bar/verification')) ?>">Verificar licencia</a>
    <?php endif; ?>
    <a class="btn" href="<?= e(url('/marketplace')) ?>">Ver mercado</a>
    <a class="btn btn-ghost" href="<?= e(url('/bar/reviews')) ?>">Valoraciones<?= count($pendingReviews) > 0 ? ' · ' . count($pendingReviews) : '' ?></a>
  </div>
</div>

<div class="stats">
  <div class="stat">
    <div class="k"><?= count($requests) ?></div>
    <div class="l">Peticiones propias</div>
    <div class="s"><?= (int) ($stats['requests']['open'] ?? 0) ?> abiertas en toda la plataforma</div>
  </div>
  <div class="stat">
    <div class="k"><?= count($commissions) ?></div>
    <div class="l">Comisiones</div>
    <div class="s"><?= (int) ($stats['commissions']['completed'] ?? 0) ?> completadas en total</div>
  </div>
  <div class="stat">
    <div class="k"><?= count($pendingReviews) ?></div>
    <div class="l">Catas pendientes</div>
    <div class="s">72 h de reflexión cumplidas</div>
  </div>
  <div class="stat">
    <div class="k"><?= e(status_label((string) ($profile['license_status'] ?? 'pendiente'))) ?></div>
    <div class="l">Licencia</div>
    <div class="s"><?= $verified ? 'Puedes abrir peticiones' : 'Verificación manual' ?></div>
  </div>
</div>

<?php if (!$verified): ?>
  <div class="notice">
    <b>Tu licencia está <?= e(strtolower(status_label((string) ($profile['license_status'] ?? 'pendiente')))) ?>.</b>
    Un moderador revisa el NIF y la licencia a mano. Hasta entonces puedes leer el mercado y preparar tu perfil, pero no abrir peticiones.
  </div>
<?php endif; ?>

<div class="grid grid-side">
  <div>
    <section class="card">
      <div class="card-head">
        <h2>Tus peticiones</h2>
        <?php if ($verified): ?><a class="btn btn-sm btn-ghost" href="<?= e(url('/bar/requests/new')) ?>">Nueva petición</a><?php endif; ?>
      </div>
      <?php if ($requests === []): ?>
        <?= partial('empty', [
            'title' => 'Aún no has pedido nada',
            'message' => $verified
                ? 'Describe una necesidad concreta y recibe propuestas de lotes reales.'
                : 'Primero verifica tu licencia; después podrás abrir peticiones.',
        ]) ?>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Petición</th><th>Estilo</th><th class="num">Unidades</th><th>Objetivo</th><th class="num">Propuestas</th><th>Estado</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($requests as $requestRow): ?>
                <tr>
                  <td><a href="<?= e(url('/bar/requests/' . $requestRow['id'])) ?>"><?= e($requestRow['title']) ?></a><br><span class="muted small"><?= e(format_date($requestRow['created_at'])) ?></span></td>
                  <td><?= e($requestRow['style']) ?></td>
                  <td class="num"><?= (int) $requestRow['units_wanted'] ?></td>
                  <td><?= e(format_date($requestRow['target_date'])) ?></td>
                  <td class="num"><?= (int) $requestRow['pending_proposals'] ?> / <?= (int) $requestRow['total_proposals'] ?></td>
                  <td><span class="badge <?= e(status_class((string) $requestRow['status'])) ?>"><?= e(status_label((string) $requestRow['status'])) ?></span></td>
                  <td><a class="btn btn-sm btn-ghost" href="<?= e(url('/bar/requests/' . $requestRow['id'])) ?>">Gestionar</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head">
        <h2>Comisiones recientes</h2>
        <a class="btn btn-sm btn-ghost" href="<?= e(url('/bar/commissions')) ?>">Ver todas →</a>
      </div>
      <?php if ($commissions === []): ?>
        <p class="muted">Cuando aceptes una propuesta, la comisión aparecerá aquí con su factura o consignación.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Comisión</th><th>Lote</th><th class="num">Unidades</th><th class="num">Importe</th><th>Estado</th></tr></thead>
            <tbody>
              <?php foreach ($commissions as $commission): ?>
                <tr>
                  <td><a href="<?= e(url('/commissions/' . $commission['id'])) ?>">#<?= (int) $commission['id'] ?></a></td>
                  <td><?= e($commission['lot_code']) ?><br><span class="muted small"><?= e($commission['recipe_title']) ?></span></td>
                  <td class="num"><?= (int) $commission['units'] ?></td>
                  <td class="num"><?= money($commission['total_amount']) ?></td>
                  <td><span class="badge <?= e(status_class((string) $commission['status'])) ?>"><?= e(status_label((string) $commission['status'])) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <aside>
    <div class="card">
      <h3>Catas pendientes</h3>
      <?php if ($pendingReviews === []): ?>
        <p class="muted small">No hay comisiones esperando valoración. Cuando pasen 72 h desde el cierre, podrás puntuar.</p>
      <?php else: ?>
        <ul class="list">
          <?php foreach ($pendingReviews as $pending): ?>
            <li>
              <div class="flex between">
                <span class="title"><?= e($pending['lot_code']) ?></span>
                <span class="badge warn">72 h cumplidas</span>
              </div>
              <div class="sub"><?= e($pending['recipe_title']) ?> · <?= e($pending['studio_name'] ?? '') ?></div>
              <a class="btn btn-sm btn-ghost mt-1" href="<?= e(url('/bar/reviews/' . $pending['id'])) ?>">Valorar</a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-head">
        <h3>Avisos</h3>
        <span class="muted small"><?= count($notifications) ?></span>
      </div>
      <?php if ($notifications === []): ?>
        <p class="muted small">Sin avisos recientes.</p>
      <?php else: ?>
        <?php foreach ($notifications as $notification): ?>
          <div class="notif <?= $notification['read_at'] === null ? 'unread' : '' ?>">
            <div class="t"><?= e($notification['title']) ?></div>
            <div class="b"><?= e($notification['body']) ?></div>
            <div class="b"><?= e(format_date($notification['created_at'], 'd/m/Y H:i')) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </aside>
</div>
