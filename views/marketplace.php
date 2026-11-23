<?php
/** @var array<int, array<string, mixed>> $requests */
/** @var array<int, array<string, mixed>> $listings */
/** @var array<string, mixed> $filters */
$user = auth_user();
?>
<div class="page-head">
  <p class="eyebrow">Mercado · demanda y oferta real</p>
  <h1>Donde el local pide y el artesano propone</h1>
  <p class="lede">Las peticiones describen una necesidad concreta. Las propuestas sólo pueden apuntar a lotes registrados, con su coherencia y sus unidades. No hay catálogo de humo.</p>
  <div class="actions">
    <?php if ($user !== null && has_role('local')): ?>
      <a class="btn btn-gold" href="<?= e(url('/bar/requests/new')) ?>">Abrir una petición</a>
    <?php else: ?>
      <a class="btn btn-gold" href="<?= e(url('/register?kind=local')) ?>">Soy un local</a>
    <?php endif; ?>
    <?php if ($user !== null && has_role('artesano')): ?>
      <a class="btn" href="<?= e(url('/workshop/opportunities')) ?>">Ver desde el taller</a>
    <?php else: ?>
      <a class="btn" href="<?= e(url('/register?kind=artesano')) ?>">Soy artesano</a>
    <?php endif; ?>
  </div>
</div>

<form class="card" method="get" action="<?= e(url('/marketplace')) ?>" role="search">
  <div class="form-row-3">
    <div class="field">
      <label for="q">Buscar</label>
      <input type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="título, local, estilo…">
    </div>
    <div class="field">
      <label for="territory">Territorio</label>
      <input type="text" id="territory" name="territory" value="<?= e($filters['territory']) ?>">
    </div>
    <div class="field" style="align-self:end">
      <button class="btn btn-primary btn-block" type="submit">Filtrar peticiones</button>
    </div>
  </div>
</form>

<section>
  <div class="card-head">
    <div>
      <p class="eyebrow">Demanda</p>
      <h2>Peticiones abiertas</h2>
    </div>
    <span class="muted small"><?= count($requests) ?> peticiones visibles</span>
  </div>
  <?php if ($requests === []): ?>
    <?= partial('empty', [
        'title' => 'No hay peticiones abiertas',
        'message' => 'Cuando un local verificado publique una necesidad, aparecerá aquí.',
        'actionUrl' => url('/register?kind=local'),
        'actionLabel' => 'Abrir un local',
    ]) ?>
  <?php else: ?>
    <div class="grid grid-3">
      <?php foreach ($requests as $requestRow): ?>
        <?= partial('request-card', ['request' => $requestRow]) ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="mt-3">
  <div class="card-head">
    <div>
      <p class="eyebrow">Oferta</p>
      <h2>Anuncios de lote</h2>
    </div>
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/lots')) ?>">Ver todos los lotes →</a>
  </div>
  <?php if ($listings === []): ?>
    <?= partial('empty', ['title' => 'Sin anuncios publicados', 'message' => 'Un lote necesita superar el umbral de coherencia antes de anunciarse.']) ?>
  <?php else: ?>
    <div class="grid grid-3">
      <?php foreach ($listings as $listing): ?>
        <article class="card lot-card">
          <div class="flex between">
            <span class="style"><?= e($listing['lot_code']) ?></span>
            <?= partial('score', ['score' => (int) $listing['coherence_score']]) ?>
          </div>
          <h3><a href="<?= e(url('/lots/' . $listing['lot_id'])) ?>"><?= e($listing['title']) ?></a></h3>
          <div class="meta-line">
            <span><b><?= e($listing['studio_name'] ?: $listing['artisan_name']) ?></b></span>
            <span><?= e($listing['territory']) ?></span>
          </div>
          <p class="dim small"><?= e(mb_strimwidth((string) ($listing['description'] ?? ''), 0, 120, '…')) ?></p>
          <div class="spacer"></div>
          <div class="meta-line">
            <span><b><?= (int) $listing['units_available'] ?></b> unidades</span>
            <span><b><?= money($listing['price_per_unit']) ?></b> / ud.</span>
            <span><?= (int) $listing['unit_ml'] ?> ml</span>
          </div>
          <div class="bottom">
            <span class="muted small"><?= e($listing['recipe_title']) ?></span>
            <a class="btn btn-sm btn-ghost" href="<?= e(url('/lots/' . $listing['lot_id'])) ?>">Ver lote</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="card mt-3">
  <h2>Cómo se cierra un trato</h2>
  <div class="flow">
    <div class="step"><div class="n">01</div><h4>Petición</h4><p>Un local verificado describe lo que necesita.</p></div>
    <div class="step"><div class="n">02</div><h4>Propuesta</h4><p>Un artesano ofrece un lote concreto con su coherencia.</p></div>
    <div class="step"><div class="n">03</div><h4>Aceptación</h4><p>El local elige y las unidades se reservan de forma atómica.</p></div>
    <div class="step"><div class="n">04</div><h4>Comisión</h4><p>Venta con factura o consignación con plazo.</p></div>
    <div class="step"><div class="n">05</div><h4>Valoración</h4><p>72 horas de reflexión y cata verificada.</p></div>
  </div>
</section>
