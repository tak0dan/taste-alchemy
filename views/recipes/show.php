<?php
/** @var array<string, mixed> $detail */
$recipe = $detail['recipe'];
$computed = $detail['computed'];
$body = $detail['body'];
$author = $detail['author'];
$versions = $detail['versions'];
$lots = $detail['lots'];
$user = auth_user();
$isAuthor = $user !== null && (int) $user['id'] === (int) $recipe['author_id'];
$tags = json_data($recipe['tags_json'] ?? null);
$engine = $body['engine'] ?? [];
?>
<div class="page-head">
  <p class="eyebrow"><?= e($recipe['style']) ?> · versión vigente v<?= (int) ($recipe['version_no'] ?? 1) ?></p>
  <h1><?= e($recipe['title']) ?></h1>
  <div class="meta-line">
    <?php if (!empty($recipe['artisan_slug'])): ?>
      <span>por <a href="<?= e(url('/artisans/' . $recipe['artisan_slug'])) ?>"><b><?= e($recipe['studio_name'] ?: $recipe['author_name']) ?></b></a></span>
    <?php else: ?>
      <span>por <b><?= e($recipe['author_name']) ?></b></span>
    <?php endif; ?>
    <?php if (!empty($recipe['territory'])): ?><span><?= e($recipe['territory']) ?></span><?php endif; ?>
    <span><?= (int) ($recipe['version_count'] ?? count($versions)) ?> versiones</span>
    <span><?= (int) ($recipe['fork_count'] ?? 0) ?> bifurcaciones</span>
    <span>Actualizada <?= e(format_date($recipe['updated_at'])) ?></span>
  </div>
  <?php if ($tags !== []): ?>
    <div class="mt-1"><?php foreach ($tags as $tag): ?><span class="tag"><?= e($tag) ?></span><?php endforeach; ?></div>
  <?php endif; ?>
</div>

