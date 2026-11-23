<?php
/** @var string $kind */
$isVenue = $kind === 'local';
?>
<div class="page-head">
  <p class="eyebrow">Abrir cuenta · <?= $isVenue ? 'local' : 'taller' ?></p>
  <h1><?= $isVenue ? 'Un local que pide con criterio' : 'Un taller que demuestra su oficio' ?></h1>
  <p class="lede">La cuenta es gratuita y verificable. Los datos de identidad se usan para facturas entre NIFs y para la moderación; nunca se publican completos.</p>
</div>

<div class="tabs" role="tablist" aria-label="Tipo de cuenta">
  <a class="<?= !$isVenue ? 'active' : '' ?>" href="<?= e(url('/register?kind=artesano')) ?>" role="tab" aria-selected="<?= !$isVenue ? 'true' : 'false' ?>">Soy artesano</a>
  <a class="<?= $isVenue ? 'active' : '' ?>" href="<?= e(url('/register?kind=local')) ?>" role="tab" aria-selected="<?= $isVenue ? 'true' : 'false' ?>">Soy local</a>
</div>

<form method="post" action="<?= e(url('/register')) ?>" class="grid grid-side">
  <?= csrf_field() ?>
  <input type="hidden" name="kind" value="<?= e($kind) ?>">

  <div>
    <div class="card">
      <h2>Identidad</h2>
      <div class="form-row">
        <div class="field">
          <label for="display_name">Nombre y apellidos</label>
          <input type="text" id="display_name" name="display_name" required value="<?= e(old('display_name')) ?>" autocomplete="name">
        </div>
        <div class="field">
          <label for="email">Correo</label>
          <input type="email" id="email" name="email" required value="<?= e(old('email')) ?>" autocomplete="email">
        </div>
      </div>
      <div class="form-row">
        <div class="field">
          <label for="birth_date">Fecha de nacimiento</label>
          <input type="date" id="birth_date" name="birth_date" required value="<?= e(old('birth_date')) ?>" autocomplete="bday">
          <span class="hint">Debes ser mayor de 18 años.</span>
        </div>
        <div class="field">
          <label for="nif">NIF / NIE / CIF</label>
          <input type="text" id="nif" name="nif" required value="<?= e(old('nif')) ?>" placeholder="12345678Z" autocomplete="off">
          <span class="hint">Se valida el dígito de control y se guarda enmascarado en las vistas públicas.</span>
        </div>
      </div>
      <div class="field">
        <label for="phone">Teléfono (opcional)</label>
        <input type="tel" id="phone" name="phone" value="<?= e(old('phone')) ?>" autocomplete="tel">
      </div>
      <div class="form-row">
        <div class="field">
          <label for="password">Contraseña</label>
          <input type="password" id="password" name="password" required autocomplete="new-password" minlength="8">
          <span class="hint">Al menos 8 caracteres, con letras y números.</span>
        </div>
        <div class="field">
          <label for="password_confirmation">Repite la contraseña</label>
          <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
        </div>
      </div>
    </div>

    <?php if ($isVenue): ?>
      <div class="card">
        <h2>El local</h2>
        <div class="form-row">
          <div class="field">
            <label for="venue_name">Nombre del local</label>
            <input type="text" id="venue_name" name="venue_name" required value="<?= e(old('venue_name')) ?>" placeholder="Bar La Barrica">
          </div>
          <div class="field">
            <label for="territory">Territorio</label>
            <input type="text" id="territory" name="territory" required value="<?= e(old('territory', 'Comunitat Valenciana')) ?>">
          </div>
        </div>
        <div class="form-row">
          <div class="field">
            <label for="license_number">Número de licencia</label>
            <input type="text" id="license_number" name="license_number" required value="<?= e(old('license_number')) ?>">
          </div>
          <div class="field">
            <label for="license_type">Tipo de licencia</label>
            <input type="text" id="license_type" name="license_type" value="<?= e(old('license_type', 'Licencia de restauración')) ?>">
          </div>
        </div>
        <div class="field">
          <label for="address">Dirección</label>
          <input type="text" id="address" name="address" value="<?= e(old('address')) ?>" autocomplete="street-address">
        </div>
        <div class="field">
          <label for="document_ref">Referencia del documento</label>
          <input type="text" id="document_ref" name="document_ref" value="<?= e(old('document_ref')) ?>" placeholder="Expediente, registro o URL privada">
          <span class="hint">La plataforma no verifica documentos automáticamente: un moderador los revisa a mano.</span>
        </div>
      </div>
    <?php else: ?>
      <div class="card">
        <h2>El taller</h2>
        <div class="form-row">
          <div class="field">
            <label for="studio_name">Nombre del taller</label>
            <input type="text" id="studio_name" name="studio_name" required value="<?= e(old('studio_name')) ?>" placeholder="Destilería La Cereza">
          </div>
          <div class="field">
            <label for="territory">Territorio</label>
            <input type="text" id="territory" name="territory" required value="<?= e(old('territory', 'Comunitat Valenciana')) ?>">
          </div>
        </div>
        <div class="form-row">
          <div class="field">
            <label for="founded_year">Año de fundación (opcional)</label>
            <input type="number" id="founded_year" name="founded_year" min="1800" max="<?= date('Y') ?>" value="<?= e(old('founded_year')) ?>">
          </div>
          <div class="field">
            <label for="website">Sitio web (opcional)</label>
            <input type="url" id="website" name="website" value="<?= e(old('website')) ?>" placeholder="https://">
          </div>
        </div>
        <div class="field">
          <label for="bio">Biografía breve</label>
          <textarea id="bio" name="bio" rows="4" placeholder="Qué frutas trabajas, qué métodos, qué buscas."><?= e(old('bio')) ?></textarea>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <aside>
    <div class="card">
      <h3>Qué aceptas</h3>
      <ul class="list">
        <li><span class="title">Mayoría de edad</span><div class="sub">La fecha de nacimiento es obligatoria y se valida.</div></li>
        <li><span class="title">Datos veraces</span><div class="sub">El NIF se comprueba y se usa para facturas y moderación.</div></li>
        <li><span class="title">Sin pago en plataforma</span><div class="sub">El cobro se acuerda entre las partes; aquí sólo se documenta.</div></li>
        <li><span class="title">Traza de auditoría</span><div class="sub">Las decisiones de moderación quedan registradas.</div></li>
      </ul>
      <div class="notice">
        <b><?= $isVenue ? 'Tu licencia empieza pendiente.' : 'Tu taller se abre al instante.' ?></b>
        <?= $isVenue
            ? ' Podrás preparar tu perfil, pero no abrir peticiones hasta que un moderador verifique la licencia.'
            : ' Publica una receta y registra tu primer lote con su lectura física.' ?>
      </div>
      <button class="btn btn-gold btn-block" type="submit">Abrir cuenta</button>
      <p class="muted small mt-2" style="text-align:center">¿Ya tienes cuenta? <a href="<?= e(url('/login')) ?>">Entrar</a>.</p>
    </div>
  </aside>
</form>
