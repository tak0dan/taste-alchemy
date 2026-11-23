<?php
/** @var array<string, mixed> $detail */
$commission = $detail['commission'];
$consignment = $detail['consignment'];
$invoice = $detail['invoice'];
$reviews = $detail['reviews'];
$user = auth_user();
$isVenue = (int) $user['id'] === (int) $commission['venue_id'];
$isArtisan = (int) $user['id'] === (int) $commission['artisan_id'];
$canClose = $consignment !== null && in_array($consignment['status'], ['abierta', 'en_disputa'], true) && ($isVenue || $isArtisan);
$canComplete = $isVenue && $commission['mode'] === 'venta' && in_array($commission['status'], ['abierta', 'en_ejecucion'], true);
$canReview = $isVenue && $commission['status'] === 'completada' && (int) $commission['venue_review_count'] === 0
    && strtotime((string) $commission['completed_at']) + 72 * 3600 <= time();
?>
<div class="page-head">
  <p class="eyebrow">Comisión #<?= (int) $commission['id'] ?> · <?= e(status_label((string) $commission['status'])) ?></p>
  <h1><?= e($commission['recipe_title']) ?></h1>
  <div class="meta-line">
    <span>Lote <b><?= e($commission['lot_code']) ?></b> · v<?= (int) $commission['version_no'] ?></span>
    <span>Local <b><?= e($commission['venue_name']) ?></b></span>
    <span>Taller <b><?= e($commission['studio_name'] ?: $commission['artisan_name']) ?></b></span>
    <span>Creada <?= e(format_date($commission['created_at'], 'd/m/Y H:i')) ?></span>
    <?php if (!empty($commission['exclusivity_granted'])): ?><span class="badge">Exclusividad concedida</span><?php endif; ?>
  </div>
  <div class="actions">
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/lots/' . $commission['lot_id'])) ?>">Ver lote</a>
    <a class="btn btn-sm btn-ghost" href="<?= e(url($isVenue ? '/bar/commissions' : '/workshop/commissions')) ?>">Todas tus comisiones</a>
    <?php if ($canReview): ?><a class="btn btn-sm btn-primary" href="<?= e(url('/bar/reviews/' . $commission['id'])) ?>">Valorar comisión</a><?php endif; ?>
  </div>
</div>

