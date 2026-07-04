<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SENCE Mock - Dashboard</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', monospace; max-width: 1200px; margin: 20px auto; padding: 0 20px; background: #1a1a2e; color: #eee; }
        h1 { color: #e94560; border-bottom: 2px solid #e94560; padding-bottom: 10px; }
        h2 { color: #0f3460; background: #16213e; padding: 8px 15px; border-radius: 4px; margin-top: 30px; }
        h3 { color: #e94560; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 13px; }
        th { background: #0f3460; color: #e94560; padding: 8px 10px; text-align: left; }
        td { padding: 6px 10px; border-bottom: 1px solid #16213e; vertical-align: top; }
        tr:hover { background: #16213e; }
        .badge { padding: 2px 8px; border-radius: 3px; font-size: 11px; }
        .badge-success { background: #2d6a4f; color: #d8f3dc; }
        .badge-error { background: #9b2226; color: #f5c6cb; }
        .badge-warning { background: #7b4b00; color: #ffe8a1; }
        .badge-info { background: #1b4f72; color: #bee5ff; }
        .nav { margin-bottom: 20px; }
        .nav a { color: #e94560; margin-right: 15px; text-decoration: none; font-weight: bold; font-size: 14px; }
        .nav a:hover { text-decoration: underline; }
        .btn { background: #e94560; color: #fff; border: none; padding: 6px 14px; cursor: pointer; border-radius: 3px; text-decoration: none; font-size: 12px; display: inline-block; }
        .btn:hover { background: #c73a50; }
        .btn-sm { padding: 3px 10px; font-size: 11px; }
        .btn-danger { background: #9b2226; }
        .btn-danger:hover { background: #7b1a1f; }
        .btn-success { background: #2d6a4f; }
        .btn-success:hover { background: #1f4d3a; }
        .section-header { display: flex; justify-content: space-between; align-items: center; }
        .error-form { background: #16213e; padding: 20px; border-radius: 6px; margin: 15px 0; border: 1px solid #0f3460; }
        .error-form label { display: block; margin: 10px 0 4px; color: #aaa; font-size: 12px; }
        .error-form select, .error-form input { width: 100%; padding: 8px 10px; background: #1a1a2e; border: 1px solid #0f3460; color: #eee; border-radius: 3px; font-size: 13px; }
        .error-form .form-row { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 10px; align-items: end; }
        .error-form .form-row-full { display: grid; grid-template-columns: 3fr 1fr; gap: 10px; align-items: end; }
        .muted { color: #888; font-size: 12px; }
        .warning-box { background: #7b4b00; border: 1px solid #b8860b; color: #ffe8a1; padding: 10px 15px; border-radius: 4px; margin: 10px 0; font-size: 13px; }
        p { margin: 6px 0; }
    </style>
</head>
<body>
    <h1>SENCE Mock Server Dashboard</h1>
    <div class="nav">
        <a href="/sence/dashboard">Resumen</a>
        <a href="/sence/dashboard/sesiones">Sesiones RCE</a>
        <a href="/sence/dashboard/procesos">Procesos SIC</a>
        <a href="/sence/dashboard/avances">Avances</a>
        <a href="/sence/dashboard/errores" style="color:#f39c12;">Forzar Errores</a>
        <a href="/sence/dashboard/limpiar" onclick="return confirm('Limpiar todos los datos?')" style="color:#e74c3c;">Limpiar Datos</a>
    </div>

    <!-- ===== FORZAR ERRORES SECTION ===== -->
    <?php if (isset($showErrorForm) || !empty($erroresActivos)): ?>
    <h2>Forzar Errores</h2>

    <?php if (!empty($erroresActivos)): ?>
    <div class="warning-box">
        Errores activos: las siguientes peticiones devolveran error.
    </div>
    <table>
        <tr>
            <th>ID</th><th>Endpoint</th><th>Codigo</th><th>Peticiones Restantes</th><th>Glosa</th><th>Creado</th><th></th>
        </tr>
        <?php foreach ($erroresActivos as $e): ?>
        <tr>
            <td><?= $e['id'] ?></td>
            <td><span class="badge badge-info"><?= esc($e['endpoint']) ?></span></td>
            <td><span class="badge badge-error"><?= esc($e['codigo_error']) ?></span></td>
            <td><?= $e['peticiones_restantes'] ?></td>
            <td class="muted"><?= esc($e['glosa_error'] ?? '-') ?></td>
            <td class="muted"><?= esc($e['created_at']) ?></td>
            <td>
                <form method="post" action="/sence/dashboard/errores" style="display:inline">
                    <input type="hidden" name="action" value="eliminar">
                    <input type="hidden" name="error_id" value="<?= $e['id'] ?>">
                    <button class="btn btn-sm btn-danger">X</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <form method="post" action="/sence/dashboard/errores" style="margin-top:10px">
        <input type="hidden" name="action" value="limpiar">
        <button class="btn btn-sm btn-danger" onclick="return confirm('Eliminar TODOS los errores activos?')">Limpiar Todos</button>
    </form>
    <?php endif; ?>

    <?php if (isset($showErrorForm)): ?>
    <div class="error-form">
        <h3 style="margin:0 0 15px 0;">Activar Error Forzado</h3>
        <form method="post" action="/sence/dashboard/errores">
            <input type="hidden" name="action" value="activar">
            <div class="form-row">
                <div>
                    <label>Endpoint</label>
                    <select name="endpoint" required>
                        <option value="rce_inicio">RCE Inicio Sesion (200-313)</option>
                        <option value="rce_cierre">RCE Cierre Sesion (200-313)</option>
                        <option value="sic_avance">SIC Enviar Avance (001-033)</option>
                        <option value="sic_historial">SIC Historial Envios</option>
                    </select>
                </div>
                <div>
                    <label>Codigo Error</label>
                    <select name="codigo_error" required>
                        <optgroup label="RCE (200-313)">
                            <option value="200">200 - Parametros vacios</option>
                            <option value="201">201 - UrlRetoma/UrlError vacias</option>
                            <option value="202">202 - UrlRetoma formato</option>
                            <option value="203">203 - UrlError formato</option>
                            <option value="204">204 - CodSence invalido</option>
                            <option value="205">205 - CodigoCurso < 7 chars</option>
                            <option value="206">206 - Linea incorrecta</option>
                            <option value="207">207 - RunAlumno formato</option>
                            <option value="208">208 - RunAlumno no autorizado</option>
                            <option value="209">209 - RutOtec formato</option>
                            <option value="210">210 - Sesion expirada</option>
                            <option value="211">211 - Token no pertenece</option>
                            <option value="212">212 - Token no vigente</option>
                            <option value="300">300 - Error interno SENCE</option>
                            <option value="303">303 - Token no existe</option>
                            <option value="311">311 - RUT no coincide</option>
                            <option value="313">313 - URL cierre incorrecta</option>
                        </optgroup>
                        <optgroup label="SIC (001-033)">
                            <option value="001">001 - Token invalido</option>
                            <option value="003">003 - Curso no registrado</option>
                            <option value="010">010 - Alumno no en modulo</option>
                            <option value="011">011 - Alumno no en curso</option>
                            <option value="012">012 - Alumno no registrado</option>
                            <option value="021">021 - tiempoConectividad invalido</option>
                            <option value="022">022 - porcentajeAvance invalido</option>
                            <option value="023">023 - modulo.tiempo invalido</option>
                            <option value="024">024 - modulo.porcentaje invalido</option>
                            <option value="025">025 - %Avance retroceso</option>
                            <option value="026">026 - fechaInicio invalida</option>
                            <option value="027">027 - fechaFin invalida</option>
                            <option value="028">028 - notaModulo invalida</option>
                            <option value="029">029 - cantSincronica invalida</option>
                            <option value="030">030 - cantAsincronica invalida</option>
                            <option value="031">031 - evaluacionFinal invalida</option>
                            <option value="032">032 - curso.cantSincronica</option>
                            <option value="033">033 - curso.cantAsincronica</option>
                        </optgroup>
                    </select>
                </div>
                <div>
                    <label>Peticiones a Fallar</label>
                    <input type="number" name="peticiones" value="1" min="1" max="100" required>
                </div>
                <div>
                    <label>Glosa / Mensaje (opcional)</label>
                    <input type="text" name="glosa_error" placeholder="Mensaje personalizado">
                </div>
            </div>
            <button class="btn" style="margin-top:15px;">Activar Error</button>
        </form>
    </div>
    <?php endif; ?>

    <?php endif; ?>
    <!-- ===== END FORZAR ERRORES ===== -->

    <?php if (!isset($showErrorForm)): ?>
    <div class="section-header">
        <h2>Resumen</h2>
    </div>

    <h3>Sesiones RCE (<?= $sesionesCount ?? 0 ?>)</h3>
    <?php if (!empty($sesiones)): ?>
    <table>
        <tr><th>ID</th><th>RUT OTEC</th><th>RUT Alumno</th><th>Curso</th><th>IdSesionSence</th><th>Estado</th><th>Inicio</th></tr>
        <?php foreach ($sesiones as $s): ?>
        <tr>
            <td><?= $s['id'] ?></td>
            <td><?= esc($s['rut_otec']) ?></td>
            <td><?= esc($s['run_alumno']) ?></td>
            <td><?= esc($s['codigo_curso']) ?></td>
            <td><?= esc($s['id_sesion_sence']) ?></td>
            <td><span class="badge <?= $s['estado'] === 'activa' ? 'badge-success' : 'badge-warning' ?>"><?= esc($s['estado']) ?></span></td>
            <td><?= esc($s['fecha_inicio']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p class="muted">No hay sesiones registradas.</p>
    <?php endif; ?>

    <h3>Ultimos Procesos SIC (<?= $procesosCount ?? 0 ?>)</h3>
    <?php if (!empty($procesos)): ?>
    <table>
        <tr><th>ID</th><th>Proceso</th><th>RUT OTEC</th><th>Oferta</th><th>Envio</th><th>Estado</th><th>Fecha</th></tr>
        <?php foreach ($procesos as $p): ?>
        <tr>
            <td><?= $p['id'] ?></td>
            <td><?= esc($p['id_proceso']) ?></td>
            <td><?= esc($p['rut_otec']) ?></td>
            <td><?= esc($p['codigo_oferta']) ?></td>
            <td><?= esc($p['codigo_externo']) ?></td>
            <td><span class="badge <?= $p['estado'] === '2' ? 'badge-success' : 'badge-error' ?>"><?= esc($p['estado']) ?></span></td>
            <td><?= esc($p['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p class="muted">No hay procesos registrados.</p>
    <?php endif; ?>

    <?php if (!empty($avances)): ?>
    <h3>Ultimos Avances (<?= $avancesCount ?? 0 ?>)</h3>
    <table>
        <tr><th>ID</th><th>RUT Alumno</th><th>DV</th><th>Modulo</th><th>% Avance</th><th>Proceso</th><th>Fecha</th></tr>
        <?php foreach ($avances as $a): ?>
        <tr>
            <td><?= $a['id'] ?></td>
            <td><?= esc($a['rut_alumno']) ?></td>
            <td><?= esc($a['dv_alumno']) ?></td>
            <td><?= esc($a['codigo_modulo']) ?></td>
            <td><?= esc($a['porcentaje_avance']) ?></td>
            <td><?= esc($a['proceso_id']) ?></td>
            <td><?= esc($a['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <?php if (!empty($erroresActivos)): ?>
    <h3>Errores Activos (<?= count($erroresActivos) ?>)</h3>
    <table>
        <tr><th>Endpoint</th><th>Codigo</th><th>Peticiones Rest.</th><th>Glosa</th></tr>
        <?php foreach ($erroresActivos as $e): ?>
        <tr>
            <td><span class="badge badge-info"><?= esc($e['endpoint']) ?></span></td>
            <td><span class="badge badge-error"><?= esc($e['codigo_error']) ?></span></td>
            <td><?= $e['peticiones_restantes'] ?></td>
            <td class="muted"><?= esc($e['glosa_error'] ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
    <?php endif; ?>
</body>
</html>
