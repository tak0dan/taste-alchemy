<?php
/** @var array<int, array<string, mixed>> $commissions */
?>
<div class="page-head">
  <p class="eyebrow">Local · comisiones</p>
  <h1>Tus tratos cerrados</h1>
  <p class="lede">Cada comisión conserva su lote, su factura o consignación y la valoración posterior. Nada se reescribe.</p>
  <div class="actions">
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/bar')) ?>">Volver al bar</a>
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/marketplace')) ?>">Buscar lotes</a>
  </div>
</div>

<?php if ($commissions === []): ?>
  <?= partial('empty', [
      'title' => 'Sin comisiones todavía',
      'message' => 'Acepta una propuesta en el mercado y la comisión aparecerá aquí.',
      'actionUrl' => url('/marketplace'),
      'actionLabel' => 'Ir al mercado',
  ]) ?>
<?php else: ?>
  <div class="card">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Comisión</th><th>Lote</th><th class="num">Unidades</th><th class="num">Importe</th><th>Modo</th><th>Estado</th><th>Factura</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($commissions as $commission): ?>
            <tr>
              <td><a href="<?= e(url('/commissions/' . $commission['id'])) ?>">#<?= (int) $commission['id'] ?></a><br><span class="muted small"><?= e(format_date($commission['created_at'])) ?></span></td>
              <td><?= e($commission['lot_code']) ?><br><span class="muted small"><?= e($commission['recipe_title']) ?></span></td>
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
