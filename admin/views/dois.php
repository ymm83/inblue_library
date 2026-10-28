<?php
if (!defined('ABSPATH')) exit;

$base_url = admin_url('edit.php?post_type=book&amp;page=inblue-library-dois');
$statuses = array(
    'all'      => __('Todos', 'inblue-library'),
    'match'    => __('✓ Coinciden', 'inblue-library'),
    'mismatch' => __('⚠ No coinciden', 'inblue-library'),
    'missing'  => __('⚠ Incompletos', 'inblue-library'),
    'empty'    => __('— Sin DOI', 'inblue-library'),
);
?>
<div class="wrap inblue-dois-page">
    <h1>
        <?php _e('DOIs', 'inblue-library'); ?>
        <span class="subtitle" style="font-weight:400;color:#646970">
            <?php printf(esc_html(_n('%s libro', '%s libros', $grand_total, 'inblue-library')), number_format_i18n($grand_total)); ?>
        </span>
    </h1>

    <?php if ($counts['mismatch'] > 0) : ?>
        <div class="notice notice-error inline" style="border-left-width:4px">
            <p>
                <strong>
                    <?php
                    printf(
                        esc_html(_n('⚠ %s libro con DOIs que no coinciden', '⚠ %s libros con DOIs que no coinciden', $counts['mismatch'], 'inblue-library')),
                        number_format_i18n($counts['mismatch'])
                    );
                    ?>
                </strong>
                <?php _e('El DOI de la página del libro y el de la página del PDF en GitBook son distintos. Revísalos y guárdalos.', 'inblue-library'); ?>
            </p>
        </div>
    <?php elseif ($counts['match'] > 0) : ?>
        <div class="notice notice-success inline" style="border-left-width:4px">
            <p>
                <strong>
                    <?php
                    printf(
                        esc_html(_n('✓ %s libro con DOIs que coinciden', '✓ %s libros con DOIs que coinciden', $counts['match'], 'inblue-library')),
                        number_format_i18n($counts['match'])
                    );
                    ?>
                </strong>
                <?php _e('No hay discrepancias entre el DOI de la página y el del PDF en GitBook.', 'inblue-library'); ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="inblue-doi-summary">
        <?php
        $cards = array(
            'match'    => array('label' => __('✓ Coinciden', 'inblue-library'), 'note' => __('DOI idénticos', 'inblue-library')),
            'mismatch' => array('label' => __('⚠ No coinciden', 'inblue-library'), 'note' => __('Discrepancias', 'inblue-library')),
            'missing'  => array('label' => __('⚠ Incompletos', 'inblue-library'), 'note' => __('Falta uno', 'inblue-library')),
            'empty'    => array('label' => __('— Sin DOI', 'inblue-library'), 'note' => __('Sin asignar', 'inblue-library')),
        );
        foreach ($cards as $key => $card) :
            $url = add_query_arg(array('doi_status' => $key), $base_url);
        ?>
            <a class="inblue-doi-card inblue-doi-card-<?php echo esc_attr($key); ?><?php echo $filter === $key ? ' is-current' : ''; ?>"
               href="<?php echo esc_url($url); ?>">
                <span class="inblue-doi-card-num"><?php echo number_format_i18n($counts[$key]); ?></span>
                <span class="inblue-doi-card-label"><?php echo esc_html($card['label']); ?></span>
                <span class="inblue-doi-card-note"><?php echo esc_html($card['note']); ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php
    $source_label = isset(Inblue_Library_Admin::DOI_PDF_SOURCES[$doi_pdf_source])
        ? Inblue_Library_Admin::DOI_PDF_SOURCES[$doi_pdf_source]
        : $doi_pdf_source;
    $next_offset        = $fill_result ? (int) $fill_result['next_offset'] : 0;
    $page_fill_offset   = $page_fill_result ? (int) $page_fill_result['next_offset'] : 0;
    $page_source_label  = __('Página de GitBook', 'inblue-library');
    ?>

    <div class="inblue-doi-fill-bar">
        <form method="post">
            <?php wp_nonce_field('inblue_doi_fill'); ?>
            <input type="hidden" name="fill_offset" value="<?php echo (int) $next_offset; ?>">
            <input type="hidden" name="page_offset" value="<?php echo (int) $page_fill_offset; ?>">

            <button type="submit" name="fill_doi_page" class="button">
                <?php if ($page_fill_result && $page_fill_result['remaining'] > 0) : ?>
                    <?php printf(
                        esc_html__('Continuar DOI de la página: quedan %s', 'inblue-library'),
                        number_format_i18n($page_fill_result['remaining'])
                    ); ?>
                <?php else : ?>
                    <?php _e('Rellenar DOI de la página (lotes de 10)', 'inblue-library'); ?>
                <?php endif; ?>
            </button>

            <button type="submit" name="fill_doi_pdf" class="button">
                <?php if ($fill_result && $fill_result['remaining'] > 0) : ?>
                    <?php printf(
                        esc_html__('Continuar: quedan %s por procesar', 'inblue-library'),
                        number_format_i18n($fill_result['remaining'])
                    ); ?>
                <?php else : ?>
                    <?php _e('Rellenar DOI PDF (lotes de 10)', 'inblue-library'); ?>
                <?php endif; ?>
            </button>

            <label class="inblue-doi-fill-label" for="inblue-doi-pdf-source">
                <?php _e('Origen del DOI PDF:', 'inblue-library'); ?>
            </label>
            <select name="doi_pdf_source" id="inblue-doi-pdf-source">
                <?php foreach (Inblue_Library_Admin::DOI_PDF_SOURCES as $key => $label) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($doi_pdf_source, $key); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="save_doi_source" class="button">
                <?php _e('Guardar origen', 'inblue-library'); ?>
            </button>

            <label class="inblue-doi-fill-label">
                <input type="checkbox" name="fill_overwrite" value="1" <?php checked(! empty($_POST['fill_overwrite'])); ?>>
                <?php _e('Sobrescribir los que ya tienen valor', 'inblue-library'); ?>
            </label>
        </form>

        <?php if ($source_saved) : ?>
            <div class="notice notice-success inline">
                <p><?php printf(
                    esc_html__('Origen del DOI del PDF guardado: %s.', 'inblue-library'),
                    esc_html($source_label)
                ); ?></p>
            </div>
        <?php endif; ?>

        <?php
        $notice_blocks = array();
        if ($page_fill_result) {
            $notice_blocks[] = array(
                'result' => $page_fill_result,
                'label'  => $page_source_label,
                'hint'   => __('Revisa la URL de GitBook en la ficha del libro.', 'inblue-library'),
            );
        }
        if ($fill_result) {
            $notice_blocks[] = array(
                'result' => $fill_result,
                'label'  => $source_label,
                'hint'   => __('Cambia el origen en Configuración o complétalo a mano.', 'inblue-library'),
            );
        }
        foreach ($notice_blocks as $nb) :
            $nr = $nb['result'];
        ?>
            <?php if ($nr['processed'] > 0 && $nr['filled'] > 0 && $nr['failed'] === 0) : ?>
                <div class="notice notice-success inline">
                    <p><?php printf(
                        esc_html__('Se han rellenado %1$d DOI con la fuente "%2$s". Quedan %3$d libros por procesar.', 'inblue-library'),
                        number_format_i18n($nr['filled']),
                        esc_html($nb['label']),
                        number_format_i18n($nr['remaining'])
                    ); ?></p>
                </div>
            <?php elseif ($nr['processed'] > 0 && $nr['filled'] === 0) : ?>
                <div class="notice notice-error inline">
                    <p><?php printf(
                        esc_html__('No se ha podido extraer ningún DOI de los %1$d libros procesados con la fuente "%2$s". El campo queda vacío. %3$s', 'inblue-library'),
                        number_format_i18n($nr['processed']),
                        esc_html($nb['label']),
                        esc_html($nb['hint'])
                    ); ?></p>
                </div>
            <?php elseif ($nr['processed'] > 0) : ?>
                <div class="notice notice-warning inline">
                    <p><?php printf(
                        esc_html__('Procesados %1$d libros: %2$d rellenados y %3$d sin DOI detectable. Quedan %4$d por procesar.', 'inblue-library'),
                        number_format_i18n($nr['processed']),
                        number_format_i18n($nr['filled']),
                        number_format_i18n($nr['failed']),
                        number_format_i18n($nr['remaining'])
                    ); ?></p>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <form method="get" class="inblue-doi-filters">
        <input type="hidden" name="post_type" value="book">
        <input type="hidden" name="page" value="inblue-library-dois">
        <label for="doi_status_filter"><?php _e('Estado:', 'inblue-library'); ?></label>
        <select name="doi_status" id="doi_status_filter">
            <?php foreach ($statuses as $key => $label) : ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($filter, $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ('' !== $search) : ?>
            <input type="hidden" name="s" value="<?php echo esc_attr($search); ?>">
        <?php endif; ?>
        <button type="submit" class="button"><?php _e('Filtrar', 'inblue-library'); ?></button>
        <?php if ('all' !== $filter || '' !== $search) : ?>
            <a class="button" href="<?php echo esc_url($base_url); ?>"><?php _e('Limpiar', 'inblue-library'); ?></a>
        <?php endif; ?>
    </form>

    <form method="get" class="inblue-doi-search" style="margin-left:12px">
        <input type="hidden" name="post_type" value="book">
        <input type="hidden" name="page" value="inblue-library-dois">
        <?php if ('all' !== $filter) : ?>
            <input type="hidden" name="doi_status" value="<?php echo esc_attr($filter); ?>">
        <?php endif; ?>
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Buscar por título o DOI…', 'inblue-library'); ?>" class="regular-text">
        <button type="submit" class="button"><?php _e('Buscar', 'inblue-library'); ?></button>
    </form>

    <?php if (empty($rows)) : ?>
        <div class="notice notice-info inline">
            <p>
                <?php if ('' !== $search || 'all' !== $filter) : ?>
                    <?php _e('Ningún libro coincide con los filtros aplicados.', 'inblue-library'); ?>
                <?php else : ?>
                    <?php _e('No hay libros publicados todavía.', 'inblue-library'); ?>
                <?php endif; ?>
            </p>
        </div>
    <?php else : ?>
        <form method="post" action="<?php echo esc_url(remove_query_arg(array('s', 'doi_status', 'paged'))); ?>">
            <?php wp_nonce_field('inblue_doi_save'); ?>

            <table class="wp-list-table widefat fixed striped inblue-doi-table">
                <thead>
                    <tr>
                        <th class="column-primary"><?php _e('Libro', 'inblue-library'); ?></th>
                        <th><?php _e('DOI de la página', 'inblue-library'); ?></th>
                        <th><?php _e('DOI del PDF en GitBook', 'inblue-library'); ?></th>
                        <th class="inblue-doi-status-col"><?php _e('Estado', 'inblue-library'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <?php $status = $row['status']; ?>
                        <tr class="inblue-doi-row inblue-doi-row-<?php echo esc_attr($status['key']); ?>">
                            <td class="column-primary">
                                <strong>
                                    <a href="<?php echo esc_url(get_edit_post_link($row['id'])); ?>">
                                        <?php echo esc_html($row['title']); ?>
                                    </a>
                                </strong>
                                <span class="row-actions">
                                    <a href="<?php echo esc_url(get_permalink($row['id'])); ?>" target="_blank" rel="noopener">
                                        <?php _e('Ver libro', 'inblue-library'); ?>
                                    </a>
                                </span>
                            </td>
                            <td>
                                <input type="text"
                                       name="dois[<?php echo (int) $row['id']; ?>][page_doi]"
                                       value="<?php echo esc_attr($row['page_doi']); ?>"
                                       placeholder="10.56168/ibl.ed.xxxxxx"
                                       class="regular-text inblue-doi-input"
                                       data-doi-preview>
                            </td>
                            <td>
                                <input type="text"
                                       name="dois[<?php echo (int) $row['id']; ?>][pdf_doi]"
                                       value="<?php echo esc_attr($row['pdf_doi']); ?>"
                                       placeholder="10.56168/ibl.ed.xxxxxx"
                                       class="regular-text inblue-doi-input"
                                       data-doi-preview>
                            </td>
                            <td class="inblue-doi-status-col">
                                <span class="inblue-doi-badge inblue-doi-badge-<?php echo esc_attr($status['key']); ?>"
                                      title="<?php echo esc_attr($status['note']); ?>">
                                    <?php echo esc_html($status['label']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p class="submit">
                <button type="submit" name="save_dois" class="button-primary">
                    <?php _e('Guardar DOIs', 'inblue-library'); ?>
                </button>
            </p>
        </form>

        <?php if ($pages > 1) : ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    $prev_url = $paged > 1
                        ? add_query_arg(array('paged' => $paged - 1), remove_query_arg('paged'))
                        : '';
                    $next_url = $paged < $pages
                        ? add_query_arg(array('paged' => $paged + 1), remove_query_arg('paged'))
                        : '';
                    ?>
                    <span class="displaying-num">
                        <?php printf(
                            esc_html__('Página %1$d de %2$d', 'inblue-library'),
                            $paged,
                            $pages
                        ); ?>
                    </span>
                    <?php if ($prev_url) : ?>
                        <a class="prev-page" href="<?php echo esc_url($prev_url); ?>">&laquo;</a>
                    <?php endif; ?>
                    <?php if ($next_url) : ?>
                        <a class="next-page" href="<?php echo esc_url($next_url); ?>">&raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
