<?php
/** @var array<string, mixed>|null $profile */
/** @var array<string, mixed>|null $latest */
$status = $profile['license_status'] ?? 'pendiente';
$pending = $latest !== null && $latest['status'] === 'pendiente';
?>
<div class="page-head">
  <p class="eyebrow">Local · verificación</p>
  <h1>La licencia se comprueba a mano</h1>
  <p class="lede">No hay verificación automática de documentos. Un moderador revisa el NIF y la licencia; la decisión queda auditada.</p>
  <div class="actions">
    <a class="btn btn-sm btn-ghost" href="<?= e(url('/bar')) ?>">Volver al bar</a>
  </div>
</div>

<div class="grid grid-side">
  <div>
    <div class="card">
      <div class="card-head">
        <h2>Estado actual</h2>
        <span class="badge <?= e(status_class((string) $status)) ?>"><?= e(status_label((string) $status)) ?></span>
      </div>
      <dl class="kv">
        <dt>Local</dt><dd><?= e($profile['venue_name'] ?? '—') ?></dd>
        <dt>NIF registrado</dt><dd><?= e(mask_nif($profile['nif'] ?? null)) ?></dd>
        <dt>Licencia</dt><dd><?= e($profile['license_number'] ?? '—') ?></dd>
        <dt>Tipo</dt><dd><?= e($profile['license_type'] ?? '—') ?></dd>
        <dt>Territorio</dt><dd><?= e($profile['territory'] ?? '—') ?></dd>
      </dl>
      <?php if ($status === 'verificada'): ?>
        <div class="notice mt-2"><b>Verificada.</b> Ya puedes abrir peticiones y aceptar propuestas.</div>
      <?php elseif ($pending): ?>
        <div class="notice mt-2"><b>En revisión.</b> Tu solicitud está en la cola de moderación. No necesitas enviar otra.</div>
      <?php elseif ($status === 'rechazada'): ?>
        <div class="notice mt-2"><b>Rechazada.</b> Revisa el motivo y vuelve a enviar la documentación corregida.</div>
      <?php endif; ?>
    </div>

    <?php if ($status !== 'verificada' && !$pending): ?>
      <form class="card" method="post" action="<?= e(url('/bar/verification')) ?>">
        <?= csrf_field() ?>
        <h2>Enviar documentación</h2>
        <div class="form-row">
          <div class="field">
            <label for="submitted_nif">NIF / NIE / CIF</label>
            <input type="text" id="submitted_nif" name="submitted_nif" required value="<?= e(old('submitted_nif', $profile['nif'] ?? '')) ?>">
            <span class="hint">Se valida el dígito de control.</span>
          </div>
          <div class="field">
            <label for="submitted_license">Número de licencia</label>
            <input type="text" id="submitted_license" name="submitted_license" required value="<?= e(old('submitted_license', $profile['license_number'] ?? '')) ?>">
          </div>
        </div>
        <div class="field">
          <label for="document_ref">Referencia del documento</label>
          <input type="text" id="document_ref" name="document_ref" value="<?= e(old('document_ref')) ?>" placeholder="Expediente, registro o URL privada">
          <span class="hint">No subimos archivos: indica dónde puede comprobarlo el moderador.</span>
        </div>
        <div class="field">
          <label for="notes">Notas para el moderador</label>
          <textarea id="notes" name="notes" rows="3"><?= e(old('notes')) ?></textarea>
        </div>
        <button class="btn btn-gold" type="submit">Enviar a revisión</button>
      </form>
    <?php endif; ?>
  </div>

  <aside>
    <div class="card">
      <h3>Última solicitud</h3>
      <?php if ($latest === null): ?>
        <p class="muted small">Sin solicitudes registradas.</p>
      <?php else: ?>
        <dl class="kv">
          <dt>Estado</dt><dd><?= e(status_label((string) $latest['status'])) ?></dd>
          <dt>Enviada</dt><dd><?= e(format_date($latest['submitted_at'], 'd/m/Y H:i')) ?></dd>
          <dt>Revisada</dt><dd><?= e(format_date($latest['reviewed_at'], 'd/m/Y H:i')) ?></dd>
          <dt>NIF</dt><dd><?= e(mask_nif($latest['submitted_nif'] ?? null)) ?></dd>
          <dt>Licencia</dt><dd><?= e($latest['submitted_license'] ?? '—') ?></dd>
        </dl>
        <?php if (!empty($latest['notes'])): ?>
          <div class="notice mt-1"><?= e($latest['notes']) ?></div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3>Qué se comprueba</h3>
      <ul class="list">
        <li><span class="title">NIF válido</span><div class="sub">Dígito de control y tipo de documento.</div></li>
        <li><span class="title">Licencia de restauración</span><div class="sub">Que el número y el tipo sean coherentes.</div></li>
        <li><span class="title">Sin duplicados</span><div class="sub">Un NIF no puede sostener dos locales activos.</div></li>
      </ul>
      <p class="muted small">La verificación no certifica la calidad del local: sólo su capacidad legal para comprar y servir alcohol.</p>
    </div>
  </aside>
</div>
