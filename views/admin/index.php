<?php
/** @var array<string, mixed> $dashboard */
/** @var array<string, int> $statistics */
$metrics = $dashboard['metrics'];
$users = $dashboard['users'];
$artisans = $dashboard['artisans'];
$venues = $dashboard['venues'];
$settings = $dashboard['settings'];
$ingredients = $dashboard['ingredients'];
$extractions = $dashboard['extractions'];
$syrups = $dashboard['syrups'];
$allRoles = ['artesano', 'local', 'comisario', 'moderador', 'admin'];
?>
<div class="page-head">
  <p class="eyebrow">Administración · parámetros y traza</p>
  <h1>El cuadro de mandos</h1>
  <p class="lede">Los umbrales se cambian aquí y cada cambio queda auditado. Los roles son acumulables; nadie puede borrar la traza.</p>
</div>

<div class="stats">
  <div class="stat"><div class="k"><?= (int) $metrics['users'] ?></div><div class="l">Cuentas activas</div><div class="s"><?= (int) $metrics['artisans'] ?> talleres · <?= (int) $metrics['venues'] ?> locales</div></div>
  <div class="stat"><div class="k"><?= (int) $metrics['recipes'] ?></div><div class="l">Recetas públicas</div><div class="s"><?= (int) $metrics['recipe_versions'] ?> versiones</div></div>
  <div class="stat"><div class="k"><?= (int) $metrics['lots'] ?></div><div class="l">Lotes</div><div class="s">Coherencia media <?= num($metrics['avg_coherence'], 1) ?></div></div>
  <div class="stat"><div class="k"><?= money($metrics['gmv']) ?></div><div class="l">Volumen completado</div><div class="s"><?= (int) $metrics['units_sold'] ?> unidades · <?= (int) $metrics['commissions_completed'] ?> comisiones</div></div>
</div>

<div class="stats">
  <div class="stat"><div class="k"><?= (int) $metrics['open_requests'] ?></div><div class="l">Peticiones abiertas</div><div class="s"><?= (int) $metrics['proposals'] ?> propuestas totales</div></div>
  <div class="stat"><div class="k"><?= (int) $metrics['reviews'] ?></div><div class="l">Valoraciones</div><div class="s">Verificadas por comisión</div></div>
  <div class="stat"><div class="k"><?= (int) $metrics['pending_verifications'] ?></div><div class="l">Verificaciones</div><div class="s"><?= (int) $metrics['open_reports'] ?> reportes abiertos</div></div>
  <div class="stat"><div class="k"><?= (int) $metrics['open_consignments'] ?></div><div class="l">Consignaciones</div><div class="s">Abiertas o en disputa</div></div>
</div>

<div class="tabs" data-tabs>
  <button type="button" role="tab" aria-selected="true" aria-controls="tab-settings" id="tabbtn-settings">Parámetros</button>
  <button type="button" role="tab" aria-selected="false" aria-controls="tab-users" id="tabbtn-users">Usuarios y roles</button>
  <button type="button" role="tab" aria-selected="false" aria-controls="tab-catalog" id="tabbtn-catalog">Catálogos</button>
  <button type="button" role="tab" aria-selected="false" aria-controls="tab-profiles" id="tabbtn-profiles">Perfiles</button>
</div>

