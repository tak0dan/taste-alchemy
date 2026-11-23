<?php
/** @var array<string, mixed>|null $profile */
$verified = $profile !== null && $profile['license_status'] === 'verificada';
?>
<div class="page-head">
  <p class="eyebrow">Local · nueva petición</p>
  <h1>Describe una necesidad concreta</h1>
  <p class="lede">No pides «un licor bueno». Pides un estilo, un rango de graduación, unas unidades y una fecha. Los artesanos responderán con lotes reales.</p>
</div>

<?php if (!$verified): ?>
  <div class="notice">
    <b>Licencia <?= e(status_label((string) ($profile['license_status'] ?? 'pendiente'))) ?>.</b>
    No puedes abrir peticiones hasta que un moderador verifique tu licencia.
    <a href="<?= e(url('/bar/verification')) ?>">Completar verificación</a>.
  </div>
<?php endif; ?>

<form class="grid grid-side" method="post" action="<?= e(url('/bar/requests')) ?>">
  <?= csrf_field() ?>
  <div>
    <div class="card">
      <h2>La necesidad</h2>
      <div class="field">
        <label for="title">Título</label>
        <input type="text" id="title" name="title" required minlength="4" value="<?= e(old('title')) ?>" placeholder="Licor de cereza para carta de invierno">
      </div>
      <div class="form-row">
        <div class="field">
          <label for="style">Estilo</label>
          <input type="text" id="style" name="style" required list="style-list" value="<?= e(old('style', 'Licor de fruta')) ?>">
          <datalist id="style-list">
            <option value="Licor de fruta"></option>
            <option value="Licor de hierbas"></option>
            <option value="Licor de flor"></option>
            <option value="Aperitivo"></option>
            <option value="Digestivo"></option>
            <option value="Crema de licor"></option>
          </datalist>
        </div>
        <div class="field">
          <label for="territory">Territorio</label>
          <input type="text" id="territory" name="territory" required value="<?= e(old('territory', $profile['territory'] ?? 'Comunitat Valenciana')) ?>">
        </div>
      </div>
      <div class="field">
        <label for="description">Descripción</label>
        <textarea id="description" name="description" rows="5" placeholder="Para qué momento del servicio, qué perfil buscas, qué evitar…"><?= e(old('description')) ?></textarea>
      </div>
      <div class="form-row-3">
        <div class="field">
          <label for="units_wanted">Unidades</label>
          <input type="number" step="1" min="1" id="units_wanted" name="units_wanted" required value="<?= e(old('units_wanted', '24')) ?>">
        </div>
        <div class="field">
          <label for="abv_min">ABV mínimo (%)</label>
          <input type="number" step="0.1" min="0" max="90" id="abv_min" name="abv_min" value="<?= e(old('abv_min', '18')) ?>">
        </div>
        <div class="field">
          <label for="abv_max">ABV máximo (%)</label>
          <input type="number" step="0.1" min="0" max="90" id="abv_max" name="abv_max" value="<?= e(old('abv_max', '32')) ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="field">
          <label for="target_date">Fecha objetivo</label>
          <input type="date" id="target_date" name="target_date" required value="<?= e(old('target_date', date('Y-m-d', strtotime('+45 days')))) ?>" min="<?= date('Y-m-d') ?>">
        </div>
        <div class="field">
          <label for="expires_at">Caducidad de la petición (opcional)</label>
          <input type="date" id="expires_at" name="expires_at" value="<?= e(old('expires_at')) ?>" min="<?= date('Y-m-d') ?>">
        </div>
      </div>
      <label class="checkline">
        <input type="checkbox" name="exclusivity_requested" value="1">
        <span>Quiero exclusividad territorial sobre el lote elegido</span>
      </label>
    </div>
  </div>

  <aside>
    <div class="card">
      <h3>Qué pasará</h3>
      <ul class="list">
        <li><span class="title">Los artesanos recibirán un aviso</span><div class="sub">Con el título, las unidades y el territorio.</div></li>
        <li><span class="title">Sólo verás lotes elegibles</span><div class="sub">Coherencia mínima y unidades disponibles.</div></li>
        <li><span class="title">Aceptar reserva unidades</span><div class="sub">La operación es atómica: no hay sobreventa.</div></li>
        <li><span class="title">Después, factura o consignación</span><div class="sub">Entre NIFs, sin pasarela de pago.</div></li>
      </ul>
      <div class="notice">
        <b>Sé concreto.</b> Una petición vaga recibe propuestas vagas. El estilo, el ABV y la fecha son el filtro.
      </div>
      <button class="btn btn-gold btn-block" type="submit" <?= $verified ? '' : 'disabled' ?>>Abrir petición</button>
      <a class="btn btn-ghost btn-block mt-1" href="<?= e(url('/bar')) ?>">Cancelar</a>
    </div>
  </aside>
</form>
