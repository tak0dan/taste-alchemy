<?php
$settings = $settings ?? [];
?>
<div class="page-head narrow">
  <p class="eyebrow">Manifiesto</p>
  <h1>La evidencia es la única credencial que no se puede fabricar.</h1>
  <p class="lede">Taste Alchemy nace de una sospecha: el prestigio de un licor se sostiene demasiadas veces en una etiqueta. Aquí se sostiene en un historial.</p>
  <div class="rule"></div>
</div>

<div class="grid grid-side">
  <div>
    <article class="card">
      <h2>1. El repositorio es público; el lote es privado</h2>
      <p>Una receta es conocimiento. Se publica, se versiona y se bifurca con crédito. Un lote es una barrica concreta, con su volumen y su graduación; su historial físico se publica, pero su operación económica se cierra entre las partes.</p>
      <p>Por eso cada receta tiene versiones inmutables y cada versión es ejecutable por el motor de cálculo. Una receta que no se puede reproducir es literatura, no oficio.</p>
    </article>

    <article class="card">
      <h2>2. La coherencia se mide, no se opina</h2>
      <p>Cuatro comprobaciones independientes, con pesos públicos y auditables:</p>
      <div class="breakdown">
        <div class="item"><span class="name">A · Evaporación de barrica</span><span class="val">35 %</span><span class="meter"><i style="width:35%"></i></span><span class="why">El volumen perdido debe parecerse a la evaporación anual esperada durante la edad declarada.</span></div>
        <div class="item"><span class="name">B · Trayectoria de graduación</span><span class="val">25 %</span><span class="meter"><i style="width:25%"></i></span><span class="why">La graduación baja con el tiempo; un repunte sin explicación resta puntos.</span></div>
        <div class="item"><span class="name">C · Cadencia de lecturas</span><span class="val">20 %</span><span class="meter"><i style="width:20%"></i></span><span class="why">No basta con dos lecturas antiguas: el silencio reciente también se juzga.</span></div>
        <div class="item"><span class="name">D · Declaración</span><span class="val">20 %</span><span class="meter"><i style="width:20%"></i></span><span class="why">La graduación declarada debe coincidir con la última lectura física.</span></div>
      </div>
      <div class="notice">El resultado se guarda con su desglose completo. Cualquiera puede leer por qué un lote tiene 84 y no 92. La caja negra es el enemigo.</div>
    </article>

    <article class="card">
      <h2>3. Aceptar una propuesta es un acto transaccional</h2>
      <p>Cuando un local acepta una propuesta, ocurren cinco cosas en una sola transacción: se bloquea la fila del lote, se reservan unidades, se cierra la petición, se crea la comisión y se concede —o no— la exclusividad territorial. Si algo falla, no ocurre nada.</p>
      <p>No hay sobreventa posible. No hay dos locales con la misma exclusividad sobre el mismo lote y territorio. No hay factura sin NIF. La base de datos es la que dice que no.</p>
    </article>

    <article class="card">
      <h2>4. La valoración espera 72 horas</h2>
      <p>Sólo la contraparte de una comisión real puede valorar. Y no puede hacerlo hasta 72 horas después de cerrarla. La espera no es burocracia: es el tiempo mínimo para separar el entusiasmo de la cata.</p>
      <p>Las catas de comisario son otra cosa: ponderadas, firmadas y limitadas a lotes que ya superan el umbral de coherencia. No sustituyen a la valoración del local; la acompañan.</p>
    </article>

    <article class="card">
      <h2>5. Seis roles, acumulables</h2>
      <p>Visitante, artesano, local, comisario, moderador y administrador. Un mismo usuario puede ser artesano y comisario; nunca puede verificar su propia licencia. Los permisos se comprueban en el servidor y se auditan.</p>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Rol</th><th>Puede</th><th>No puede</th></tr></thead>
          <tbody>
            <tr><td>Visitante</td><td>Leer recetas, lotes y mercado; calcular.</td><td>Escribir, proponer, aceptar.</td></tr>
            <tr><td>Artesano</td><td>Publicar recetas, versionar, bifurcar, registrar lotes y proponer.</td><td>Aceptar propuestas ni verificar licencias.</td></tr>
            <tr><td>Local</td><td>Abrir peticiones, aceptar propuestas, valorar tras 72 h.</td><td>Publicar recetas ni proponer lotes.</td></tr>
            <tr><td>Comisario</td><td>Publicar catas ponderadas sobre lotes elegibles.</td><td>Participar en comisiones ni moderar.</td></tr>
            <tr><td>Moderador</td><td>Verificar licencias, resolver reportes, sancionar.</td><td>Cambiar umbrales ni roles.</td></tr>
            <tr><td>Administrador</td><td>Gestionar usuarios, catálogos y parámetros.</td><td>Borrar la traza de auditoría.</td></tr>
          </tbody>
        </table>
      </div>
    </article>

    <article class="card">
      <h2>6. Lo que este proyecto no hace</h2>
      <p>No procesa pagos. No integra transportistas. No tiene app nativa. No verifica documentos automáticamente. No promete que un licor sea bueno: sólo enseña si su historia física es plausible.</p>
      <p>Esa renuncia es deliberada. Un proyecto intermodular no debe fingir ser una empresa; debe demostrar que entiende el problema y que puede construir la parte que importa.</p>
    </article>
  </div>

  <aside>
    <div class="card">
      <h3>Arquitectura</h3>
      <dl class="kv">
        <dt>Servidor</dt><dd>PHP 8.1 · OOP · PDO</dd>
        <dt>Base de datos</dt><dd>MariaDB 10.6 · InnoDB</dd>
        <dt>Tablas</dt><dd>26</dd>
        <dt>Vistas</dt><dd>5</dd>
        <dt>Disparadores</dt><dd>7</dd>
        <dt>Procedimientos</dt><dd>6</dd>
        <dt>API</dt><dd>REST /api/v1 · OpenAPI 3.1</dd>
        <dt>Motor</dt><dd>PHP puro + réplica JS</dd>
        <dt>Herramientas</dt><dd>Python · Java</dd>
      </dl>
    </div>

    <div class="card">
      <h3>Umbrales vigentes</h3>
      <dl class="kv">
        <dt>Anuncio mínimo</dt><dd><?= e($settings['trust.listing_min_score'] ?? '70') ?>/100</dd>
        <dt>Reflexión</dt><dd><?= e($settings['trust.review_reflection_hours'] ?? '72') ?> h</dd>
        <dt>IVA</dt><dd><?= e($settings['billing.vat_rate'] ?? '21') ?> %</dd>
        <dt>Consignación</dt><dd><?= e($settings['consignment.default_days'] ?? '90') ?> días</dd>
      </dl>
      <p class="muted small mt-1">Los umbrales se cambian desde administración y cada cambio queda auditado.</p>
    </div>

    <div class="card center">
      <?= sigil(56) ?>
      <h3>¿Firmas?</h3>
      <p class="dim small">Abre un taller o un local. La primera lectura siempre es tuya.</p>
      <a class="btn btn-primary btn-block" href="<?= e(url('/register')) ?>">Abrir cuenta</a>
    </div>
  </aside>
</div>