<div class="grid grid-side">
  <div>
    <div class="card">
      <h2>El trato</h2>
      <?php if ($canComplete): ?>
        <form class="notice mb-2" method="post" action="<?= e(url('/commissions/' . $commission['id'] . '/complete')) ?>" data-confirm="¿Confirmar que has recibido las <?= (int) $commission['units'] ?> unidades? La comisión se completará.">
          <?= csrf_field() ?>
          <b>Entrega pendiente de confirmación.</b> Cuando el lote llegue al local, confírmalo aquí: la comisión se completará y empezará la reflexión de 72 horas antes de valorar.
          <button class="btn btn-primary btn-sm mt-1" type="submit">Confirmar entrega</button>
        </form>
      <?php endif; ?>
      <div class="phys">
        <div class="p"><div class="k">Unidades</div><div class="v"><?= (int) $commission['units'] ?></div></div>
        <div class="p"><div class="k">Precio unitario</div><div class="v"><?= money($commission['unit_price']) ?></div></div>
        <div class="p"><div class="k">Importe</div><div class="v"><?= money($commission['total_amount']) ?></div></div>
        <div class="p"><div class="k">Modo</div><div class="v"><?= e(status_label((string) $commission['mode'])) ?></div></div>
      </div>
      <dl class="kv mt-2">
        <dt>Petición</dt><dd><a href="<?= e(url('/requests/' . $commission['request_id'])) ?>"><?= e($commission['request_title']) ?></a></dd>
        <dt>Territorio</dt><dd><?= e($commission['territory']) ?></dd>
        <dt>Objetivo</dt><dd><?= e(format_date($commission['target_date'])) ?></dd>
        <dt>Completada</dt><dd><?= e(format_date($commission['completed_at'], 'd/m/Y H:i')) ?></dd>
        <dt>NIF local</dt><dd><?= e(mask_nif($commission['venue_nif'])) ?></dd>
        <dt>NIF taller</dt><dd><?= e(mask_nif($commission['artisan_nif'])) ?></dd>
      </dl>
    </div>

    <?php if ($consignment !== null): ?>
      <div class="card">
        <div class="card-head">
          <h2>Consignación</h2>
          <span class="badge <?= e(status_class((string) $consignment['status'])) ?>"><?= e(status_label((string) $consignment['status'])) ?></span>
        </div>
        <div class="phys">
          <div class="p"><div class="k">Unidades</div><div class="v"><?= (int) $consignment['units'] ?></div></div>
          <div class="p"><div class="k">Vendidas</div><div class="v"><?= (int) $consignment['sold_units'] ?></div></div>
          <div class="p"><div class="k">Devueltas</div><div class="v"><?= (int) $consignment['returned_units'] ?></div></div>
          <div class="p"><div class="k">Vencimiento</div><div class="v"><?= e(format_date($consignment['due_date'], 'd/m')) ?></div></div>
        </div>
        <?php if ($canClose): ?>
          <div class="divider"></div>
          <form method="post" action="<?= e(url('/commissions/' . $commission['id'] . '/consignment')) ?>" data-confirm="¿Cerrar la consignación? Se emitirá factura por las unidades vendidas.">
            <?= csrf_field() ?>
            <div class="form-row">
              <div class="field">
                <label for="sold_units">Unidades vendidas</label>
                <input type="number" step="1" min="0" max="<?= (int) $consignment['units'] ?>" id="sold_units" name="sold_units" required value="<?= (int) $consignment['units'] ?>">
              </div>
              <div class="field">
                <label for="returned_units">Unidades devueltas</label>
                <input type="number" step="1" min="0" max="<?= (int) $consignment['units'] ?>" id="returned_units" name="returned_units" value="0">
              </div>
            </div>
            <p class="muted small">Vendidas + devueltas no pueden superar las unidades consignadas. La factura se emite sólo si hay unidades vendidas.</p>
            <button class="btn btn-primary" type="submit">Cerrar consignación</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($invoice !== null): ?>
      <div class="card">
        <div class="card-head">
          <h2>Factura</h2>
          <span class="badge ok"><?= e($invoice['number']) ?></span>
        </div>
        <div class="phys">
          <div class="p"><div class="k">Base</div><div class="v"><?= money($invoice['base_amount']) ?></div></div>
          <div class="p"><div class="k">IVA <?= num($invoice['vat_rate'], 0) ?> %</div><div class="v"><?= money($invoice['vat_amount']) ?></div></div>
          <div class="p"><div class="k">Total</div><div class="v"><?= money($invoice['total_amount']) ?></div></div>
          <div class="p"><div class="k">Emitida</div><div class="v"><?= e(format_date($invoice['issued_at'], 'd/m/Y')) ?></div></div>
        </div>
        <dl class="kv mt-2">
          <dt>Emisor</dt><dd><?= e(mask_nif($invoice['issuer_nif'])) ?></dd>
          <dt>Receptor</dt><dd><?= e(mask_nif($invoice['recipient_nif'])) ?></dd>
          <dt>Tipo</dt><dd><?= e(status_label((string) $invoice['kind'])) ?></dd>
        </dl>
        <p class="muted small mt-1">La factura se emite entre NIFs. La plataforma no procesa el cobro.</p>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head">
        <h2>Valoraciones</h2>
        <span class="muted small"><?= count($reviews) ?></span>
      </div>
      <?php if ($reviews === []): ?>
        <p class="muted">Sin valoraciones todavía. La cata del local espera 72 horas desde el cierre.</p>
      <?php else: ?>
        <?php foreach ($reviews as $review): ?>
          <div class="review">
            <div class="who">
              <span class="avatar" aria-hidden="true"><?= e(initials((string) $review['author_name'])) ?></span>
              <div><b><?= e($review['author_name']) ?></b><span><?= e(format_date($review['created_at'])) ?></span></div>
              <?php if ($review['score_total'] !== null): ?><span class="badge ok right"><?= num($review['score_total'], 1) ?>/10</span><?php endif; ?>
            </div>
            <blockquote>«<?= e($review['body']) ?>»</blockquote>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <aside>
    <div class="card">
      <h3>Línea temporal</h3>
      <div class="timeline">
        <div class="event">
          <div class="when"><?= e(format_date($commission['created_at'], 'd/m/Y H:i')) ?></div>
          <div class="what">Propuesta aceptada</div>
          <div class="why">Las unidades quedaron reservadas de forma atómica.</div>
        </div>
        <?php if ($consignment !== null): ?>
          <div class="event">
            <div class="when">Consignación</div>
            <div class="what"><?= (int) $consignment['units'] ?> unidades en depósito</div>
            <div class="why">Vence el <?= e(format_date($consignment['due_date'])) ?>.</div>
          </div>
        <?php endif; ?>
        <?php if ($invoice !== null): ?>
          <div class="event">
            <div class="when"><?= e(format_date($invoice['issued_at'], 'd/m/Y')) ?></div>
            <div class="what">Factura <?= e($invoice['number']) ?></div>
            <div class="why"><?= money($invoice['total_amount']) ?> entre NIFs.</div>
          </div>
        <?php endif; ?>
        <?php if ($commission['completed_at']): ?>
          <div class="event">
            <div class="when"><?= e(format_date($commission['completed_at'], 'd/m/Y H:i')) ?></div>
            <div class="what">Comisión completada</div>
            <div class="why">Empieza la reflexión de 72 horas antes de valorar.</div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <h3>Contraparte</h3>
      <?php if ($isVenue): ?>
        <p class="gold" style="font-family:var(--serif);font-size:1.2rem"><?= e($commission['studio_name'] ?: $commission['artisan_name']) ?></p>
        <p class="muted small"><?= e($commission['artisan_name']) ?></p>
        <?php if (!empty($commission['artisan_slug'])): ?>
          <a class="btn btn-sm btn-ghost btn-block" href="<?= e(url('/artisans/' . $commission['artisan_slug'])) ?>">Ver taller</a>
        <?php endif; ?>
      <?php else: ?>
        <p class="gold" style="font-family:var(--serif);font-size:1.2rem"><?= e($commission['venue_name']) ?></p>
        <p class="muted small"><?= e($commission['venue_contact']) ?></p>
        <span class="badge <?= e(status_class((string) $commission['license_status'])) ?>">Licencia <?= e(status_label((string) $commission['license_status'])) ?></span>
      <?php endif; ?>
    </div>
  </aside>
</div>
