<div class="wrap">
    <h1><?php _e('Importar desde GitBook', 'inblue-library'); ?></h1>

    <form method="post" style="margin-bottom:30px">
        <?php wp_nonce_field('inblue_gitbook_import'); ?>
        <p>
            <button type="submit" name="import_gitbook" class="button button-primary">
                <?php _e('Importar todas las categorías desde GitBook', 'inblue-library'); ?>
            </button>
            <button type="submit" name="clear_gitbook_cache" class="button">
                <?php _e('Limpiar caché', 'inblue-library'); ?>
            </button>
        </p>
        <p class="description">
            <?php _e('Obtiene las páginas de los libros en lotes de 10 en paralelo. Las portadas NO se extraen durante la importación: usa el botón de abajo para las portadas.', 'inblue-library'); ?>
        </p>
    </form>

    <?php if ($results) : ?>
        <hr>
        <h2><?php _e('Resultados de la importación', 'inblue-library'); ?></h2>
        <table class="widefat striped" style="max-width:500px;margin-bottom:15px">
            <tr><td><strong><?php _e('Total procesados', 'inblue-library'); ?></strong></td><td><?php echo intval($results['total']); ?></td></tr>
            <tr><td><strong><?php _e('Creados', 'inblue-library'); ?></strong></td><td><?php echo intval($results['created']); ?></td></tr>
            <tr><td><strong><?php _e('Actualizados', 'inblue-library'); ?></strong></td><td><?php echo intval($results['updated']); ?></td></tr>
            <tr><td><strong><?php _e('Errores', 'inblue-library'); ?></strong></td><td><?php echo intval($results['errors']); ?></td></tr>
        </table>
        <?php if (!empty($results['log'])) : ?>
            <details>
                <summary><?php _e('Ver detalles', 'inblue-library'); ?></summary>
                <div style="max-height:300px;overflow:auto;background:#f0f0f1;padding:10px;border:1px solid #ccc;margin-top:8px">
                    <?php foreach ($results['log'] as $entry) : ?>
                        <p style="margin:2px 0;font-size:12px;color:<?php echo $entry['type'] === 'error' ? '#d63638' : ($entry['type'] === 'warning' ? '#dba617' : '#2271b1'); ?>">
                            <?php echo esc_html($entry['time'] . ' - ' . $entry['message']); ?>
                        </p>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endif; ?>
    <?php endif; ?>

    <hr>

    <h2><?php _e('Extraer portadas', 'inblue-library'); ?></h2>
    <p><?php _e('Extrae las imágenes de portada desde las URLs de los PDF para todos los libros que aún no tienen portada.', 'inblue-library'); ?></p>
    <form method="post">
        <?php wp_nonce_field('inblue_gitbook_import'); ?>
        <p>
            <button type="submit" name="extract_covers" class="button button-secondary">
                <?php _e('Extraer portadas de todos los libros', 'inblue-library'); ?>
            </button>
        </p>
        <p class="description">
            <?php _e('Esto puede tardar un rato: intenta descargar las miniaturas desde Google Drive para cada libro. Los libros que ya tienen portada se omiten.', 'inblue-library'); ?>
        </p>
    </form>

    <?php if ($cover_results) : ?>
        <h2><?php _e('Resultados de extracción de portadas', 'inblue-library'); ?></h2>
        <table class="widefat striped" style="max-width:500px;margin-bottom:15px">
            <tr><td><strong><?php _e('Total de libros', 'inblue-library'); ?></strong></td><td><?php echo intval($cover_results['total']); ?></td></tr>
            <tr><td><strong style="color:#46b450"><?php _e('Portada extraída', 'inblue-library'); ?></strong></td><td><?php echo intval($cover_results['success']); ?></td></tr>
            <tr><td><strong><?php _e('Omitidos (ya tienen portada / sin PDF)', 'inblue-library'); ?></strong></td><td><?php echo intval($cover_results['skipped']); ?></td></tr>
            <tr><td><strong style="color:#d63638"><?php _e('Errores', 'inblue-library'); ?></strong></td><td><?php echo intval($cover_results['errors']); ?></td></tr>
        </table>
        <?php if (!empty($cover_results['log'])) : ?>
            <details>
                <summary><?php _e('Ver detalles', 'inblue-library'); ?></summary>
                <div style="max-height:300px;overflow:auto;background:#f0f0f1;padding:10px;border:1px solid #ccc;margin-top:8px">
                    <?php foreach ($cover_results['log'] as $entry) : ?>
                        <p style="margin:2px 0;font-size:12px;color:<?php echo $entry['type'] === 'error' ? '#d63638' : ($entry['type'] === 'warning' ? '#dba617' : '#46b450'); ?>">
                            <?php echo esc_html($entry['time'] . ' - ' . $entry['message']); ?>
                        </p>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endif; ?>
    <?php endif; ?>
</div>
