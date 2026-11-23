<?php
/** @var array<int, array<string, mixed>> $pending */
/** @var array<int, array<string, mixed>> $written */
?>
<div class="page-head">
  <p class="eyebrow">Local · valoraciones</p>
  <h1>La cata que espera 72 horas</h1>
  <p class="lede">Sólo puedes valorar una comisión real, y sólo después de 72 horas. La espera separa el entusiasmo de la memoria.</p>
  <div class="actions">
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/bar')) ?>">Volver al bar</a>
  </div>
</div>

<div class="grid grid-side">
  <div>
    <section class="card">
      <div class="card-head">
        <h2>Pendientes de valorar</h2>
        <span class="muted small"><?= count($pending) ?></span>
      </div>
      <?php if ($pending === []): ?>
        <p class="muted">No hay comisiones completadas esperando cata. Si acabas de cerrar una, la espera es de 72 horas.</p>
      <?php else: ?>
        <div class="grid grid-2">
          <?php foreach ($pending as $commission): ?>
            <div class="card flat" style="margin:0">
              <div class="flex between">
                <span class="style"><?= e($commission['lot_code']) ?></span>
                <span class="badge ok"><?= e(format_date($commission['completed_at'], 'd/m/Y')) ?></span>
              </div>
              <h3 class="mt-1"><?= e($commission['recipe_title']) ?></h3>
              <div class="muted small"><?= e($commission['studio_name'] ?? 'Taller') ?> · <?= (int) $commission['units'] ?> unidades</div>
              <a class="btn btn-sm btn-primary mt-2" href="<?= e(url('/bar/reviews/' . $commission['id'])) ?>">Escribir la cata</a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head">
        <h2>Valoraciones publicadas</h2>
        <span class="muted small"><?= count($written) ?></span>
      </div>
      <?php if ($written === []): ?>
        <p class="muted">Aún no has publicado ninguna cata.</p>
      <?php else: ?>
        <?php foreach ($written as $review): ?>
          <div class="review">
            <div class="who">
              <span class="avatar" aria-hidden="true"><?= e(initials((string) $review['recipe_title'])) ?></span>
              <div>
                <b><?= e($review['recipe_title']) ?></b>
                <span>Lote <?= e($review['lot_code']) ?> · <?= e(format_date($review['published_at'] ?: $review['created_at'])) ?></span>
              </div>
              <?php if ($review['score_total'] !== null): ?><span class="badge ok right"><?= num($review['score_total'], 1) ?>/10</span><?php endif; ?>
            </div>
            <blockquote>«<?= e($review['body']) ?>»</blockquote>
            <div class="scores">
              <?php foreach (['aroma' => 'Aroma', 'texture' => 'Textura', 'balance' => 'Equilibrio', 'finish' => 'Final'] as $key => $label): ?>
                <?php if ($review['score_' . $key] !== null): ?><span class="s"><?= $label ?> <?= (int) $review['score_' . $key] ?>/10</span><?php endif; ?>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
  </div>

  <aside>
    <div class="card">
      <h3>Por qué esperar</h3>
      <p class="muted small">Una valoración inmediata mide la expectativa. Una valoración a las 72 horas mide el recuerdo. La plataforma elige el recuerdo.</p>
      <div class="divider"></div>
      <ul class="list">
        <li><span class="title">Sólo la contraparte</span><div class="sub">Nadie más puede valorar la comisión.</div></li>
        <li><span class="title">Una vez</span><div class="sub">No se puede reescribir la cata.</div></li>
        <li><span class="title">Firmada</span><div class="sub">Tu nombre de local acompaña la puntuación.</div></li>
      </ul>
    </div>
  </aside>
</div>
