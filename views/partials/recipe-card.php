<?php
/** @var array<string, mixed> $recipe */
$tags = json_data($recipe['tags_json'] ?? null);
$score = (int) round((float) ($recipe['avg_coherence'] ?? 0));
?>
<article class="card recipe-card">
  <div class="flex between">
    <span class="style"><?= e($recipe['style']) ?></span>
    <?php if ($score > 0): ?>
      <?= partial('score', ['score' => $score, 'label' => 'media']) ?>
    <?php endif; ?>
  </div>
  <h3><a href="<?= e(url('/recipes/' . $recipe['slug'])) ?>"><?= e($recipe['title']) ?></a></h3>
  <div class="meta-line">
    <span><b><?= e($recipe['studio_name'] ?: $recipe['author_name']) ?></b></span>
    <?php if (!empty($recipe['territory'])): ?><span><?= e($recipe['territory']) ?></span><?php endif; ?>
  </div>
  <p class="dim small"><?= e(mb_strimwidth((string) ($recipe['description'] ?? ''), 0, 150, '…')) ?></p>
  <div class="spacer"></div>
  <?php if ($tags !== []): ?>
    <div><?php foreach (array_slice($tags, 0, 4) as $tag): ?><span class="tag"><?= e($tag) ?></span><?php endforeach; ?></div>
  <?php endif; ?>
  <div class="meta-line">
    <span><b><?= num($recipe['abv'], 1) ?> %</b> vol</span>
    <span><b><?= num($recipe['brix'], 1) ?></b> °Bx</span>
    <span><b>v<?= (int) ($recipe['version_no'] ?? 1) ?></b> · <?= (int) ($recipe['version_count'] ?? 1) ?> versiones</span>
    <span><b><?= (int) ($recipe['fork_count'] ?? 0) ?></b> bifurcaciones</span>
  </div>
  <div class="bottom">
    <span class="muted small"><?= (int) ($recipe['lot_count'] ?? 0) ?> lotes vinculados</span>
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/recipes/' . $recipe['slug'])) ?>">Abrir receta</a>
  </div>
</article>
