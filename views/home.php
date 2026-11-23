<?php
/** @var array<string, mixed> $stats */
/** @var array<int, array<string, mixed>> $featuredRecipes */
/** @var array<int, array<string, mixed>> $featuredLots */
/** @var array<int, array<string, mixed>> $artisans */
/** @var array<int, array<string, mixed>> $reviews */
$recipesStats = $stats['recipes'];
$lotsStats = $stats['lots'];
$commissionStats = $stats['commissions'];
?>
<section class="hero">
  <div>
    <p class="eyebrow">Proyecto intermodular · oficio y evidencia</p>
    <h1>El licor no se promete.<br>Se demuestra.</h1>
    <p class="lede">Un repositorio público de recetas ejecutables y un mercado donde cada lote enseña su historial físico: volumen, graduación y cadencia de lecturas.</p>
    <div class="actions">
      <a class="btn btn-gold" href="<?= e(url('/calculator')) ?>">Construir una receta</a>
      <a class="btn" href="<?= e(url('/marketplace')) ?>">Ver el mercado</a>
      <a class="btn btn-ghost" href="<?= e(url('/manifesto')) ?>">Leer el manifiesto</a>
    </div>
  </div>
  <div class="hero-art" aria-hidden="true">
    <span class="ring"></span>
    <span class="ring two"></span>
    <span class="halo"><?= sigil(120) ?></span>
  </div>
</section>

<div class="stats">
  <div class="stat">
    <div class="k"><?= (int) $recipesStats['recipes'] ?></div>
    <div class="l">Recetas públicas</div>
    <div class="s"><?= (int) $recipesStats['versions'] ?> versiones · <?= (int) $recipesStats['forks'] ?> bifurcaciones</div>
  </div>
  <div class="stat">
    <div class="k"><?= (int) $lotsStats['lots'] ?></div>
    <div class="l">Lotes registrados</div>
    <div class="s">Coherencia media <?= num($lotsStats['avg_coherence'], 1) ?>/100</div>
  </div>
  <div class="stat">
    <div class="k"><?= (int) $commissionStats['completed'] ?></div>
    <div class="l">Comisiones cerradas</div>
    <div class="s"><?= (int) $commissionStats['units'] ?> unidades · <?= money($commissionStats['gmv']) ?></div>
  </div>
  <div class="stat">
    <div class="k"><?= (int) $stats['reviews'] ?></div>
    <div class="l">Valoraciones verificadas</div>
    <div class="s"><?= (int) $stats['members'] ?> cuentas activas</div>
  </div>
</div>

<section class="grid grid-2">
  <div class="card">
    <p class="eyebrow">Para el artesano</p>
    <h2>Tu receta, tu barrica, tu historial</h2>
    <p class="dim">Publica una receta ejecutable, registra cada lote y añade lecturas fechadas. La plataforma calcula la coherencia física y la enseña tal cual: sin maquillaje, sin promesas.</p>
    <ul class="list">
      <li><span class="title">Versionado inmutable</span><div class="sub">Cada cambio es una versión nueva; las anteriores siguen citables.</div></li>
      <li><span class="title">Bifurcación con crédito</span><div class="sub">Copia una receta pública, ajústala y conserva el linaje.</div></li>
      <li><span class="title">Anuncio sólo con evidencia</span><div class="sub">Nada se publica por debajo del umbral de coherencia.</div></li>
    </ul>
    <div class="actions">
      <a class="btn btn-primary" href="<?= e(url('/register?kind=artesano')) ?>">Abrir taller</a>
      <a class="btn btn-ghost" href="<?= e(url('/recipes')) ?>">Ver recetas</a>
    </div>
  </div>
  <div class="card">
    <p class="eyebrow">Para el local</p>
    <h2>Compra lotes, no presentaciones</h2>
    <p class="dim">Abre una petición concreta y compara propuestas de lotes reales. Cada propuesta llega con su código, su coherencia y sus unidades disponibles. Consignación o venta: tú eliges.</p>
    <ul class="list">
      <li><span class="title">Licencia verificada</span><div class="sub">Un moderador comprueba NIF y licencia antes de pedir.</div></li>
      <li><span class="title">Reserva transaccional</span><div class="sub">Aceptar una propuesta reserva unidades de verdad, sin sobreventa.</div></li>
      <li><span class="title">Valoración con reflexión</span><div class="sub">72 horas antes de puntuar. Sólo la contraparte de una comisión real.</div></li>
    </ul>
    <div class="actions">
      <a class="btn btn-primary" href="<?= e(url('/register?kind=local')) ?>">Abrir local</a>
      <a class="btn btn-ghost" href="<?= e(url('/marketplace')) ?>">Ver peticiones</a>
    </div>
  </div>
</section>

