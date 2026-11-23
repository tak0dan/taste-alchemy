<?php
/** @var array<int, array<string, mixed>> $proposals */
?>
<div class="page-head">
  <p class="eyebrow">Taller · propuestas</p>
  <h1>Tus ofertas en el mercado</h1>
  <p class="lede">Cada propuesta es un lote concreto esperando la decisión de un local. Puedes retirar las que sigan pendientes.</p>
  <div class="actions">
    <a class="btn btn-gold btn-sm" href="<?= e(url('/workshop/opportunities')) ?>">Buscar peticiones</a>
  </div>
</div>

<?php if ($proposals === []): ?>
  <?= partial('empty', [
      'title' => 'Sin propuestas',
      'message' => 'Encuentra una petición compatible y ofrece un lote con su coherencia.',
      'actionUrl' => url('/workshop/opportunities'),
      'actionLabel' => 'Ver peticiones abiertas',
  ]) ?>
<?php else: ?>
  <div class="card">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Petición</th><th>Lote</th><th class="num">Coherencia</th><th class="num">Unidades</th><th class="num">Precio</th><th>Modo</th><th>Estado</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($proposals as $proposal): ?>
            <tr>
              <td>
                <a href="<?= e(url('/workshop/opportunities/' . $proposal['request_id'])) ?>"><?= e($proposal['request_title']) ?></a><br>
                <span class="muted small"><?= e($proposal['venue_name']) ?> · <?= e($proposal['request_territory']) ?></span>
              </td>
              <td>
                <a href="<?= e(url('/lots/' . $proposal['lot_id'])) ?>"><?= e($proposal['lot_code']) ?></a><br>
                <span class="muted small"><?= e($proposal['recipe_title']) ?></span>
              </td>
              <td class="num"><?= (int) $proposal['coherence_score'] ?>/100</td>
              <td class="num"><?= (int) $proposal['units'] ?> / <?= (int) $proposal['units_available'] ?></td>
              <td class="num"><?= money($proposal['price_per_unit']) ?></td>
              <td><?= e(status_label((string) $proposal['mode'])) ?></td>
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
  </div>
<?php endif; ?>