<div class="grid grid-side">
  <div>
    <article class="card">
      <h2 class="mt-0">La receta</h2>
      <p class="dim"><?= nl2br(e((string) ($recipe['description'] ?? 'Sin descripción.'))) ?></p>

      <?php if ($computed === null): ?>
        <div class="notice">Esta versión no contiene parámetros ejecutables. El historial se conserva, pero el motor no puede recalcularla.</div>
      <?php else: ?>
        <div class="grid grid-2 mt-2">
          <div class="card flat">
            <p class="eyebrow">Física estimada</p>
            <dl class="kv">
              <dt>Graduación</dt><dd><?= num($computed['abv_pct'], 1) ?> % vol</dd>
              <dt>Brix</dt><dd><?= num($computed['brix'], 1) ?> °Bx</dd>
              <dt>Azúcar</dt><dd><?= num($computed['sugar_gl'], 0) ?> g/L</dd>
              <dt>Volumen final</dt><dd><?= num($computed['volumes']['final_ml'], 0) ?> ml</dd>
              <dt>Punto de congelación</dt><dd><?= num($computed['freeze']['freezing_point_c'], 1) ?> °C</dd>
              <dt>Servicio</dt><dd><?= num($computed['freeze']['serving_temperature_c'], 0) ?> °C</dd>
            </dl>
          </div>
          <div class="card flat">
            <p class="eyebrow">Ingredientes para esta versión</p>
            <dl class="kv">
              <dt>Alcohol base</dt><dd><?= num($computed['ingredients']['vodka_ml'], 0) ?> ml</dd>
              <dt><?= e($computed['preset']['fruit_name']) ?></dt><dd><?= num($computed['ingredients']['fruit_g'], 0) ?> g</dd>
              <dt>Azúcar</dt><dd><?= num($computed['ingredients']['sugar_g'], 0) ?> g</dd>
              <dt>Agua</dt><dd><?= num($computed['ingredients']['water_ml'], 0) ?> ml</dd>
              <dt>Extracción</dt><dd><?= e($computed['extraction']['label']) ?></dd>
              <dt>Almíbar</dt><dd><?= e($computed['syrup']['label']) ?></dd>
            </dl>
          </div>
        </div>

        <div class="flex between mt-2">
          <?= partial('score', ['score' => (int) round((float) ($recipe['avg_coherence'] ?? 0)), 'label' => 'coherencia media de sus lotes']) ?>
          <span class="badge <?= e($computed['freeze']['freezer_safe'] ? 'ok' : 'warn') ?>">
            <?= $computed['freeze']['freezer_safe'] ? 'Aguanta −18 °C' : 'No congelar' ?>
          </span>
        </div>

        <div class="grid grid-2 mt-2">
          <div>
            <p class="eyebrow">Frascos de fruta</p>
            <div class="jars">
              <?php foreach ($computed['jars']['fruit'] as $jar): ?>
                <span class="jar" title="<?= e(num($jar['total_g'], 0)) ?> g"><i style="height:<?= max(4, min(100, (float) $jar['fill_pct'])) ?>%"></i><span><?= num($jar['fill_pct'], 0) ?>%</span></span>
              <?php endforeach; ?>
            </div>
          </div>
          <div>
            <p class="eyebrow">Frascos de alcohol</p>
            <div class="jars">
              <?php foreach ($computed['jars']['vodka'] as $jar): ?>
                <span class="jar" title="<?= e(num($jar['ml'], 0)) ?> ml"><i style="height:<?= max(4, min(100, (float) $jar['fill_pct'])) ?>%"></i><span><?= num($jar['fill_pct'], 0) ?>%</span></span>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <?php if ($computed['warnings'] !== []): ?>
          <div class="notice mt-2">
            <b>Advertencias del motor.</b>
            <ul style="margin:8px 0 0;padding-left:18px">
              <?php foreach ($computed['warnings'] as $warning): ?><li><?= e($warning) ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <details class="disclosure mt-2">
          <summary>Ver cronograma de elaboración (<?= (int) $computed['timeline']['total_days'] ?> días)</summary>
          <div class="timeline">
            <?php foreach ($computed['timeline']['items'] as $item): ?>
              <div class="event">
                <div class="when"><?= e($item['label']) ?> · día <?= (int) $item['at_day'] ?></div>
                <div class="what"><?= e($item['title']) ?></div>
                <div class="why"><?= e($item['body']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </details>
      <?php endif; ?>
    </article>

    <article class="card">
      <div class="card-head">
        <h2>Historial de versiones</h2>
        <span class="muted small">Inmutable: publicar una versión no borra la anterior.</span>
      </div>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>Versión</th><th>Cambio</th><th class="num">ABV</th><th class="num">Brix</th><th class="num">Volumen</th><th>Autor</th><th>Fecha</th></tr>
          </thead>
          <tbody>
            <?php foreach ($versions as $version): ?>
              <tr>
                <td>
                  v<?= (int) $version['version_no'] ?>
                  <?php if ((int) $version['id'] === (int) ($recipe['version_id'] ?? 0)): ?><span class="badge ok">vigente</span><?php endif; ?>
                </td>
                <td><?= e($version['changelog'] ?: '—') ?></td>
                <td class="num"><?= num($version['abv'], 1) ?> %</td>
                <td class="num"><?= num($version['brix'], 1) ?></td>
                <td class="num"><?= num($version['final_volume_ml'], 0) ?> ml</td>
                <td><?= e($version['author_name']) ?></td>
                <td><?= e(format_date($version['published_at'] ?: $version['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </article>

    <section>
      <div class="card-head">
        <h2>Lotes nacidos de esta receta</h2>
        <a class="btn btn-sm btn-ghost" href="<?= e(url('/lots?q=' . urlencode((string) $recipe['title']))) ?>">Buscar en lotes →</a>
      </div>
      <?php if ($lots === []): ?>
        <?= partial('empty', ['title' => 'Sin lotes vinculados', 'message' => 'Cuando un artesano registre una barrica con esta versión, aparecerá aquí.']) ?>
      <?php else: ?>
        <div class="grid grid-2">
          <?php foreach ($lots as $lot): ?>
            <?= partial('lot-card', ['lot' => $lot]) ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <aside>
    <div class="card">
      <h3>Acciones</h3>
      <?php if ($user === null): ?>
        <p class="dim small">Entra como artesano para bifurcar esta receta o añadir una versión.</p>
        <a class="btn btn-block btn-primary" href="<?= e(url('/login?next=/recipes/' . $recipe['slug'])) ?>">Entrar</a>
      <?php elseif ($isAuthor): ?>
        <p class="dim small">Eres autor de esta receta. Cada cambio se publica como versión nueva.</p>
        <a class="btn btn-block" href="#nueva-version">Añadir versión</a>
      <?php elseif (has_role('artesano')): ?>
        <form method="post" action="<?= e(url('/recipes/' . $recipe['id'] . '/fork')) ?>">
          <?= csrf_field() ?>
          <div class="field">
            <label for="fork-title">Título de tu bifurcación</label>
            <input type="text" id="fork-title" name="title" value="<?= e($recipe['title'] . ' · mi versión') ?>">
          </div>
          <button class="btn btn-block btn-primary" type="submit">Bifurcar con crédito</button>
        </form>
      <?php else: ?>
        <p class="dim small">Tu cuenta no es de artesano. Puedes leer y calcular, pero no bifurcar.</p>
      <?php endif; ?>

      <div class="divider"></div>
      <dl class="kv">
        <dt>Autor</dt><dd><?= e($recipe['author_name']) ?></dd>
        <dt>Estilo</dt><dd><?= e($recipe['style']) ?></dd>
        <dt>Licencia de lectura</dt><dd>Pública</dd>
        <dt>Versión vigente</dt><dd>v<?= (int) ($recipe['version_no'] ?? 1) ?></dd>
      </dl>
    </div>

    <?php if ($isAuthor): ?>
      <div class="card" id="nueva-version">
        <h3>Nueva versión</h3>
        <p class="muted small">Los campos no enviados conservan el valor vigente cuando sea posible; el motor recalcula todo.</p>
        <form method="post" action="<?= e(url('/recipes/' . $recipe['id'] . '/versions')) ?>">
          <?= csrf_field() ?>
          <div class="form-row">
            <div class="field">
              <label for="v-vodka">Alcohol (ml)</label>
              <input type="number" step="1" min="0" id="v-vodka" name="vodka_ml" value="<?= e($engine['vodka_ml'] ?? 500) ?>">
            </div>
            <div class="field">
              <label for="v-fruit">Fruta (g)</label>
              <input type="number" step="1" min="0" id="v-fruit" name="ingredient_g" value="<?= e($engine['ingredient_g'] ?? 500) ?>">
            </div>
          </div>
          <div class="form-row">
            <div class="field">
              <label for="v-sugar">Azúcar (g)</label>
              <input type="number" step="1" min="0" id="v-sugar" name="sugar_g" value="<?= e($computed['ingredients']['sugar_g'] ?? 200) ?>">
            </div>
            <div class="field">
              <label for="v-water">Agua (ml)</label>
              <input type="number" step="1" min="0" id="v-water" name="water_ml" value="<?= e($computed['ingredients']['water_ml'] ?? 250) ?>">
            </div>
          </div>
          <div class="field">
            <label for="v-changelog">Qué cambia y por qué</label>
            <input type="text" id="v-changelog" name="changelog" required placeholder="Menos azúcar para dejar hablar al fruto">
          </div>
          <input type="hidden" name="base" value="<?= e($engine['base'] ?? 'cereza') ?>">
          <input type="hidden" name="mode" value="ingredientes">
          <input type="hidden" name="extraction" value="<?= e($engine['extraction'] ?? 'limoncello') ?>">
          <input type="hidden" name="syrup" value="<?= e($engine['syrup'] ?? 'homemade') ?>">
          <input type="hidden" name="rest_days" value="<?= e($engine['rest_days'] ?? 14) ?>">
          <button class="btn btn-block btn-primary" type="submit">Publicar versión</button>
        </form>
      </div>
    <?php endif; ?>

    <div class="card">
      <h3>¿Algo no encaja?</h3>
      <p class="muted small">La denuncia se revisa por un moderador y queda auditada.</p>
      <?php if ($user === null): ?>
        <a class="btn btn-sm btn-ghost btn-block" href="<?= e(url('/login')) ?>">Entrar para denunciar</a>
      <?php else: ?>
        <details class="disclosure">
          <summary>Denunciar esta receta</summary>
          <form method="post" action="<?= e(url('/report')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="entity_type" value="recipe">
            <input type="hidden" name="entity_id" value="<?= (int) $recipe['id'] ?>">
            <input type="hidden" name="return_to" value="/recipes/<?= e($recipe['slug']) ?>">
            <div class="field">
              <label for="reason">Motivo</label>
              <input type="text" id="reason" name="reason" required placeholder="Plagio, datos falsos, contenido…">
            </div>
            <div class="field">
              <label for="details">Detalle</label>
              <textarea id="details" name="details" rows="3"></textarea>
            </div>
            <button class="btn btn-sm btn-danger btn-block" type="submit">Enviar denuncia</button>
          </form>
        </details>
      <?php endif; ?>
    </div>
  </aside>
</div>