<section class="mt-3">
  <div class="card-head">
    <div>
      <p class="eyebrow">Repositorio público</p>
      <h2>Recetas recientes</h2>
    </div>
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/recipes')) ?>">Todo el catálogo →</a>
  </div>
  <?php if ($featuredRecipes === []): ?>
    <?= partial('empty', ['title' => 'Aún no hay recetas', 'message' => 'La primera receta pública abrirá el repositorio.']) ?>
  <?php else: ?>
    <div class="grid grid-3">
      <?php foreach ($featuredRecipes as $recipe): ?>
        <?= partial('recipe-card', ['recipe' => $recipe]) ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="card mt-3">
  <p class="eyebrow">El mecanismo de tiraje</p>
  <h2>De la petición a la factura, sin saltos</h2>
  <div class="flow">
    <div class="step"><div class="n">01</div><h4>Petición</h4><p>El local verificado describe estilo, unidades, territorio y fecha objetivo.</p></div>
    <div class="step"><div class="n">02</div><h4>Propuesta</h4><p>El artesano ofrece un lote concreto con su coherencia y precio por unidad.</p></div>
    <div class="step"><div class="n">03</div><h4>Comisión</h4><p>Aceptar reserva unidades de forma atómica y cierra la petición.</p></div>
    <div class="step"><div class="n">04</div><h4>Consignación o factura</h4><p>Venta directa con factura entre NIFs, o consignación con plazo.</p></div>
    <div class="step"><div class="n">05</div><h4>Valoración</h4><p>Tras 72 h de reflexión, el local puntúa el lote servido.</p></div>
  </div>
</section>

<section class="mt-3">
  <div class="card-head">
    <div>
      <p class="eyebrow">Mercado</p>
      <h2>Lotes con evidencia</h2>
    </div>
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/lots')) ?>">Ver todos →</a>
  </div>
  <?php if ($featuredLots === []): ?>
    <?= partial('empty', ['title' => 'Sin lotes todavía', 'message' => 'Un lote se registra con su primera lectura física.']) ?>
  <?php else: ?>
    <div class="grid grid-3">
      <?php foreach ($featuredLots as $lot): ?>
        <?= partial('lot-card', ['lot' => $lot]) ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="grid grid-2 mt-3">
  <div class="card">
    <p class="eyebrow">La credencial que no se fabrica</p>
    <h2>Coherencia física, explicada</h2>
    <p class="dim">No pedimos confianza. Medimos cuatro cosas y publicamos el desglose: evaporación de barrica, trayectoria de graduación, cadencia de lecturas y coherencia de la declaración.</p>
    <div class="breakdown">
      <div class="item"><span class="name">Evaporación de barrica</span><span class="val">35 %</span><span class="meter"><i style="width:35%"></i></span></div>
      <div class="item"><span class="name">Trayectoria de graduación</span><span class="val">25 %</span><span class="meter"><i style="width:25%"></i></span></div>
      <div class="item"><span class="name">Cadencia de lecturas</span><span class="val">20 %</span><span class="meter"><i style="width:20%"></i></span></div>
      <div class="item"><span class="name">Declaración frente a la última lectura</span><span class="val">20 %</span><span class="meter"><i style="width:20%"></i></span></div>
    </div>
    <div class="notice mt-2">Un lote por debajo de <b>70/100</b> no puede publicar anuncio ni proponerse a una petición. El umbral se administra y se audita.</div>
  </div>
  <div class="card">
    <p class="eyebrow">Voces verificadas</p>
    <h2>Lo que se sirvió</h2>
    <?php if ($reviews === []): ?>
      <?= partial('empty', ['title' => 'Aún no hay catas', 'message' => 'Las valoraciones nacen de comisiones reales, no de formularios abiertos.']) ?>
    <?php else: ?>
      <?php foreach ($reviews as $review): ?>
        <div class="review">
          <div class="who">
            <span class="avatar" aria-hidden="true"><?= e(initials((string) $review['venue_name'])) ?></span>
            <div>
              <b><?= e($review['venue_name']) ?></b><br>
              <span><?= e($review['recipe_title']) ?> · lote <?= e($review['lot_code']) ?></span>
            </div>
            <span class="badge ok right"><?= num($review['score_total'], 1) ?>/10</span>
          </div>
          <blockquote>«<?= e(mb_strimwidth((string) $review['body'], 0, 220, '…')) ?>»</blockquote>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<?php if ($artisans !== []): ?>
<section class="card mt-3">
  <div class="card-head">
    <div>
      <p class="eyebrow">Talleres</p>
      <h2>Oficios con historial</h2>
    </div>
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/artisans')) ?>">Ver talleres →</a>
  </div>
  <div class="grid grid-4">
    <?php foreach ($artisans as $artisan): ?>
      <a class="card flat" href="<?= e(url('/artisans/' . $artisan['slug'])) ?>" style="text-decoration:none">
        <div class="flex between">
          <span class="style"><?= e($artisan['territory']) ?></span>
          <?php if ((float) ($artisan['avg_coherence'] ?? 0) > 0): ?>
            <?= partial('score', ['score' => (int) round((float) $artisan['avg_coherence'])]) ?>
          <?php endif; ?>
        </div>
        <h3 class="mt-1"><?= e($artisan['studio_name']) ?></h3>
        <div class="muted small"><?= (int) $artisan['public_recipes'] ?> recetas · <?= (int) $artisan['lot_count'] ?> lotes</div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="card center mt-3">
  <p class="eyebrow">Sin pasarela, sin intermediarios logísticos</p>
  <h2>El trato se cierra entre dos oficios</h2>
  <p class="lede" style="margin:0 auto 18px">La plataforma pone el lenguaje, la evidencia y la trazabilidad. La botella, el precio y la palabra siguen siendo de quien los firma.</p>
  <div class="actions" style="justify-content:center">
    <a class="btn btn-gold" href="<?= e(url('/register')) ?>">Entrar en el oficio</a>
    <a class="btn" href="<?= e(url('/api/v1/openapi.json')) ?>">Explorar la API</a>
  </div>
</section>
