<?php
/** @var array<string, mixed> $account */
/** @var array<string, mixed>|null $artisan */
/** @var array<string, mixed>|null $venue */
$user = auth_user();
?>
<div class="page-head">
  <p class="eyebrow">Cuenta</p>
  <h1><?= e($account['display_name']) ?></h1>
  <div class="meta-line">
    <span><?= e($account['email']) ?></span>
    <span>NIF <?= e(mask_nif($account['nif'])) ?></span>
    <span>Alta <?= e(format_date($account['created_at'])) ?></span>
    <span>Roles: <b><?= e(implode(' · ', $account['roles'])) ?></b></span>
  </div>
</div>

<div class="grid grid-side">
  <div>
    <form class="card" method="post" action="<?= e(url('/account')) ?>">
      <?= csrf_field() ?>
      <h2>Datos personales</h2>
      <div class="form-row">
        <div class="field">
          <label for="display_name">Nombre</label>
          <input type="text" id="display_name" name="display_name" required value="<?= e(old('display_name', $account['display_name'])) ?>">
        </div>
        <div class="field">
          <label for="birth_date">Fecha de nacimiento</label>
          <input type="date" id="birth_date" name="birth_date" value="<?= e(old('birth_date', $account['birth_date'])) ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="field">
          <label for="nif">NIF / NIE / CIF</label>
          <input type="text" id="nif" name="nif" value="<?= e(old('nif', $account['nif'])) ?>">
        </div>
        <div class="field">
          <label for="phone">Teléfono</label>
          <input type="tel" id="phone" name="phone" value="<?= e(old('phone', $account['phone'])) ?>">
        </div>
      </div>

      <?php if ($artisan !== null): ?>
        <div class="divider"></div>
        <h3>Perfil de taller</h3>
        <div class="form-row">
          <div class="field">
            <label for="studio_name">Nombre del taller</label>
            <input type="text" id="studio_name" name="studio_name" value="<?= e(old('studio_name', $artisan['studio_name'])) ?>">
          </div>
          <div class="field">
            <label for="territory">Territorio</label>
            <input type="text" id="territory" name="territory" value="<?= e(old('territory', $artisan['territory'])) ?>">
          </div>
        </div>
        <div class="form-row">
          <div class="field">
            <label for="founded_year">Año de fundación</label>
            <input type="number" id="founded_year" name="founded_year" min="1800" max="<?= date('Y') ?>" value="<?= e(old('founded_year', $artisan['founded_year'])) ?>">
          </div>
          <div class="field">
            <label for="website">Sitio web</label>
            <input type="url" id="website" name="website" value="<?= e(old('website', $artisan['website'])) ?>">
          </div>
        </div>
        <div class="field">
          <label for="bio">Biografía</label>
          <textarea id="bio" name="bio" rows="4"><?= e(old('bio', $artisan['bio'])) ?></textarea>
        </div>
        <p class="muted small">Tu taller público: <a href="<?= e(url('/artisans/' . $artisan['slug'])) ?>"><?= e(url('/artisans/' . $artisan['slug'])) ?></a></p>
      <?php endif; ?>

      <?php if ($venue !== null): ?>
        <div class="divider"></div>
        <h3>Perfil de local</h3>
        <div class="form-row">
          <div class="field">
            <label for="venue_name">Nombre del local</label>
            <input type="text" id="venue_name" name="venue_name" value="<?= e(old('venue_name', $venue['venue_name'])) ?>">
          </div>
          <div class="field">
            <label for="territory">Territorio</label>
            <input type="text" id="territory" name="territory" value="<?= e(old('territory', $venue['territory'])) ?>">
          </div>
        </div>
        <div class="field">
          <label for="address">Dirección</label>
          <input type="text" id="address" name="address" value="<?= e(old('address', $venue['address'])) ?>">
        </div>
        <div class="form-row">
          <div class="field">
            <label for="license_number">Licencia</label>
            <input type="text" id="license_number" name="license_number" value="<?= e(old('license_number', $venue['license_number'])) ?>">
          </div>
          <div class="field">
            <label for="license_type">Tipo</label>
            <input type="text" id="license_type" name="license_type" value="<?= e(old('license_type', $venue['license_type'])) ?>">
          </div>
        </div>
        <p class="muted small">Estado de licencia: <span class="badge <?= e(status_class((string) $venue['license_status'])) ?>"><?= e(status_label((string) $venue['license_status'])) ?></span></p>
      <?php endif; ?>

      <div class="actions">
        <button class="btn btn-primary" type="submit">Guardar cambios</button>
      </div>
    </form>

    <form class="card" method="post" action="<?= e(url('/account/password')) ?>">
      <?= csrf_field() ?>
      <h2>Contraseña</h2>
      <div class="form-row">
        <div class="field">
          <label for="current_password">Contraseña actual</label>
          <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
        </div>
        <div class="field">
          <label for="password">Contraseña nueva</label>
          <input type="password" id="password" name="password" required autocomplete="new-password" minlength="8">
          <span class="hint">Al menos 8 caracteres, con letras y números.</span>
        </div>
      </div>
      <button class="btn" type="submit">Cambiar contraseña</button>
    </form>
  </div>

  <aside>
    <div class="card">
      <h3>Tus roles</h3>
      <div class="flex" style="margin-bottom:12px">
        <?php foreach ($account['roles'] as $role): ?>
          <span class="chip"><b><?= e($role) ?></b></span>
        <?php endforeach; ?>
      </div>
      <p class="muted small">Los roles son acumulables y sólo un administrador puede cambiarlos. Cada cambio queda auditado.</p>
      <div class="divider"></div>
      <dl class="kv">
        <dt>Último acceso</dt><dd><?= e(format_date($account['last_login_at'], 'd/m/Y H:i')) ?></dd>
        <dt>Estado</dt><dd><?= e(status_label((string) $account['status'])) ?></dd>
      </dl>
    </div>

    <div class="card">
      <h3>Tus datos</h3>
      <p class="muted small">Puedes descargar una copia completa de tu información en JSON, o solicitar la anonimización de la cuenta.</p>
      <a class="btn btn-sm btn-ghost btn-block" href="<?= e(url('/account/export')) ?>">Exportar mis datos</a>
      <form class="mt-1" method="post" action="<?= e(url('/account/delete')) ?>" data-confirm="¿Anonimizar tu cuenta? Esta acción es irreversible. Las facturas y la auditoría se conservarán.">
        <?= csrf_field() ?>
        <button class="btn btn-sm btn-danger btn-block" type="submit">Anonimizar cuenta</button>
      </form>
    </div>
  </aside>
</div>
