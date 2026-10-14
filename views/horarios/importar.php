<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="toolbar">
    <div class="toolbar-left">
        <a href="<?= BASE_URL ?>?action=horarios" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver a Horarios
        </a>
    </div>
</div>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header" style="margin-bottom: 25px;">
        <h2 style="font-size: 1.3rem; margin-bottom: 8px;">
            <i class="fas fa-file-excel" style="color: var(--accent-green); margin-right: 8px;"></i>
            Importación Masiva de Horarios Semestrales
        </h2>
        <p class="text-muted" style="font-size: 0.9rem;">
            Sube el archivo Excel oficial con la grilla de calendario del semestre. El sistema detectará automáticamente la ficha, los instructores por nombre y las fechas y bloques de clase.
        </p>
    </div>

    <!-- Guía y Reglas de Importación -->
    <div style="background: var(--bg-darker, #0f172a); border: 1px solid var(--border-color, #334155); border-radius: 8px; padding: 18px 20px; margin-bottom: 25px;">
        <h4 style="font-size: 0.95rem; color: var(--accent-blue, #38bdf8); margin-bottom: 10px;">
            <i class="fas fa-info-circle"></i> ¿Cómo funciona el procesamiento del Excel?
        </h4>
        <ul style="margin: 0; padding-left: 20px; font-size: 0.88rem; color: #cbd5e1; line-height: 1.6;">
            <li><strong>Detección de Ficha:</strong> El sistema busca automáticamente el código numérico de 7 dígitos en la cabecera (ej. <code>3064749</code>). La ficha debe estar previamente registrada en el sistema.</li>
            <li><strong>Búsqueda de Instructores:</strong> Se extrae el nombre completo de la primera línea de cada bloque y se busca en la base de datos de instructores.</li>
            <li><strong>Bloques y Fechas:</strong> Se interpretan las celdas combinadas de acuerdo a la jornada (Mañana, Tarde, Noche) y los días del calendario.</li>
            <li><strong>Control de Duplicados:</strong> Si una sesión ya fue registrada anteriormente, se omitirá automáticamente para evitar repeticiones.</li>
        </ul>
    </div>

    <!-- Formulario de Carga -->
    <form action="<?= BASE_URL ?>?action=horarios/procesar" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <div class="form-group" style="margin-bottom: 25px;">
            <label class="form-label" style="font-weight: 600; display: block; margin-bottom: 8px;">
                Seleccionar Archivo Excel (.xlsx / .xls) <span style="color: var(--accent-red, #ef4444); font-weight: bold;">*</span>
            </label>
            <div style="border: 2px dashed var(--border-color, #334155); border-radius: 8px; padding: 30px; text-align: center; background: rgba(255,255,255,0.02); transition: border-color 0.2s;" id="dropArea">
                <i class="fas fa-cloud-arrow-up" style="font-size: 40px; color: var(--accent-green); margin-bottom: 12px; display: block;"></i>
                <input type="file" name="archivo" id="archivoExcel" accept=".xlsx, .xls" required style="display: none;" onchange="updateFileName(this)">
                <label for="archivoExcel" class="btn btn-secondary" style="cursor: pointer; display: inline-block;">
                    <i class="fas fa-folder-open"></i> Explorar Archivo
                </label>
                <div id="fileSelectedName" style="margin-top: 12px; font-size: 0.9rem; color: var(--accent-green); font-weight: 500;">
                    Ningún archivo seleccionado
                </div>
                <span class="text-muted" style="font-size: 0.8rem; display: block; margin-top: 6px;">Formatos admitidos: .xlsx, .xls (Máximo 10 MB)</span>
            </div>
        </div>

        <div class="form-actions" style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 30px;">
            <a href="<?= BASE_URL ?>?action=horarios" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary" id="btnSubmit">
                <i class="fas fa-upload"></i> Procesar e Importar
            </button>
        </div>
    </form>
</div>

<script>
    function updateFileName(input) {
        const label = document.getElementById('fileSelectedName');
        if (input.files && input.files[0]) {
            label.innerHTML = '<i class="fas fa-file-excel"></i> ' + input.files[0].name + ' (' + (input.files[0].size / 1024).toFixed(1) + ' KB)';
            label.style.color = '#10b981';
        } else {
            label.textContent = 'Ningún archivo seleccionado';
            label.style.color = '#94a3b8';
        }
    }
</script>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>