<?php
/** @var string $next */
?>
<div class="narrow" style="margin:0 auto">
  <div class="page-head center">
    <p class="eyebrow">Acceso</p>
    <h1>Entrar en el oficio</h1>
    <p class="lede" style="margin:0 auto">Tu cuenta es la firma de tus lotes y de tus catas. No hay acceso anónimo a la escritura.</p>
  </div>

  <form class="card" method="post" action="<?= e(url('/login')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <div class="field">
      <label for="email">Correo</label>
      <input type="email" id="email" name="email" required autocomplete="email" value="<?= e(old('email')) ?>">
    </div>
    <div class="field">
      <label for="password">Contraseña</label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
    </div>
    <button class="btn btn-primary btn-block" type="submit">Entrar</button>
    <p class="muted small mt-2" style="text-align:center">
      ¿Aún no tienes cuenta? <a href="<?= e(url('/register')) ?>">Abre un taller o un local</a>.
    </p>
  </form>

  <div class="card flat">
    <h3>Cuentas de demostración</h3>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Rol</th><th>Correo</th><th>Contraseña</th></tr></thead>
        <tbody>
          <tr><td>Artesano</td><td><code>artesano@tastealchemy.test</code></td><td><code>Alchemy2026</code></td></tr>
          <tr><td>Local</td><td><code>local@tastealchemy.test</code></td><td><code>Alchemy2026</code></td></tr>
          <tr><td>Comisario</td><td><code>comisario@tastealchemy.test</code></td><td><code>Alchemy2026</code></td></tr>
          <tr><td>Moderador</td><td><code>moderador@tastealchemy.test</code></td><td><code>Alchemy2026</code></td></tr>
          <tr><td>Admin</td><td><code>admin@tastealchemy.test</code></td><td><code>Alchemy2026</code></td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
