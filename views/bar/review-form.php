<?php
/** @var array<string, mixed> $detail */
$commission = $detail['commission'];
$consignment = $detail['consignment'];
$invoice = $detail['invoice'];
$reviews = $detail['reviews'];
?>
<div class="page-head">
  <p class="eyebrow">Valoración verificada · comisión #<?= (int) $commission['id'] ?></p>
  <h1><?= e($commission['recipe_title']) ?></h1>
  <div class="meta-line">
    <span>Lote <b><?= e($commission['lot_code']) ?></b></span>
    <span>Taller <b><?= e($commission['studio_name'] ?: $commission['artisan_name']) ?></b></span>
    <span>Cerrada <?= e(format_date($commission['completed_at'], 'd/m/Y H:i')) ?></span>
    <span><?= (int) $commission['units'] ?> unidades</span>
  </div>
</div>

<div class="grid grid-side">
  <form class="card" method="post" action="<?= e(url('/bar/reviews/' . $commission['id'])) ?>">
    <?= csrf_field() ?>
    <h2>Tu cata</h2>
    <p class="muted small">Puntúa de 1 a 10. Puedes dejar dimensiones en blanco; la nota total se calcula con las que rellenes.</p>

    <div class="form-row-3">
      <?php foreach (['aroma' => 'Aroma', 'texture' => 'Textura', 'balance' => 'Equilibrio', 'finish' => 'Final'] as $key => $label): ?>
        <div class="field">
          <label for="score_<?= e($key) ?>"><?= e($label) ?></label>
          <input type="number" step="1" min="1" max="10" id="score_<?= e($key) ?>" name="score_<?= e($key) ?>" value="<?= e(old('score_' . $key)) ?>">
        </div>
      <?php endforeach; ?>
    </div>

    <div class="field">
      <label for="body">Nota de cata</label>
      <textarea id="body" name="body" rows="6" required minlength="20" placeholder="Qué se sirvió, cómo evolucionó en copa, con qué funcionó…"><?= e(old('body')) ?></textarea>
      <span class="hint">Mínimo 20 caracteres. Se publica con tu nombre de local.</span>
    </div>

    <button class="btn btn-gold" type="submit">Publicar valoración</button>
  </form>

  <aside>
    <div class="card">
      <h3>La comisión</h3>
      <dl class="kv">
        <dt>Lote</dt><dd><?= e($commission['lot_code']) ?></dd>
        <dt>Unidades</dt><dd><?= (int) $commission['units'] ?></dd>
        <dt>Importe</dt><dd><?= money($commission['total_amount']) ?></dd>
        <dt>Modo</dt><dd><?= e(status_label((string) $commission['mode'])) ?></dd>
        <dt>Estado</dt><dd><?= e(status_label((string) $commission['status'])) ?></dd>
      </dl>
      <?php if ($consignment !== null): ?>
        <div class="divider"></div>
        <p class="eyebrow">Consignación</p>
        <dl class="kv">
          <dt>Vencimiento</dt><dd><?= e(format_date($consignment['due_date'])) ?></dd>
          <dt>Vendidas</dt><dd><?= (int) $consignment['sold_units'] ?></dd>
          <dt>Devueltas</dt><dd><?= (int) $consignment['returned_units'] ?></dd>
        </dl>
      <?php endif; ?>
      <?php if ($invoice !== null): ?>
        <div class="divider"></div>
        <p class="eyebrow">Factura</p>
        <dl class="kv">
          <dt>Número</dt><dd><?= e($invoice['number']) ?></dd>
          <dt>Total</dt><dd><?= money($invoice['total_amount']) ?></dd>
        </dl>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3>Ya valorada</h3>
      <?php if ($reviews === []): ?>
        <p class="muted small">Esta comisión aún no tiene valoración.</p>
      <?php else: ?>
        <?php foreach ($reviews as $review): ?>
          <div class="review">
            <div class="who">
              <span class="avatar" aria-hidden="true"><?= e(initials((string) $review['author_name'])) ?></span>
              <div><b><?= e($review['author_name']) ?></b><span><?= e(format_date($review['created_at'])) ?></span></div>
            </div>
            <blockquote>«<?= e($review['body']) ?>»</blockquote>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </aside>
</div>
