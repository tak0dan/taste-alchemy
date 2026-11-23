<?php
/** @var array<int, array<string, mixed>> $lots */
/** @var array<int, array<string, mixed>> $recent */
/** @var array<string, mixed> $stats */
?>
<div class="page-head">
  <p class="eyebrow">Comisario · mesa de cata</p>
  <h1>Catas ponderadas, no reseñas</h1>
  <p class="lede">Sólo los lotes que ya superan 70 de coherencia pueden recibir una cata destacada. Tu firma acompaña la puntuación.</p>
  <div class="actions">
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/lots')) ?>">Ver todos los lotes</a>
  </div>
</div>

<div class="stats">
  <div class="stat"><div class="k"><?= count($lots) ?></div><div class="l">Lotes elegibles</div><div class="s">Coherencia ≥ 70</div></div>
  <div class="stat"><div class="k"><?= num($stats['avg_coherence'], 1) ?></div><div class="l">Coherencia media</div><div class="s">Sobre lotes con veredicto</div></div>
  <div class="stat"><div class="k"><?= (int) $stats['high_trust'] ?></div><div class="l">Lotes sólidos</div><div class="s">Coherencia ≥ 85</div></div>
  <div class="stat"><div class="k"><?= count($recent) ?></div><div class="l">Catas recientes</div><div class="s">De locales verificados</div></div>
</div>

<div class="grid grid-side">
  <div>
    <section>
      <div class="card-head">
        <h2>Lotes elegibles para cata</h2>
        <span class="muted small">Ordenados por coherencia</span>
      </div>
      <?php if ($lots === []): ?>
        <?= partial('empty', ['title' => 'Sin lotes elegibles', 'message' => 'Ningún lote supera aún el umbral de 70 de coherencia.']) ?>
      <?php else: ?>
        <div class="grid grid-2">
          <?php foreach ($lots as $lot): ?>
            <article class="card lot-card">
              <div class="flex between">
                <span class="style"><?= e($lot['code']) ?></span>
                <?= partial('score', ['score' => (int) $lot['coherence_score']]) ?>
              </div>
              <h3><a href="<?= e(url('/curator/lots/' . $lot['id'])) ?>"><?= e($lot['recipe_title']) ?></a></h3>
              <div class="meta-line">
                <span><b><?= e($lot['studio_name'] ?: $lot['artisan_name']) ?></b></span>
                <span><?= e($lot['territory']) ?></span>
              </div>
              <div class="spacer"></div>
              <div class="meta-line">
                <span><b><?= num($lot['initial_abv'], 1) ?> %</b> vol</span>
                <span><b><?= (int) $lot['reading_count'] ?></b> lecturas</span>
                <span><b><?= (int) $lot['units_available'] ?></b> libres</span>
              </div>
              <div class="bottom">
                <span class="muted small"><?= e(status_label((string) $lot['status'])) ?></span>
                <a class="btn btn-sm btn-ghost" href="<?= e(url('/curator/lots/' . $lot['id'])) ?>">Abrir cata</a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <aside>
    <div class="card">
      <h3>Voces recientes de locales</h3>
      <?php if ($recent === []): ?>
        <p class="muted small">Aún no hay valoraciones de locales.</p>
      <?php else: ?>
        <?php foreach ($recent as $review): ?>
          <div class="review">
            <div class="who">
              <span class="avatar" aria-hidden="true"><?= e(initials((string) ($review['venue_name'] ?: $review['author_name']))) ?></span>
              <div><b><?= e($review['venue_name'] ?: $review['author_name']) ?></b><span><?= e($review['recipe_title']) ?></span></div>
              <?php if ($review['score_total'] !== null): ?><span class="badge ok right"><?= num($review['score_total'], 1) ?>/10</span><?php endif; ?>
            </div>
            <blockquote>«<?= e(mb_strimwidth((string) $review['body'], 0, 160, '…')) ?>»</blockquote>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </aside>
</div>
