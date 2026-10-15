<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="cal-board" id="calBoard"
     data-vista="<?= htmlspecialchars($vista) ?>"
     data-hoy="<?= date('Y-m-d') ?>"
     data-hora-desde="<?= (int) $horaDesde ?>"
     data-hora-px="<?= (int) $hourPx ?>">

    <div class="toolbar">
        <div class="toolbar-left">
            <div class="cal-switch" role="tablist" aria-label="Vista del calendario">
                <a href="<?= htmlspecialchars($urlSemana) ?>" class="cal-switch-btn <?= $vista === 'semana' ? 'is-active' : '' ?>" data-vista="semana" role="tab">Semana</a>
                <a href="<?= htmlspecialchars($urlMes) ?>" class="cal-switch-btn <?= $vista === 'mes' ? 'is-active' : '' ?>" data-vista="mes" role="tab">Mes</a>
            </div>
            <div class="btn-group">
                <a href="<?= htmlspecialchars($vista === 'mes' ? $urlPrevMes : $urlPrevSemana) ?>" id="calPrev" class="btn btn-sm btn-secondary" title="Periodo anterior"
                   data-semana="<?= htmlspecialchars($urlPrevSemana) ?>" data-mes="<?= htmlspecialchars($urlPrevMes) ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <a href="<?= htmlspecialchars($vista === 'mes' ? $urlHoyMes : $urlHoySemana) ?>" id="calHoy" class="btn btn-sm btn-primary"
                   data-semana="<?= htmlspecialchars($urlHoySemana) ?>" data-mes="<?= htmlspecialchars($urlHoyMes) ?>">Hoy</a>
                <a href="<?= htmlspecialchars($vista === 'mes' ? $urlNextMes : $urlNextSemana) ?>" id="calNext" class="btn btn-sm btn-secondary" title="Periodo siguiente"
                   data-semana="<?= htmlspecialchars($urlNextSemana) ?>" data-mes="<?= htmlspecialchars($urlNextMes) ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            <h2 class="cal-title" id="calTitulo"
                data-semana="<?= htmlspecialchars($tituloSemana) ?>"
                data-mes="<?= htmlspecialchars($tituloMes) ?>">
                <?= htmlspecialchars($vista === 'mes' ? $tituloMes : $tituloSemana) ?>
            </h2>
        </div>
        <div class="toolbar-right">
            <form method="GET" action="<?= BASE_URL ?>" class="cal-filters">
                <input type="hidden" name="action" value="horarios/miHorario">
                <input type="hidden" name="vista" id="calVistaInput" value="<?= htmlspecialchars($vista) ?>">
                <input type="date" name="fecha" class="form-control" value="<?= htmlspecialchars($fechaRef) ?>" aria-label="Ir a fecha" onchange="this.form.submit()">
                <select name="ficha_id" class="form-control" aria-label="Filtrar por ficha" onchange="this.form.submit()">
                    <option value="">Todas las fichas</option>
                    <?php foreach ($fichasFiltro as $f): ?>
                        <option value="<?= (int) $f['id'] ?>" <?= $fichaId === (int) $f['id'] ? 'selected' : '' ?>>
                            Ficha <?= htmlspecialchars($f['codigo']) ?><?= !empty($f['nombre_programa']) ? ' — ' . htmlspecialchars($f['nombre_programa']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="estado" class="form-control" aria-label="Filtrar por estado" onchange="this.form.submit()">
                    <option value="">Todos los estados</option>
                    <?php foreach (['Activo', 'Finalizada', 'Cancelado'] as $op): ?>
                        <option value="<?= $op ?>" <?= $estado === $op ? 'selected' : '' ?>><?= $op ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($hayFiltro): ?>
                    <a href="<?= htmlspecialchars($urlLimpiar) ?>" class="btn btn-sm btn-secondary">Limpiar</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <p class="cal-subtitle">
        Carga de <strong><?= htmlspecialchars($nombreInstructor) ?></strong>
        <span class="text-muted" id="calResultados"></span>
    </p>

    <div class="dashboard-grid cal-stats">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: linear-gradient(135deg, #10b981, #34d399);">
                <i class="fas fa-calendar-week"></i>
            </div>
            <div class="stat-card-info">
                <span class="stat-card-value"><?= (int) $resumen['sesiones_semana'] ?></span>
                <span class="stat-card-label">Sesiones de la semana</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background: linear-gradient(135deg, #06b6d4, #22d3ee);">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-card-info">
                <span class="stat-card-value cal-stat-hours"><?= htmlspecialchars($resumen['horas_semana']) ?></span>
                <span class="stat-card-label">Horas de la semana</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-card-info">
                <span class="stat-card-value"><?= (int) $resumen['sesiones_mes'] ?></span>
                <span class="stat-card-label">Sesiones del mes</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div class="stat-card-info">
                <span class="stat-card-value cal-stat-hours"><?= htmlspecialchars($resumen['horas_mes']) ?></span>
                <span class="stat-card-label">Horas del mes<?= $resumen['fichas_mes'] ? ' · ' . (int) $resumen['fichas_mes'] . ' fichas' : '' ?></span>
            </div>
        </div>
    </div>

    <div class="cal-layout">
        <div class="card cal-card">
            <div class="cal-card-tools">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="search" id="calBuscar" placeholder="Buscar ficha, competencia o especialidad" aria-label="Buscar en mi horario">
                </div>
                <?php if ($leyenda): ?>
                <div class="cal-legend">
                    <?php foreach ($leyenda as $item): ?>
                        <span class="cal-legend-item">
                            <i style="background: <?= htmlspecialchars($item['color']) ?>"></i>
                            <?= htmlspecialchars($item['codigo']) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="cal-week-wrap solo-semana">
                <div class="cal-week-scroll">
                    <div class="cal-week" style="--hours: <?= (int) ($horaHasta - $horaDesde) ?>; --hour-px: <?= (int) $hourPx ?>px;">
                        <div class="cal-corner"></div>
                        <?php foreach ($diasSemana as $dia): ?>
                            <button type="button" class="cal-day-head <?= $dia['esHoy'] ? 'is-today' : '' ?>" data-fecha="<?= htmlspecialchars($dia['fecha']) ?>" data-label="<?= htmlspecialchars($dia['etiqueta']) ?>">
                                <span><?= htmlspecialchars($dia['nombre']) ?></span>
                                <strong><?= htmlspecialchars($dia['numero']) ?></strong>
                            </button>
                        <?php endforeach; ?>
                        <div class="cal-time-col">
                            <?php for ($h = $horaDesde; $h < $horaHasta; $h++): ?>
                                <div class="cal-hour"><span><?= sprintf('%02d:00', $h) ?></span></div>
                            <?php endfor; ?>
                        </div>
                        <?php foreach ($diasSemana as $dia): ?>
                            <div class="cal-day-col <?= $dia['esHoy'] ? 'is-today' : '' ?>" data-fecha="<?= htmlspecialchars($dia['fecha']) ?>">
                                <div class="cal-day-grid">
                                    <?php foreach ($dia['sesiones'] as $s): ?>
                                        <button type="button"
                                            class="cal-event <?= $s['estado'] === 'Cancelado' ? 'is-cancelado' : '' ?>"
                                            data-id="<?= (int) $s['id'] ?>"
                                            data-fecha="<?= htmlspecialchars($s['fecha']) ?>"
                                            data-search="<?= htmlspecialchars($s['busqueda']) ?>"
                                            style="top:<?= $s['_top'] ?>px;height:<?= $s['_height'] ?>px;left:<?= $s['_left'] ?>%;width:<?= $s['_width'] ?>%;--ev:<?= htmlspecialchars($s['color']) ?>"
                                            title="<?= htmlspecialchars($s['hora_ini_fmt'] . ' Ficha ' . $s['codigo_ficha']) ?>">
                                            <span class="cal-event-time"><?= htmlspecialchars($s['hora_ini_fmt']) ?></span>
                                            <strong>Ficha <?= htmlspecialchars($s['codigo_ficha']) ?></strong>
                                            <?php if (!empty($s['especialidad'])): ?>
                                                <span class="cal-event-meta"><?= htmlspecialchars($s['especialidad']) ?></span>
                                            <?php elseif (!empty($s['nombre_programa'])): ?>
                                                <span class="cal-event-meta"><?= htmlspecialchars($s['nombre_programa']) ?></span>
                                            <?php endif; ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="cal-month-wrap solo-mes">
                <div class="cal-month-head">
                    <?php foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $nombre): ?>
                        <span><?= $nombre ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="cal-month-grid">
                    <?php foreach ($celdasMes as $celda): ?>
                        <div class="cal-month-day <?= $celda['enMes'] ? '' : 'is-out' ?> <?= $celda['esHoy'] ? 'is-today' : '' ?>" data-fecha="<?= htmlspecialchars($celda['fecha']) ?>" data-en-mes="<?= $celda['enMes'] ? '1' : '0' ?>">
                            <a href="<?= htmlspecialchars($celda['urlDia']) ?>" class="cal-day-num" data-fecha="<?= htmlspecialchars($celda['fecha']) ?>" data-label="<?= htmlspecialchars($celda['etiqueta']) ?>"><?= htmlspecialchars($celda['numero']) ?></a>
                            <?php foreach (array_slice($celda['sesiones'], 0, 3) as $s): ?>
                                <button type="button"
                                    class="cal-chip <?= $s['estado'] === 'Cancelado' ? 'is-cancelado' : '' ?>"
                                    data-id="<?= (int) $s['id'] ?>"
                                    data-fecha="<?= htmlspecialchars($s['fecha']) ?>"
                                    data-search="<?= htmlspecialchars($s['busqueda']) ?>"
                                    style="--ev: <?= htmlspecialchars($s['color']) ?>">
                                    <?= htmlspecialchars($s['hora_ini_fmt']) ?> · <?= htmlspecialchars($s['codigo_ficha']) ?>
                                </button>
                            <?php endforeach; ?>
                            <?php if ($celda['extra'] > 0): ?>
                                <button type="button" class="cal-more" data-fecha="<?= htmlspecialchars($celda['fecha']) ?>" data-label="<?= htmlspecialchars($celda['etiqueta']) ?>">+<?= (int) $celda['extra'] ?> más</button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <p class="cal-empty" id="calSinResultados" hidden>No hay sesiones que coincidan con el filtro.</p>
        </div>

        <aside class="card cal-detail" id="calDetalle">
            <div class="cal-detail-head">
                <h3 id="calDetalleTitulo">Detalle</h3>
                <button type="button" class="btn btn-sm btn-secondary" id="calDetalleCerrar" hidden>Cerrar</button>
            </div>
            <div id="calDetalleCuerpo">
                <p class="text-muted">Selecciona un bloque del calendario para ver ficha, horario y competencia.</p>
            </div>
        </aside>
    </div>

    <div class="card cal-agenda">
        <h3 class="cal-agenda-title"><i class="fas fa-list-ul"></i> Agenda del periodo</h3>
        <?php if ($agenda): ?>
            <?php foreach ($agenda as $dia): ?>
                <section class="agenda-dia <?= $dia['enSemana'] ? 'solo-semana' : '' ?> <?= $dia['enMes'] ? 'solo-mes' : '' ?>" data-fecha="<?= htmlspecialchars($dia['fecha']) ?>">
                    <h4><?= htmlspecialchars($dia['etiqueta']) ?></h4>
                    <?php foreach ($dia['sesiones'] as $s): ?>
                        <button type="button" class="agenda-item" data-id="<?= (int) $s['id'] ?>" data-fecha="<?= htmlspecialchars($s['fecha']) ?>" data-search="<?= htmlspecialchars($s['busqueda']) ?>">
                            <span class="agenda-bar" style="background: <?= htmlspecialchars($s['color']) ?>"></span>
                            <span class="agenda-time"><?= htmlspecialchars($s['hora_ini_fmt']) ?> - <?= htmlspecialchars($s['hora_fin_fmt']) ?></span>
                            <span class="agenda-main">
                                <strong>Ficha <?= htmlspecialchars($s['codigo_ficha']) ?></strong>
                                <span class="text-muted"><?= htmlspecialchars(trim(($s['nombre_programa'] ?? '') . (!empty($s['especialidad']) ? ' · ' . $s['especialidad'] : ''))) ?></span>
                            </span>
                            <span class="badge <?= $s['estado'] === 'Activo' ? 'badge-green' : ($s['estado'] === 'Finalizada' ? 'badge-blue' : 'badge-inactivo') ?>"><?= htmlspecialchars($s['estado']) ?></span>
                        </button>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <p><?= $fichasFiltro ? 'No tienes sesiones en este periodo.' : 'Aún no hay carga horaria registrada a tu nombre.' ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script type="application/json" id="miHorarioData"><?= $jsonEventos ?></script>
<script src="<?= BASE_URL ?>assets/js/mi-horario.js"></script>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
