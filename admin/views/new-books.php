<div class="wrap">
    <h1><?php _e('Libros nuevos', 'inblue-library'); ?></h1>
    <p><?php _e('Consulta GitBook y compara con la biblioteca para detectar los libros que aún no están importados.', 'inblue-library'); ?></p>

    <form method="post" style="margin-bottom:20px">
        <?php wp_nonce_field('inblue_gitbook_availability'); ?>
        <p>
            <button type="submit" name="check_gitbook_availability" class="button button-primary">
                <?php _e('Comprobar libros nuevos disponibles', 'inblue-library'); ?>
            </button>
        </p>
    </form>

    <?php if ($availability) : ?>
        <table class="widefat striped" style="max-width:600px;margin-bottom:15px">
            <tr><td><strong><?php _e('Libros en GitBook', 'inblue-library'); ?></strong></td><td><?php echo intval($availability['total_remote']); ?></td></tr>
            <tr><td><strong style="color:#46b450"><?php _e('Nuevos disponibles', 'inblue-library'); ?></strong></td><td><?php echo count($availability['new']); ?></td></tr>
            <tr><td><strong><?php _e('Ya importados', 'inblue-library'); ?></strong></td><td><?php echo count($availability['existing']); ?></td></tr>
            <tr><td><strong style="color:#d63638"><?php _e('Errores de conexión', 'inblue-library'); ?></strong></td><td><?php echo intval($availability['errors']); ?></td></tr>
        </table>

        <?php if (!empty($availability['new'])) : ?>
            <form method="post">
                <?php wp_nonce_field('inblue_gitbook_availability'); ?>
                <h2><?php _e('Libros nuevos listos para importar', 'inblue-library'); ?></h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th style="width:40px"><input type="checkbox" id="inblue-select-all"></th>
                            <th><?php _e('Título', 'inblue-library'); ?></th>
                            <th><?php _e('Categoría', 'inblue-library'); ?></th>
                            <th><?php _e('URL', 'inblue-library'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($availability['new'] as $idx => $item) : ?>
                            <tr>
                                <td><input type="checkbox" name="selected_books[]" value="<?php echo esc_attr($idx); ?>" checked></td>
                                <td>
                                    <strong><?php echo esc_html($item['title']); ?></strong>
                                    <?php if (!empty($item['subtitle'])) : ?>
                                        <br><em><?php echo esc_html($item['subtitle']); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($item['category_name']); ?></td>
                                <td><a href="<?php echo esc_url($item['url']); ?>" target="_blank"><?php echo esc_html($item['url']); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p>
                    <button type="submit" name="import_selected_books" class="button button-primary">
                        <?php echo esc_html(sprintf(__('Importar %d libros nuevos', 'inblue-library'), count($availability['new']))); ?>
                    </button>
                </p>
            </form>
        <?php else : ?>
            <p><?php _e('No hay libros nuevos: toda la biblioteca de GitBook ya está importada.', 'inblue-library'); ?></p>
        <?php endif; ?>

        <?php if (!empty($availability['existing'])) : ?>
            <details>
                <summary><?php echo esc_html(sprintf(__('Ver %d libros ya importados', 'inblue-library'), count($availability['existing']))); ?></summary>
                <div style="max-height:250px;overflow:auto;background:#f0f0f1;padding:10px;border:1px solid #ccc;margin-top:8px">
                    <ul style="margin:0">
                        <?php foreach ($availability['existing'] as $item) : ?>
                            <li style="font-size:12px;margin:2px 0">
                                <?php echo esc_html($item['title'] . ' — ' . $item['category_name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </details>
        <?php endif; ?>

        <?php if (!empty($availability['log'])) : ?>
            <details>
                <summary><?php _e('Ver detalles de la comprobación', 'inblue-library'); ?></summary>
                <div style="max-height:250px;overflow:auto;background:#f0f0f1;padding:10px;border:1px solid #ccc;margin-top:8px">
                    <?php foreach ($availability['log'] as $entry) : ?>
                        <p style="margin:2px 0;font-size:12px;color:<?php echo $entry['type'] === 'error' ? '#d63638' : '#2271b1'; ?>">
                            <?php echo esc_html($entry['time'] . ' - ' . $entry['message']); ?>
                        </p>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($import_new_results) : ?>
        <hr>
        <h2><?php _e('Resultados de la importación de libros nuevos', 'inblue-library'); ?></h2>
        <table class="widefat striped" style="max-width:500px;margin-bottom:15px">
            <tr><td><strong><?php _e('Total procesados', 'inblue-library'); ?></strong></td><td><?php echo intval($import_new_results['total']); ?></td></tr>
            <tr><td><strong style="color:#46b450"><?php _e('Creados', 'inblue-library'); ?></strong></td><td><?php echo intval($import_new_results['created']); ?></td></tr>
            <tr><td><strong><?php _e('Actualizados', 'inblue-library'); ?></strong></td><td><?php echo intval($import_new_results['updated']); ?></td></tr>
            <tr><td><strong><?php _e('Omitidos', 'inblue-library'); ?></strong></td><td><?php echo intval($import_new_results['skipped']); ?></td></tr>
            <tr><td><strong style="color:#d63638"><?php _e('Errores', 'inblue-library'); ?></strong></td><td><?php echo intval($import_new_results['errors']); ?></td></tr>
        </table>
        <?php if (!empty($import_new_results['log'])) : ?>
            <details>
                <summary><?php _e('Ver detalles', 'inblue-library'); ?></summary>
                <div style="max-height:300px;overflow:auto;background:#f0f0f1;padding:10px;border:1px solid #ccc;margin-top:8px">
                    <?php foreach ($import_new_results['log'] as $entry) : ?>
                        <p style="margin:2px 0;font-size:12px;color:<?php echo $entry['type'] === 'error' ? '#d63638' : ($entry['type'] === 'warning' ? '#dba617' : '#2271b1'); ?>">
                            <?php echo esc_html($entry['time'] . ' - ' . $entry['message']); ?>
                        </p>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script>
(function() {
    var toggle = document.getElementById('inblue-select-all');
    if (toggle) {
        toggle.addEventListener('change', function() {
            var checked = toggle.checked;
            document.querySelectorAll('input[name="selected_books[]"]').forEach(function(cb) {
                cb.checked = checked;
            });
        });
    }
})();
</script>
