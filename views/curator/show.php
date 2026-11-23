<?php
/** @var array<string, mixed> $detail */
$lot = $detail['lot'];
$readings = $detail['readings'];
$breakdown = $detail['breakdown'];
$checks = $breakdown['checks'] ?? [];
$reviews = $detail['reviews'];
?>
<div class="page-head">
  <div class="lot-hero">
    <?= partial('score', ['score' => (int) $lot['coherence_score'], 'size' => 'lg']) ?>
    <div class="ident">
      <p class="sub"><?= e($lot['code']) ?> · <?= e(status_label((string) $lot['status'])) ?></p>
      <h1><?= e($lot['recipe_title']) ?></h1>
      <div class="meta-line">
        <span><b><?= e($lot['studio_name'] ?: $lot['artisan_name']) ?></b></span>
        <span><?= e($lot['territory']) ?></span>
        <span>Iniciado <?= e(format_date($lot['started_at'])) ?></span>
        <span><?= (int) $lot['reading_count'] ?> lecturas</span>
      </div>
    </div>
  </div>
</div>

<div class="grid grid-side">
  <div>
    <div class="card">
      <h2>Física del lote</h2>
      <div class="phys">
        <div class="p"><div class="k">Volumen inicial</div><div class="v"><?= num($lot['initial_volume_l'], 1) ?> L</div></div>
        <div class="p"><div class="k">Volumen actual</div><div class="v"><?= num($lot['current_volume_l'], 1) ?> L</div></div>
        <div class="p"><div class="k">ABV inicial</div><div class="v"><?= num($lot['initial_abv'], 1) ?> %</div></div>
        <div class="p"><div class="k">ABV declarado</div><div class="v"><?= num($lot['declared_abv'], 1) ?> %</div></div>
      </div>
      <?php if ($checks !== []): ?>
        <div class="breakdown mt-2">
          <?php foreach ($checks as $check): ?>
            <div class="item">
              <span class="name"><?= e($check['label']) ?></span>
              <span class="val"><?= (int) $check['score'] ?>/100 · peso <?= num($check['weight'] * 100, 0) ?> %</span>
              <span class="meter"><i class="<?= e($check['status']) ?>" style="width:<?= max(0, min(100, (int) $check['score'])) ?>%"></i></span>
              <span class="why"><?= e($check['explanation']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Lecturas</h2>
      <?php if ($readings === []): ?>
        <p class="muted">Sin lecturas.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Fecha</th><th class="num">Volumen</th><th class="num">ABV</th><th class="num">Temperatura</th></tr></thead>
            <tbody>
              <?php foreach ($readings as $reading): ?>
                <tr>
                  <td><?= e(format_date($reading['taken_at'])) ?></td>
                  <td class="num"><?= num($reading['volume_l'], 2) ?> L</td>
                  <td class="num"><?= num($reading['abv'], 2) ?> %</td>
                  <td class="num"><?= $reading['temperature_c'] !== null ? num($reading['temperature_c'], 1) . ' °C' : '—' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Catas publicadas</h2>
      <?php if ($reviews === []): ?>
        <p class="muted">Sin catas todavía.</p>
      <?php else: ?>
        <?php foreach ($reviews as $review): ?>
          <div class="review">
            <div class="who">
              <span class="avatar" aria-hidden="true"><?= e(initials((string) ($review['venue_name'] ?: $review['author_name']))) ?></span>
              <div>
                <b><?= e($review['venue_name'] ?: $review['author_name']) ?></b>
                <span><?= $review['kind'] === 'comisario' ? 'Comisario' : 'Local verificado' ?> · <?= e(format_date($review['published_at'] ?: $review['created_at'])) ?></span>
              </div>
              <?php if ($review['score_total'] !== null): ?><span class="badge ok right"><?= num($review['score_total'], 1) ?>/10</span><?php endif; ?>
            </div>
            <blockquote>«<?= e($review['body']) ?>»</blockquote>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <aside>
    <form class="card" method="post" action="<?= e(url('/curator/lots/' . $lot['id'] . '/tasting')) ?>">
      <?= csrf_field() ?>
      <h3>Publicar cata de comisario</h3>
      <p class="muted small">La cata se vincula al lote y a tu firma. No sustituye a la valoración del local.</p>
      <div class="form-row">
        <?php foreach (['aroma' => 'Aroma', 'texture' => 'Textura', 'balance' => 'Equilibrio', 'finish' => 'Final'] as $key => $label): ?>
          <div class="field">
            <label for="score_<?= e($key) ?>"><?= e($label) ?></label>
            <input type="number" step="1" min="1" max="10" id="score_<?= e($key) ?>" name="score_<?= e($key) ?>">
          </div>
        <?php endforeach; ?>
      </div>
      <div class="field">
        <label for="body">Nota de cata</label>
        <textarea id="body" name="body" rows="5" required minlength="30" placeholder="Aroma, textura, equilibrio, final. Al menos 30 caracteres."></textarea>
      </div>
      <button class="btn btn-gold btn-block" type="submit">Firmar cata</button>
    </form>

    <div class="card">
      <h3>Ficha</h3>
      <dl class="kv">
        <dt>Lote</dt><dd><?= e($lot['code']) ?></dd>
        <dt>Receta</dt><dd><?= e($lot['recipe_title']) ?></dd>
        <dt>Versión</dt><dd>v<?= (int) $lot['version_no'] ?></dd>
        <dt>Formato</dt><dd><?= (int) $lot['unit_ml'] ?> ml</dd>
        <dt>Unidades libres</dt><dd><?= (int) $lot['units_available'] ?></dd>
      </dl>
      <a class="btn btn-sm btn-ghost btn-block mt-1" href="<?= e(url('/lots/' . $lot['id'])) ?>">Ver ficha pública</a>
    </div>
  </aside>
</div>
