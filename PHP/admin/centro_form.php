<?php
/**
 * CRUD de centros - CREAR (sin ?id) y EDITAR (?id=N). Solo administrador.
 *
 * Campos: codigo (unico), nombre y comuna. La REGION no se guarda en Centro:
 * se deduce de la comuna (3FN, nombreComuna -> nombreRegion); el
 * selector agrupa las comunas por region.
 * Unicidad del codigo: PHP (mensaje claro) + uq_centro_codigo (BD).
 * PRG: tras guardar, redirect al detalle del centro.
 */
require __DIR__ . '/../includes/bootstrap.php';

requerir_rol('admin');
if (es_post()) {
    csrf_verificar();
}

$id = entero_positivo(es_post() ? post('id') : get('id'));
$editar = $id > 0;
$centro = $editar ? centro_con_dependencias($id) : null;
if ($editar && $centro === null) {
    pagina_error(404, 'Centro no encontrado', 'El centro no existe.');
}

$codigo   = $centro['codigoCentro'] ?? '';
$nombre   = $centro['nombreCentro'] ?? '';
$idComuna = (int) ($centro['idComuna'] ?? 0);
$errores  = [];

if (es_post()) {
    $codigo   = normalizar_codigo_centro(post('codigo'));
    $nombre   = normalizar_nombre(post('nombre'));
    $idComuna = entero_positivo(post('comuna'));
    $errores  = errores_centro($codigo, $nombre, $idComuna, $id);

    if (!$errores) {
        try {
            if ($editar) {
                q('UPDATE Centro SET codigoCentro = ?, nombreCentro = ?, idComuna = ? WHERE idCentro = ?',
                  [$codigo, $nombre, $idComuna, $id]);
                flash('success', 'Centro ' . $codigo . ' actualizado.');
            } else {
                q('INSERT INTO Centro (codigoCentro, nombreCentro, idComuna) VALUES (?, ?, ?)', [$codigo, $nombre, $idComuna]);
                $id = (int) db()->lastInsertId();
                flash('success', 'Centro ' . $codigo . ' creado.');
            }
            redirect('admin/centro.php?id=' . $id);
        } catch (PDOException $e) {
            $errores[] = mensaje_error_bd($e);      // p. ej. uq_centro_codigo si otro admin lo creo recien
        }
    }
    http_response_code(422);
}

encabezado($editar ? 'Editar centro' : 'Nuevo centro', 'admin/centros.php');
?>
<a class="small" href="<?= e(url('admin/centros.php')) ?>">&larr; Centros</a>
<h1 class="h3 mt-2 mb-3"><?= $editar ? 'Editar centro ' . e($centro['codigoCentro']) : 'Nuevo centro' ?></h1>
<?php lista_errores($errores); ?>
<div class="card shadow-sm" style="max-width: 640px">
    <div class="card-body">
        <form method="post" action="<?= e(url('admin/centro_form.php')) ?>" novalidate>
            <?= csrf_campo() ?>
            <?php if ($editar): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
            <div class="mb-3">
                <label for="codigo" class="form-label">Codigo <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="codigo" name="codigo" maxlength="10" value="<?= e($codigo) ?>" required placeholder="Ej.: CM10">
                <div class="form-text">Unico. 2 a 10 caracteres: letras, digitos o guion (se guarda en mayusculas).</div>
            </div>
            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="nombre" name="nombre" maxlength="100" value="<?= e($nombre) ?>" required>
            </div>
            <div class="mb-3">
                <label for="comuna" class="form-label">Comuna y region <span class="text-danger">*</span></label>
                <select class="form-select" id="comuna" name="comuna" required>
                    <option value="">Selecciona…</option>
                    <?php foreach (catalogo_comunas() as $region => $comunas): ?>
                        <optgroup label="<?= e($region) ?>">
                            <?php foreach ($comunas as $idC => $nombreC): ?>
                                <option value="<?= (int) $idC ?>" <?= (int) $idC === $idComuna ? 'selected' : '' ?>><?= e($nombreC) ?> (<?= e($region) ?>)</option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">La region queda determinada por la comuna.</div>
            </div>
            <button type="submit" class="btn btn-primary"><?= $editar ? 'Guardar cambios' : 'Crear centro' ?></button>
            <a class="btn btn-link" href="<?= e(url($editar ? 'admin/centro.php?id=' . $id : 'admin/centros.php')) ?>">Cancelar</a>
        </form>
    </div>
</div>
<?php pie();
