<?php
/** @var array<int, array<string, mixed>> $commissions */
?>
<div class="page-head">
  <p class="eyebrow">Taller · comisiones</p>
  <h1>Lo que se ha servido</h1>
  <p class="lede">Cada comisión es un trato cerrado: unidades reservadas, factura o consignación, y una valoración que espera 72 horas.</p>
  <div class="actions">
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/workshop/proposals')) ?>">Ver propuestas</a>
  </div>
</div>

<?php if ($commissions === []): ?>
  <?= partial('empty', [
      'title' => 'Sin comisiones todavía',
      'message' => 'Cuando un local acepte una de tus propuestas, la comisión aparecerá aquí.',
      'actionUrl' => url('/workshop/opportunities'),
      'actionLabel' => 'Buscar peticiones',
  ]) ?>
<?php else: ?>
  <div class="card">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Comisión</th><th>Local</th><th class="num">Unidades</th><th class="num">Importe</th><th>Modo</th><th>Estado</th><th>Factura</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($commissions as $commission): ?>
            <tr>
              <td><a href="<?= e(url('/commissions/' . $commission['id'])) ?>">#<?= (int) $commission['id'] ?></a><br><span class="muted small"><?= e($commission['lot_code']) ?> · <?= e($commission['recipe_title']) ?></span></td>
              <td><?= e($commission['venue_name']) ?><br><span class="muted small"><?= e($commission['territory']) ?></span></td>
              <td class="num"><?= (int) $commission['units'] ?></td>
              <td class="num"><?= money($commission['total_amount']) ?></td>
              <td><?= e(status_label((string) $commission['mode'])) ?></td>
              <td><span class="badge <?= e(status_class((string) $commission['status'])) ?>"><?= e(status_label((string) $commission['status'])) ?></span></td>
              <td><?= $commission['invoice_number'] ? e($commission['invoice_number']) : '—' ?></td>
              <td><a class="btn btn-sm btn-ghost" href="<?= e(url('/commissions/' . $commission['id'])) ?>">Abrir</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
