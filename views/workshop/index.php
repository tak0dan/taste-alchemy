<?php
/** @var array<string, mixed>|null $profile */
/** @var array<int, array<string, mixed>> $recipes */
/** @var array<int, array<string, mixed>> $lots */
/** @var array<int, array<string, mixed>> $listings */
/** @var array<int, array<string, mixed>> $commissions */
/** @var int $pendingProposals */
/** @var array<int, array<string, mixed>> $notifications */
/** @var int $openRequests */
/** @var array<string, array<string, mixed>> $stats */
$user = auth_user();
$avgCoherence = 0.0;
if ($lots !== []) {
    $avgCoherence = array_sum(array_map(static fn(array $lot): float => (float) $lot['coherence_score'], $lots)) / count($lots);
}
?>
<div class="page-head">
  <p class="eyebrow">Taller · <?= e($profile['territory'] ?? 'sin territorio') ?></p>
  <h1><?= e($profile['studio_name'] ?? $user['display_name']) ?></h1>
  <p class="lede">Cada receta publicada es una petición potencial. Cada lote es una promesa con lecturas.</p>
  <div class="actions">
    <a class="btn btn-gold" href="<?= e(url('/workshop/recipes/new')) ?>">Publicar receta</a>
    <a class="btn" href="<?= e(url('/workshop/lots/new')) ?>">Registrar lote</a>
    <a class="btn btn-ghost" href="<?= e(url('/workshop/opportunities')) ?>">Peticiones abiertas<?= $openRequests > 0 ? ' · ' . $openRequests : '' ?></a>
    <?php if ($profile !== null): ?>
      <a class="btn btn-ghost" href="<?= e(url('/artisans/' . $profile['slug'])) ?>">Ver perfil público</a>
    <?php endif; ?>
  </div>
</div>

<div class="stats">
  <div class="stat">
    <div class="k"><?= count($recipes) ?></div>
    <div class="l">Recetas propias</div>
    <div class="s"><?= (int) ($stats['recipes']['versions'] ?? 0) ?> versiones en total</div>
  </div>
  <div class="stat">
    <div class="k"><?= count($lots) ?></div>
    <div class="l">Lotes registrados</div>
    <div class="s">Coherencia media <?= num($avgCoherence, 1) ?>/100</div>
  </div>
  <div class="stat">
    <div class="k"><?= $pendingProposals ?></div>
    <div class="l">Propuestas vivas</div>
    <div class="s"><?= count($commissions) ?> comisiones recientes</div>
  </div>
  <div class="stat">
    <div class="k"><?= count($listings) ?></div>
    <div class="l">Anuncios</div>
    <div class="s">Sólo lotes por encima del umbral</div>
  </div>
</div>

<div class="grid grid-side">
  <div>
    <section class="card">
      <div class="card-head">
        <h2>Tus recetas</h2>
        <a class="btn btn-sm btn-ghost" href="<?= e(url('/workshop/recipes/new')) ?>">Nueva receta</a>
      </div>
      <?php if ($recipes === []): ?>
        <?= partial('empty', [
            'title' => 'Aún no hay recetas',
            'message' => 'La primera receta abre el taller. Se calcula al publicar y su versión inicial es inmutable.',
            'actionUrl' => url('/workshop/recipes/new'),
            'actionLabel' => 'Publicar la primera',
        ]) ?>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Receta</th><th>Estilo</th><th class="num">ABV</th><th class="num">Versiones</th><th>Visibilidad</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($recipes as $recipe): ?>
                <tr>
                  <td><a href="<?= e(url('/recipes/' . $recipe['slug'])) ?>"><?= e($recipe['title']) ?></a><br><span class="muted small"><?= e(format_date($recipe['updated_at'])) ?></span></td>
                  <td><?= e($recipe['style']) ?></td>
                  <td class="num"><?= num($recipe['abv'], 1) ?> %</td>
                  <td class="num"><?= (int) $recipe['version_count'] ?></td>
                  <td><span class="badge <?= $recipe['visibility'] === 'publica' ? 'ok' : 'neutral' ?>"><?= e(status_label((string) $recipe['visibility'])) ?></span></td>
                  <td><a class="btn btn-sm btn-ghost" href="<?= e(url('/recipes/' . $recipe['slug'])) ?>">Abrir</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head">
        <h2>Tus lotes</h2>
        <a class="btn btn-sm btn-ghost" href="<?= e(url('/workshop/lots/new')) ?>">Registrar lote</a>
      </div>
      <?php if ($lots === []): ?>
        <?= partial('empty', [
            'title' => 'Sin lotes',
            'message' => 'Un lote necesita una primera lectura física. Sin evidencia no hay coherencia.',
            'actionUrl' => url('/workshop/lots/new'),
            'actionLabel' => 'Registrar el primero',
        ]) ?>
      <?php else: ?>
        <div class="grid grid-2">
          <?php foreach ($lots as $lot): ?>
            <?= partial('lot-card', ['lot' => $lot]) ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head">
        <h2>Comisiones recientes</h2>
        <a class="btn btn-sm btn-ghost" href="<?= e(url('/workshop/commissions')) ?>">Ver todas →</a>
      </div>
      <?php if ($commissions === []): ?>
        <p class="muted">Aún no has cerrado ninguna comisión. Propón un lote a una petición abierta.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Comisión</th><th>Local</th><th class="num">Unidades</th><th class="num">Importe</th><th>Estado</th></tr></thead>
            <tbody>
              <?php foreach ($commissions as $commission): ?>
                <tr>
                  <td><a href="<?= e(url('/commissions/' . $commission['id'])) ?>"><?= e($commission['lot_code']) ?></a><br><span class="muted small"><?= e($commission['recipe_title']) ?></span></td>
                  <td><?= e($commission['venue_name']) ?></td>
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
      <h3>Anuncios publicados</h3>
      <?php if ($listings === []): ?>
        <p class="muted small">Ningún lote está anunciado todavía. Publica uno desde su ficha.</p>
      <?php else: ?>
        <ul class="list">
          <?php foreach ($listings as $listing): ?>
            <li>
              <div class="flex between">
                <span class="title"><?= e($listing['lot_code']) ?></span>
                <span class="badge <?= e(status_class((string) $listing['status'])) ?>"><?= e(status_label((string) $listing['status'])) ?></span>
              </div>
              <div class="sub"><?= (int) $listing['units_available'] ?> unidades · <?= money($listing['price_per_unit']) ?> · coherencia <?= (int) $listing['coherence_score'] ?></div>
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
