<div class="wrap">
    <h1><?php _e('Importar / Exportar', 'inblue-library'); ?></h1>

    <h2><?php _e('Exportar', 'inblue-library'); ?></h2>
    <p><?php _e('Elige qué quieres incluir en el backup. El ZIP con portadas incluye las imágenes de cada libro.', 'inblue-library'); ?></p>

    <form method="post">
        <?php wp_nonce_field('inblue_export_zip'); ?>
        <table class="form-table">
            <tr>
                <th><?php _e('Contenido a exportar', 'inblue-library'); ?></th>
                <td>
                    <label style="display:block;margin-bottom:6px;">
                        <input type="checkbox" name="export_data" value="1" checked>
                        <strong><?php _e('Datos y metadatos', 'inblue-library'); ?></strong>
                        — <?php _e('título, autores, fechas, ISBN, DOI, DOI PDF, PDF URL, categorías, etc.', 'inblue-library'); ?>
                    </label>
                    <label style="display:block;">
                        <input type="checkbox" name="export_covers" value="1" checked>
                        <strong><?php _e('Portadas', 'inblue-library'); ?></strong>
                        — <?php _e('imágenes de portada de cada libro (carpeta covers/ del ZIP).', 'inblue-library'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="export_since"><?php _e('Solo modificados desde', 'inblue-library'); ?></label></th>
                <td>
                    <input type="date" name="export_since" id="export_since" value="">
                    <p class="description"><?php _e('Déjalo vacío para exportar todo. Con una fecha se exportan solo los libros modificados desde ese día (sincronización incremental).', 'inblue-library'); ?></p>
                </td>
            </tr>
        </table>
        <p>
            <button type="submit" name="export_zip_submit" class="button button-primary">
                <?php _e('Exportar', 'inblue-library'); ?>
            </button>
        </p>
    </form>

    <hr>

    <h2><?php _e('Importar', 'inblue-library'); ?></h2>
    <p><?php _e('Sube un archivo ZIP (backup) o CSV para importar libros. El ZIP debe tener las mismas columnas que la exportación.', 'inblue-library'); ?></p>
    <p><?php _e('Los libros se emparejan por URL de GitBook, DOI o ISBN para evitar duplicados.', 'inblue-library'); ?></p>

    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('inblue_import_csv'); ?>
        <table class="form-table">
            <tr>
                <th><label for="import_csv"><?php _e('Archivo CSV o ZIP', 'inblue-library'); ?></label></th>
                <td>
                    <input type="file" name="import_csv" id="import_csv" accept=".csv,.zip" required>
                </td>
            </tr>
            <tr>
                <th><?php _e('Qué importar', 'inblue-library'); ?></th>
                <td>
                    <label style="display:block;margin-bottom:6px;">
                        <input type="checkbox" name="import_data" value="1" checked>
                        <strong><?php _e('Datos y metadatos', 'inblue-library'); ?></strong>
                        — <?php _e('crea/actualiza los libros con los datos del archivo.', 'inblue-library'); ?>
                    </label>
                    <label style="display:block;">
                        <input type="checkbox" name="import_covers" value="1" checked>
                        <strong><?php _e('Portadas', 'inblue-library'); ?></strong>
                        — <?php _e('asigna las imágenes de portada (solo si el archivo las incluye).', 'inblue-library'); ?>
                    </label>
                    <p class="description"><?php _e('Ejemplo: si desmarcas "Datos", solo se aplicarán las portadas a los libros existentes.', 'inblue-library'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php _e('Modo de importación', 'inblue-library'); ?></th>
                <td>
                    <label style="display:block;margin-bottom:6px;">
                        <input type="checkbox" name="update_only" value="1">
                        <strong><?php _e('Solo actualizar libros existentes', 'inblue-library'); ?></strong>
                        — <?php _e('los libros del archivo que no existan en este sitio se omiten (no se crean).', 'inblue-library'); ?>
                    </label>
                    <p class="description"><?php _e('Recomendado para pasar los cambios de local a producción sin duplicar títulos.', 'inblue-library'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php _e('Importar desde cero', 'inblue-library'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="replace_library" id="replace_library" value="1" onclick="return confirm('<?php echo esc_js(__('Se borrarán TODOS los libros actuales y se importará el CSV desde cero. ¿Continuar?', 'inblue-library')); ?>');">
                        <?php _e('Borrar todos los libros actuales antes de importar.', 'inblue-library'); ?>
                    </label>
                    <p class="description"><?php _e('Útil para restaurar un backup en una instalación vacía o reemplazar toda la biblioteca.', 'inblue-library'); ?></p>
                </td>
            </tr>
        </table>
        <p>
            <button type="submit" name="import_csv_submit" class="button button-primary">
                <?php _e('Importar', 'inblue-library'); ?>
            </button>
        </p>
    </form>

    <?php if ($import_results) : ?>
        <hr>

        <h2><?php _e('Resultados de la importación', 'inblue-library'); ?></h2>
        <table class="widefat striped" style="max-width:500px">
            <tr><td><strong><?php _e('Total procesados', 'inblue-library'); ?></strong></td><td><?php echo intval($import_results['total']); ?></td></tr>
            <tr><td><strong><?php _e('Creados', 'inblue-library'); ?></strong></td><td><?php echo intval($import_results['created']); ?></td></tr>
            <tr><td><strong><?php _e('Actualizados', 'inblue-library'); ?></strong></td><td><?php echo intval($import_results['updated']); ?></td></tr>
            <tr><td><strong><?php _e('Errores', 'inblue-library'); ?></strong></td><td><?php echo intval($import_results['errors']); ?></td></tr>
        </table>

        <?php if (!empty($import_results['log'])) : ?>
            <h3><?php _e('Detalles', 'inblue-library'); ?></h3>
            <div style="max-height:400px;overflow:auto;background:#f0f0f1;padding:10px;border:1px solid #ccc;">
                <?php foreach ($import_results['log'] as $msg) : ?>
                    <p style="margin:2px 0;font-size:12px;"><?php echo esc_html($msg); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