<section id="tab-settings" role="tabpanel" aria-labelledby="tabbtn-settings">
  <div class="card">
    <div class="card-head">
      <h2>Umbrales y pesos</h2>
      <span class="muted small">Los cuatro pesos de coherencia deben sumar 1,00</span>
    </div>
    <div class="grid grid-2">
      <?php foreach ($settings as $key => $value): ?>
        <form method="post" action="<?= e(url('/admin/settings')) ?>" class="card flat" style="margin:0">
          <?= csrf_field() ?>
          <input type="hidden" name="setting_key" value="<?= e($key) ?>">
          <div class="field">
            <label for="setting-<?= e($key) ?>"><?= e($key) ?></label>
            <input type="text" id="setting-<?= e($key) ?>" name="setting_value" value="<?= e($value) ?>" required>
          </div>
          <button class="btn btn-sm" type="submit">Guardar</button>
        </form>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section id="tab-users" role="tabpanel" aria-labelledby="tabbtn-users" hidden>
  <div class="card">
    <div class="card-head">
      <h2>Cuentas y roles</h2>
      <span class="muted small"><?= count($users) ?> cuentas</span>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Cuenta</th><th>NIF</th><th>Estado</th><th>Roles</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($users as $account): ?>
            <tr>
              <td><?= e($account['display_name']) ?><br><span class="muted small"><?= e($account['email']) ?></span></td>
              <td><?= e(mask_nif($account['nif'])) ?></td>
              <td><span class="badge <?= e(status_class((string) $account['status'])) ?>"><?= e(status_label((string) $account['status'])) ?></span></td>
              <td>
                <form method="post" action="<?= e(url('/admin/users/' . $account['id'] . '/roles')) ?>">
                  <?= csrf_field() ?>
                  <div class="flex" style="gap:10px">
                    <?php foreach ($allRoles as $role): ?>
                      <label class="checkline" style="font-size:0.78rem">
                        <input type="checkbox" name="roles[]" value="<?= e($role) ?>" <?= in_array($role, $account['roles'], true) ? 'checked' : '' ?>>
                        <span><?= e($role) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
              </td>
              <td><button class="btn btn-sm" type="submit">Aplicar</button></form></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<section id="tab-catalog" role="tabpanel" aria-labelledby="tabbtn-catalog" hidden>
  <div class="grid grid-side">
    <div>
      <div class="card">
        <div class="card-head">
          <h2>Ingredientes</h2>
          <span class="muted small"><?= count($ingredients) ?></span>
        </div>
        <div class="table-wrap" style="max-height:520px;overflow:auto">
          <table class="table">
            <thead><tr><th>Nombre</th><th>Categoría</th><th class="num">Brix</th><th class="num">Azúcar</th><th class="num">Agua</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($ingredients as $ingredient): ?>
                <tr>
                  <td><?= e($ingredient['name']) ?><br><span class="muted small"><?= e($ingredient['slug']) ?></span></td>
                  <td><?= e($ingredient['category']) ?></td>
                  <td class="num"><?= $ingredient['brix'] !== null ? num($ingredient['brix'], 1) : '—' ?></td>
                  <td class="num"><?= $ingredient['sugar_g_per_100'] !== null ? num($ingredient['sugar_g_per_100'], 1) : '—' ?></td>
                  <td class="num"><?= $ingredient['water_pct'] !== null ? num($ingredient['water_pct'], 1) : '—' ?></td>
                  <td>
                    <details class="disclosure" style="margin:0;padding:6px 8px">
                      <summary>Editar</summary>
                      <form method="post" action="<?= e(url('/admin/ingredients/' . $ingredient['id'])) ?>">
                        <?= csrf_field() ?>
                        <div class="field"><label>Nombre</label><input type="text" name="name" value="<?= e($ingredient['name']) ?>" required></div>
                        <div class="field"><label>Categoría</label>
                          <select name="category">
                            <?php foreach (['fruta', 'flor', 'especia', 'hierba', 'corteza', 'azucar', 'otro'] as $category): ?>
                              <option value="<?= e($category) ?>" <?= $ingredient['category'] === $category ? 'selected' : '' ?>><?= e($category) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="field"><label>Brix</label><input type="number" step="0.1" name="brix" value="<?= e($ingredient['brix']) ?>"></div>
                        <div class="field"><label>Azúcar g/100</label><input type="number" step="0.1" name="sugar_g_per_100" value="<?= e($ingredient['sugar_g_per_100']) ?>"></div>
                        <div class="field"><label>Agua %</label><input type="number" step="0.1" name="water_pct" value="<?= e($ingredient['water_pct']) ?>"></div>
                        <div class="field"><label>Notas</label><input type="text" name="notes" value="<?= e($ingredient['notes']) ?>"></div>
                        <label class="checkline"><input type="checkbox" name="active" value="1" <?= $ingredient['active'] ? 'checked' : '' ?>> <span>Activo</span></label>
                        <button class="btn btn-sm mt-1" type="submit">Guardar</button>
                      </form>
                    </details>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <aside>
      <form class="card" method="post" action="<?= e(url('/admin/ingredients')) ?>">
        <?= csrf_field() ?>
        <h3>Nuevo ingrediente</h3>
        <div class="field"><label for="name">Nombre</label><input type="text" id="name" name="name" required></div>
        <div class="field"><label for="slug">Slug (opcional)</label><input type="text" id="slug" name="slug"></div>
        <div class="field"><label for="category">Categoría</label>
          <select id="category" name="category">
            <?php foreach (['fruta', 'flor', 'especia', 'hierba', 'corteza', 'azucar', 'otro'] as $category): ?>
              <option value="<?= e($category) ?>"><?= e($category) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label for="brix">Brix</label><input type="number" step="0.1" id="brix" name="brix"></div>
        <div class="field"><label for="sugar_g_per_100">Azúcar g/100</label><input type="number" step="0.1" id="sugar_g_per_100" name="sugar_g_per_100"></div>
        <div class="field"><label for="water_pct">Agua %</label><input type="number" step="0.1" id="water_pct" name="water_pct"></div>
        <div class="field"><label for="abv_pct">ABV % (si aplica)</label><input type="number" step="0.1" id="abv_pct" name="abv_pct"></div>
        <div class="field"><label for="notes">Notas</label><input type="text" id="notes" name="notes"></div>
        <button class="btn btn-gold btn-block" type="submit">Añadir ingrediente</button>
      </form>

      <div class="card">
        <h3>Extracciones</h3>
        <ul class="list">
          <?php foreach ($extractions as $extraction): ?>
            <li><div class="title"><?= e($extraction['name']) ?></div><div class="sub"><?= (int) $extraction['days'] ?> días · pérdida <?= num($extraction['loss_pct'], 1) ?> %</div></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="card">
        <h3>Almíbares</h3>
        <ul class="list">
          <?php foreach ($syrups as $syrup): ?>
            <li><div class="title"><?= e($syrup['name']) ?></div><div class="sub"><?= num($syrup['brix'], 0) ?> °Bx · <?= e($syrup['code']) ?></div></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </aside>
  </div>
