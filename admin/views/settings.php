<div class="wrap">
    <h1><?php _e('Configuración de INBLUE Biblioteca', 'inblue-library'); ?></h1>

    <form method="post">
        <?php wp_nonce_field('inblue_library_settings'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="library_page"><?php _e('Página de la biblioteca', 'inblue-library'); ?></label>
                </th>
                <td>
                    <?php
                    wp_dropdown_pages(array(
                        'name'             => 'library_page',
                        'id'               => 'library_page',
                        'selected'         => $library_page_id,
                        'show_option_none' => __('— Sin página (sin enlace) —', 'inblue-library'),
                        'option_none_value' => '0',
                        'post_status'      => array('publish', 'private', 'draft'),
                    ));
                    ?>
                    <p class="description" style="margin-top:8px">
                        <?php _e('Título de la página a crear:', 'inblue-library'); ?>
                        <input type="text" name="new_page_title" id="new_page_title" value="<?php echo esc_attr($new_page_title); ?>" placeholder="<?php esc_attr_e('Biblioteca', 'inblue-library'); ?>" class="regular-text" style="margin-left:4px">
                    </p>
                    <button type="submit" name="create_library_page" class="button" style="margin-top:8px">
                        <?php _e('Crear página de biblioteca', 'inblue-library'); ?>
                    </button>
                    <p class="description">
                        <?php _e('Página que contiene el shortcode [library_search]. Se usará como destino del enlace "Ver todos" de la sección destacados.', 'inblue-library'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label><?php _e('Icono del título', 'inblue-library'); ?></label>
                </th>
                <td>
                    <div class="inblue-icon-field">
                        <div id="inblue-title-icon-preview" class="inblue-icon-preview">
                            <?php if ($title_icon) : ?>
                                <?php echo wp_get_attachment_image($title_icon, 'thumbnail', false, array('style' => 'max-width:64px;height:auto')); ?>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="title_icon" id="inblue-title-icon-id" value="<?php echo esc_attr($title_icon); ?>">
                        <button type="button" class="button" id="inblue-title-icon-select">
                            <?php _e('Seleccionar imagen', 'inblue-library'); ?>
                        </button>
                        <button type="button" class="button" id="inblue-title-icon-remove">
                            <?php _e('Quitar', 'inblue-library'); ?>
                        </button>
                    </div>
                    <p class="description">
                        <?php _e('Imagen que se mostrará junto al título de la librería (p. ej. un libro o estantería). Se recomienda un archivo pequeño.', 'inblue-library'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="hide_page_header"><?php _e('Ocultar header de la página', 'inblue-library'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="hide_page_header" id="hide_page_header" value="1" <?php checked($hide_page_header, 1); ?>>
                        <?php _e('Ocultar el título de la página de la biblioteca (el que muestra el tema)', 'inblue-library'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Se oculta el título/hero de la página que contiene el shortcode [library_search]. El menú y logo del sitio se mantienen.', 'inblue-library'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="viewer_type"><?php _e('Tipo de visor de PDF', 'inblue-library'); ?></label>
                </th>
                <td>
                    <select name="viewer_type" id="viewer_type">
                        <option value="browser" <?php selected($viewer_type, 'browser'); ?>>
                            <?php _e('Nativo del navegador (&lt;embed&gt;)', 'inblue-library'); ?>
                        </option>
                        <option value="iframe" <?php selected($viewer_type, 'iframe'); ?>>
                            <?php _e('Iframe', 'inblue-library'); ?>
                        </option>
                        <option value="drive" <?php selected($viewer_type, 'drive'); ?>>
                            <?php _e('Visor de Google Drive (para enlaces de Drive)', 'inblue-library'); ?>
                        </option>
                        <option value="download" <?php selected($viewer_type, 'download'); ?>>
                            <?php _e('Solo enlace de descarga (sin visor)', 'inblue-library'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php _e('Cómo se mostrará el PDF en la página del libro.', 'inblue-library'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="books_per_page"><?php _e('Libros por página', 'inblue-library'); ?></label>
                </th>
                <td>
                    <input type="number" name="books_per_page" id="books_per_page" value="<?php echo esc_attr($per_page); ?>" min="4" max="100" step="4" class="small-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="new_book_days"><?php _e('Días para marcar un libro como "Nuevo"', 'inblue-library'); ?></label>
                </th>
                <td>
                    <input type="number" name="new_book_days" id="new_book_days" value="<?php echo esc_attr($new_days); ?>" min="1" max="730" step="1" class="small-text">
                    <p class="description">
                        <?php _e('Un libro publicado dentro de este número de días mostrará la etiqueta "Nuevo" en su tarjeta en lugar del año.', 'inblue-library'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="doi_enabled"><?php _e('DOI', 'inblue-library'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="doi_enabled" id="doi_enabled" value="1" <?php checked($doi_enabled, 1); ?>>
                        <?php _e('Activar la gestión de DOIs (listado y comprobación)', 'inblue-library'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Muestra el menú "DOIs" bajo INBLUE Biblioteca: listado editable con el DOI de la página del libro y el de la página del PDF en GitBook, con su estado — ✓ coinciden / ⚠ no coinciden / ⚠ incompletos / — sin DOI.', 'inblue-library'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="doi_pdf_source"><?php _e('Origen del DOI del PDF', 'inblue-library'); ?></label>
                </th>
                <td>
                    <select name="doi_pdf_source" id="doi_pdf_source">
                        <?php foreach (Inblue_Library_Admin::DOI_PDF_SOURCES as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($doi_pdf_source, $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php _e('De dónde se obtiene el segundo DOI, el del PDF. Se aplica con el botón "Rellenar DOI PDF" de la lista de DOIs, y con GitBook también al importar libros:', 'inblue-library'); ?>
                    </p>
                    <ul class="description" style="list-style:disc;margin-left:20px">
                        <li><?php _e('Página de GitBook: el DOI que publica GitBook en la página del libro.', 'inblue-library'); ?></li>
                        <li><?php _e('PDF de GitBook: descarga el archivo al que enlaza GitBook y busca su DOI dentro; si no lo encuentra, el campo queda vacío y el libro aparece como "Incompleto" para que lo completes a mano.', 'inblue-library'); ?></li>
                    </ul>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" name="save" class="button-primary"><?php _e('Guardar configuración', 'inblue-library'); ?></button>
        </p>
    </form>

    <hr>
    <h2><?php _e('Shortcodes', 'inblue-library'); ?></h2>
    <table class="widefat striped" style="max-width:700px">
        <thead>
            <tr>
                <th><?php _e('Shortcode', 'inblue-library'); ?></th>
                <th><?php _e('Descripción', 'inblue-library'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>[library_grid category="slug" limit="12"]</code></td>
                <td><?php _e('Cuadrícula de libros por categoría.', 'inblue-library'); ?></td>
            </tr>
            <tr>
                <td><code>[library_search title="Biblioteca"]</code></td>
                <td><?php _e('Búsqueda completa con filtros y resultados AJAX.', 'inblue-library'); ?></td>
            </tr>
            <tr>
                <td><code>[library_featured title="Recientes" count="5"]</code></td>
                <td><?php _e('Lista de los libros más recientes. En la página de inicio: pega este shortcode donde quieras que aparezca. Si hay una "Página de la biblioteca" configurada arriba, muestra el enlace "Ver todos".', 'inblue-library'); ?></td>
            </tr>
        </tbody>
    </table>
</div>
