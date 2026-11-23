<?php
/** @var array<string, mixed> $overview */
$counts = $overview['counts'];
$queue = $overview['queue'];
$reports = $overview['reports'];
$audit = $overview['audit'];
$verified = $overview['verified'];
?>
<div class="page-head">
  <p class="eyebrow">Moderación · decisiones auditadas</p>
  <h1>La puerta que se abre a mano</h1>
  <p class="lede">Cada licencia, reporte y sanción deja una huella inmutable. No hay verificación automática de documentos: hay criterio y traza.</p>
</div>

<div class="stats">
  <div class="stat"><div class="k"><?= (int) $counts['pending_verifications'] ?></div><div class="l">Verificaciones pendientes</div><div class="s">Licencias esperando revisión</div></div>
  <div class="stat"><div class="k"><?= (int) $counts['open_reports'] ?></div><div class="l">Reportes abiertos</div><div class="s">Sin resolver o en revisión</div></div>
  <div class="stat"><div class="k"><?= (int) $counts['audit_entries'] ?></div><div class="l">Entradas de auditoría</div><div class="s">Traza de sólo inserción</div></div>
  <div class="stat"><div class="k"><?= count($verified) ?></div><div class="l">Locales verificados</div><div class="s">Con licencia comprobada</div></div>
</div>

<div class="grid grid-side">
  <div>
    <section class="card">
      <div class="card-head">
        <h2>Cola de verificación</h2>
        <span class="muted small"><?= count($queue) ?> pendientes</span>
      </div>
      <?php if ($queue === []): ?>
        <p class="muted">No hay licencias esperando. La cola está limpia.</p>
      <?php else: ?>
        <?php foreach ($queue as $item): ?>
          <div class="card flat" style="margin-bottom:14px">
            <div class="flex between">
              <div>
                <span class="gold" style="font-family:var(--serif);font-size:1.2rem"><?= e($item['venue_name']) ?></span>
                <div class="muted small"><?= e($item['display_name']) ?> · <?= e($item['email']) ?> · <?= e($item['territory']) ?></div>
              </div>
              <span class="badge warn">Pendiente</span>
            </div>
            <div class="meta-line mt-1">
              <span>NIF <b><?= e(mask_nif($item['submitted_nif'])) ?></b></span>
              <span>Licencia <b><?= e($item['submitted_license']) ?></b></span>
              <span>Documento <b><?= e($item['document_ref'] ?: '—') ?></b></span>
              <span>Enviada <?= e(format_date($item['submitted_at'], 'd/m/Y H:i')) ?></span>
            </div>
            <?php if (!empty($item['notes'])): ?><p class="dim small">«<?= e($item['notes']) ?>»</p><?php endif; ?>
            <div class="divider"></div>
            <div class="form-row">
              <form method="post" action="<?= e(url('/moderation/verifications/' . $item['id'] . '/approve')) ?>">
                <?= csrf_field() ?>
                <div class="field">
                  <label for="notes-<?= (int) $item['id'] ?>">Nota de aprobación</label>
                  <input type="text" id="notes-<?= (int) $item['id'] ?>" name="notes" placeholder="Comprobado en registro autonómico">
                </div>
                <button class="btn btn-primary btn-sm" type="submit">Aprobar licencia</button>
              </form>
              <form method="post" action="<?= e(url('/moderation/verifications/' . $item['id'] . '/reject')) ?>" data-confirm="¿Rechazar esta verificación?">
                <?= csrf_field() ?>
                <div class="field">
                  <label for="reason-<?= (int) $item['id'] ?>">Motivo del rechazo</label>
                  <input type="text" id="reason-<?= (int) $item['id'] ?>" name="reason" value="Documentación incompleta">
                </div>
                <button class="btn btn-sm btn-danger" type="submit">Rechazar</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head">
        <h2>Reportes</h2>
        <span class="muted small"><?= count($reports) ?></span>
      </div>
      <?php if ($reports === []): ?>
        <p class="muted">Sin reportes. Nadie ha denunciado nada.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Entidad</th><th>Motivo</th><th>Denunciante</th><th>Estado</th><th>Resolución</th></tr></thead>
            <tbody>
              <?php foreach ($reports as $report): ?>
                <tr>
                  <td><?= e($report['entity_type']) ?> #<?= (int) $report['entity_id'] ?></td>
                  <td><?= e($report['reason']) ?><?= !empty($report['details']) ? '<br><span class="muted small">' . e(mb_strimwidth((string) $report['details'], 0, 120, '…')) . '</span>' : '' ?></td>
                  <td><?= e($report['reporter_name'] ?: 'Anónimo') ?></td>
                  <td><span class="badge <?= e(status_class((string) $report['status'])) ?>"><?= e(status_label((string) $report['status'])) ?></span></td>
                  <td>
                    <?php if (in_array($report['status'], ['abierto', 'en_revision'], true)): ?>
                      <form method="post" action="<?= e(url('/moderation/reports/' . $report['id'] . '/resolve')) ?>">
                        <?= csrf_field() ?>
                        <select name="status" style="margin-bottom:6px">
                          <option value="resuelto">Resuelto</option>
                          <option value="en_revision">En revisión</option>
                          <option value="descartado">Descartado</option>
                        </select>
                        <input type="text" name="resolution" placeholder="Resolución" value="Revisado" style="margin-bottom:6px">
                        <button class="btn btn-sm" type="submit">Guardar</button>
                      </form>
                    <?php else: ?>
                      <span class="muted small"><?= e($report['resolution'] ?: '—') ?></span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <aside>
    <div class="card">
      <h3>Locales verificados</h3>
      <?php if ($verified === []): ?>
        <p class="muted small">Ninguno todavía.</p>
      <?php else: ?>
        <ul class="list">
          <?php foreach ($verified as $venue): ?>
            <li>
              <div class="title"><?= e($venue['venue_name']) ?></div>
              <div class="sub"><?= e($venue['territory']) ?> · <?= (int) $venue['completed_commissions'] ?> comisiones · <?= e(format_date($venue['verified_at'])) ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3>Traza de auditoría</h3>
      <p class="muted small">Últimas <?= count($audit) ?> acciones. La tabla es inmutable por disparador.</p>
      <div class="table-wrap" style="max-height:420px;overflow:auto">
        <table class="table">
          <thead><tr><th>Acción</th><th>Entidad</th><th>Fecha</th></tr></thead>
          <tbody>
            <?php foreach ($audit as $entry): ?>
              <tr>
                <td><?= e($entry['action']) ?></td>
                <td><?= e($entry['entity_type']) ?><?= $entry['entity_id'] !== null ? ' #' . (int) $entry['entity_id'] : '' ?></td>
                <td><?= e(format_date($entry['created_at'], 'd/m H:i')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </aside>
</div>