</section>

<section id="tab-profiles" role="tabpanel" aria-labelledby="tabbtn-profiles" hidden>
  <div class="grid grid-2">
    <div class="card">
      <h2>Talleres</h2>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Taller</th><th>Territorio</th><th class="num">Recetas</th><th class="num">Lotes</th><th class="num">Coherencia</th></tr></thead>
          <tbody>
            <?php foreach ($artisans as $artisan): ?>
              <tr>
                <td><?= e($artisan['studio_name']) ?><br><span class="muted small"><?= e($artisan['display_name']) ?></span></td>
                <td><?= e($artisan['territory']) ?></td>
                <td class="num"><?= (int) $artisan['public_recipes'] ?></td>
                <td class="num"><?= (int) $artisan['lot_count'] ?></td>
                <td class="num"><?= num($artisan['avg_coherence'], 1) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <h2>Locales</h2>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Local</th><th>Territorio</th><th>Licencia</th><th class="num">Comisiones</th></tr></thead>
          <tbody>
            <?php foreach ($venues as $venue): ?>
              <tr>
                <td><?= e($venue['venue_name']) ?><br><span class="muted small"><?= e($venue['display_name']) ?></span></td>
                <td><?= e($venue['territory']) ?></td>
                <td><span class="badge <?= e(status_class((string) $venue['license_status'])) ?>"><?= e(status_label((string) $venue['license_status'])) ?></span></td>
                <td class="num"><?= (int) $venue['completed_commissions'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>
