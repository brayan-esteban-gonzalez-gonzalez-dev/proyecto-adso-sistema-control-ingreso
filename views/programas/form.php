<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<?php
$programaId  = $programa['id'] ?? 0;
$codigo      = $programa['codigo'] ?? '';
$nombre      = $programa['nombre'] ?? '';
$descripcion = $programa['descripcion'] ?? '';
?>

<div class="card" style="max-width: 640px;">
    <form method="POST" action="<?= BASE_URL ?>?action=programas/guardar">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="hidden" name="id" value="<?= (int) $programaId ?>">

        <div class="form-group">
            <label class="form-label" for="codigo">Código *</label>
            <input type="text" id="codigo" name="codigo" class="form-control"
                   value="<?= htmlspecialchars($codigo) ?>" required maxlength="20"
                   placeholder="Ej: 228118">
        </div>

        <div class="form-group">
            <label class="form-label" for="nombre">Nombre *</label>
            <input type="text" id="nombre" name="nombre" class="form-control"
                   value="<?= htmlspecialchars($nombre) ?>" required maxlength="100"
                   placeholder="Ej: Análisis y Desarrollo de Software">
        </div>

        <div class="form-group">
            <label class="form-label" for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" class="form-control" maxlength="2000"
                      placeholder="Descripción del programa"><?= htmlspecialchars($descripcion) ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <?= $programa ? 'Actualizar' : 'Crear' ?> Programa
            </button>
            <a href="<?= BASE_URL ?>?action=programas" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancelar
            </a>
        </div>
    </form>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
