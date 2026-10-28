<?php
if (!defined('ABSPATH')) exit;

class Inblue_Metaboxes {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('add_meta_boxes', array($this, 'register_metabox'));
        add_action('save_post_book', array($this, 'save_metabox'));
    }

    public function register_metabox() {
        add_meta_box(
            'inblue_book_details',
            __('Detalles del Libro', 'inblue-library'),
            array($this, 'render_metabox'),
            'book',
            'normal',
            'high'
        );
    }

    public function render_metabox($post) {
        wp_nonce_field('inblue_book_details', 'inblue_book_nonce');

        $has_thumbnail = has_post_thumbnail($post->ID);
        $is_featured   = get_post_meta($post->ID, '_book_featured', true) === '1';
        $authors       = get_post_meta($post->ID, '_book_authors', true);
        $pub_date      = get_post_meta($post->ID, '_book_pub_date', true);
        $update_date   = get_post_meta($post->ID, '_book_update_date', true);
        $show_updated  = get_post_meta($post->ID, '_book_show_updated', true);
        $isbn          = get_post_meta($post->ID, '_book_isbn', true);
        $doi           = get_post_meta($post->ID, '_book_doi', true);
        $doi_pdf       = get_post_meta($post->ID, '_book_doi_pdf', true);
        $pages         = get_post_meta($post->ID, '_book_pages', true);
        $pdf_source    = get_post_meta($post->ID, '_book_pdf_source', true);
        $pdf_url       = get_post_meta($post->ID, '_book_pdf_url', true);
        $pdf_file_id   = get_post_meta($post->ID, '_book_pdf_file_id', true);
        $publisher     = get_post_meta($post->ID, '_book_publisher', true);
        $language      = get_post_meta($post->ID, '_book_language', true);
        $gitbook_url   = get_post_meta($post->ID, '_book_gitbook_url', true);

        $pdf_file_url = $pdf_file_id ? wp_get_attachment_url($pdf_file_id) : '';
        ?>
        <table class="form-table">
            <tr>
                <th><label for="book_description"><?php _e('Subtítulo', 'inblue-library'); ?></label></th>
                <td>
                    <textarea id="book_description" name="book_description" class="large-text" rows="3" placeholder="<?php esc_attr_e('Texto plano, sin formato', 'inblue-library'); ?>"><?php echo esc_textarea($post->post_content); ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label for="book_authors"><?php _e('Autores', 'inblue-library'); ?></label></th>
                <td>
                    <input type="text" id="book_authors" name="book_authors" value="<?php echo esc_attr($authors); ?>" class="large-text" placeholder="<?php esc_attr_e('Autor 1, Autor 2, Autor 3', 'inblue-library'); ?>">
                    <p class="description"><?php _e('Separa varios autores con comas.', 'inblue-library'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="book_publisher"><?php _e('Editorial', 'inblue-library'); ?></label></th>
                <td><input type="text" id="book_publisher" name="book_publisher" value="<?php echo esc_attr($publisher); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="book_pub_date"><?php _e('Fecha de publicación', 'inblue-library'); ?></label></th>
                <td><input type="date" id="book_pub_date" name="book_pub_date" value="<?php echo esc_attr($pub_date); ?>"></td>
            </tr>
            <tr>
                <th><label for="book_update_date"><?php _e('Fecha de actualización', 'inblue-library'); ?></label></th>
                <td>
                    <input type="date" id="book_update_date" name="book_update_date" value="<?php echo esc_attr($update_date); ?>">
                    <p class="description"><?php _e('Déjalo vacío si no se ha actualizado.', 'inblue-library'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="book_show_updated"><?php _e('Mostrar como actualizado', 'inblue-library'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" id="book_show_updated" name="book_show_updated" value="1" <?php checked($show_updated, '1'); ?>>
                        <?php _e('Mostrar etiqueta "Actualizado" y aparecer al final de Recientes', 'inblue-library'); ?>
                    </label>
                    <p class="description"><?php _e('Requiere una fecha de actualización. Si se marca, la etiqueta "Actualizado" sustituye a "Nuevo" en la tarjeta del libro.', 'inblue-library'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="book_isbn"><?php _e('ISBN', 'inblue-library'); ?></label></th>
                <td><input type="text" id="book_isbn" name="book_isbn" value="<?php echo esc_attr($isbn); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="book_doi"><?php _e('DOI de la página', 'inblue-library'); ?></label></th>
                <td>
                    <input type="text" id="book_doi" name="book_doi" value="<?php echo esc_attr($doi); ?>" class="regular-text" placeholder="10.56168/ibl.ed.xxxxxx" data-inblue-doi="page">
                </td>
            </tr>
            <tr>
                <th><label for="book_doi_pdf"><?php _e('DOI del PDF en GitBook', 'inblue-library'); ?></label></th>
                <td>
                    <input type="text" id="book_doi_pdf" name="book_doi_pdf" value="<?php echo esc_attr($doi_pdf); ?>" class="regular-text" placeholder="10.56168/ibl.ed.xxxxxx" data-inblue-doi="pdf">
                    <p class="description"><?php _e('DOI de la página de GitBook que contiene el PDF del libro.', 'inblue-library'); ?></p>
                    <p class="inblue-doi-status" id="inblue-doi-status"></p>
                </td>
            </tr>
            <tr>
                <th><label for="book_pages"><?php _e('Páginas', 'inblue-library'); ?></label></th>
                <td><input type="number" id="book_pages" name="book_pages" value="<?php echo esc_attr($pages); ?>" min="1" step="1"></td>
            </tr>
            <tr>
                <th><label for="book_language"><?php _e('Idioma', 'inblue-library'); ?></label></th>
                <td>
                    <select id="book_language" name="book_language">
                        <option value="es" <?php selected($language, 'es'); ?>><?php _e('Español', 'inblue-library'); ?></option>
                        <option value="en" <?php selected($language, 'en'); ?>><?php _e('Inglés', 'inblue-library'); ?></option>
                        <option value="pt" <?php selected($language, 'pt'); ?>><?php _e('Portugués', 'inblue-library'); ?></option>
                        <option value="fr" <?php selected($language, 'fr'); ?>><?php _e('Francés', 'inblue-library'); ?></option>
                        <option value="other" <?php selected($language, 'other'); ?>><?php _e('Otro', 'inblue-library'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php _e('Fuente del PDF', 'inblue-library'); ?></th>
                <td>
                    <label><input type="radio" name="book_pdf_source" value="upload" <?php checked($pdf_source, 'upload'); ?> onchange="togglePdfFields()"> <?php _e('Subir PDF', 'inblue-library'); ?></label><br>
                    <label><input type="radio" name="book_pdf_source" value="url" <?php checked($pdf_source, 'url'); ?> onchange="togglePdfFields()"> <?php _e('URL externa (Google Drive, etc.)', 'inblue-library'); ?></label>
                </td>
            </tr>
            <tr class="pdf-upload-field" style="<?php echo $pdf_source === 'url' ? 'display:none;' : ''; ?>">
                <th><label for="book_pdf_file"><?php _e('Subir PDF', 'inblue-library'); ?></label></th>
                <td>
                    <input type="hidden" id="book_pdf_file_id" name="book_pdf_file_id" value="<?php echo esc_attr($pdf_file_id); ?>">
                    <button type="button" class="button" id="inblue_upload_pdf"><?php _e('Seleccionar PDF', 'inblue-library'); ?></button>
                    <span id="inblue_pdf_filename">
                        <?php if ($pdf_file_url) : ?>
                            <a href="<?php echo esc_url($pdf_file_url); ?>" target="_blank"><?php echo esc_html(basename($pdf_file_url)); ?></a>
                        <?php endif; ?>
                    </span>
                </td>
            </tr>
            <tr class="pdf-url-field" style="<?php echo $pdf_source !== 'url' ? 'display:none;' : ''; ?>">
                <th><label for="book_pdf_url"><?php _e('URL del PDF', 'inblue-library'); ?></label></th>
                <td>
                    <input type="url" id="book_pdf_url" name="book_pdf_url" value="<?php echo esc_url($pdf_url); ?>" class="large-text" placeholder="https://drive.google.com/file/d/.../view">
                    <p class="description"><?php _e('Enlace de Google Drive o cualquier URL directa de PDF.', 'inblue-library'); ?></p>
                </td>
            </tr>
            <?php if ($gitbook_url) : ?>
            <tr>
                <th><?php _e('URL de GitBook', 'inblue-library'); ?></th>
                <td><a href="<?php echo esc_url($gitbook_url); ?>" target="_blank"><?php echo esc_html($gitbook_url); ?></a></td>
            </tr>
            <?php endif; ?>
            <tr>
                <th><?php _e('Portada', 'inblue-library'); ?></th>
                <td>
                    <div id="inblue_cover_preview">
                        <?php if ($has_thumbnail) : ?>
                            <?php echo wp_get_attachment_image(get_post_thumbnail_id($post->ID), 'medium', false, array('style' => 'max-width:180px;height:auto')); ?>
                        <?php endif; ?>
                    </div>
                    <p>
                        <input type="hidden" name="inblue_cover_id" id="inblue_cover_id" value="<?php echo esc_attr(get_post_thumbnail_id($post->ID)); ?>">
                        <button type="button" class="button" id="inblue_select_cover"><?php _e('Seleccionar imagen', 'inblue-library'); ?></button>
                        <button type="button" class="button" id="inblue_remove_cover_btn"><?php _e('Quitar portada', 'inblue-library'); ?></button>
                    </p>
                    <p class="description"><?php _e('Esta imagen se usa como portada del libro en la biblioteca.', 'inblue-library'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php _e('Destacado', 'inblue-library'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="inblue_book_featured" id="inblue_book_featured" value="1" <?php checked($is_featured); ?>>
                        <?php _e('Mostrar en la sección de libros destacados.', 'inblue-library'); ?>
                    </label>
                    <p class="description"><?php _e('Solo se mantienen 5 destacados: al marcar un nuevo libro se quita automáticamente el más antiguo.', 'inblue-library'); ?></p>
                </td>
            </tr>
        </table>

        <?php if (!$has_thumbnail) : ?>
            <div class="notice notice-warning inline" style="margin:8px 0">
                <p>
                    <strong><?php _e('Este libro no tiene portada.', 'inblue-library'); ?></strong>
                    <?php _e('Usa el campo "Portada" de arriba para subirla manualmente o genérala desde el PDF con el botón de abajo.', 'inblue-library'); ?>
                </p>
            </div>
        <?php endif; ?>
        <p>
            <button type="button" class="button button-secondary" id="inblue_get_cover">
                <?php _e('Obtener portada del PDF', 'inblue-library'); ?>
            </button>
            <span id="inblue_cover_status"></span>
        </p>

        <script>
        function togglePdfFields() {
            var upload = document.querySelector('.pdf-upload-field');
            var urlField = document.querySelector('.pdf-url-field');
            var uploadRadio = document.querySelector('input[name="book_pdf_source"][value="upload"]');
            if (uploadRadio.checked) {
                upload.style.display = '';
                urlField.style.display = 'none';
            } else {
                upload.style.display = 'none';
                urlField.style.display = '';
            }
        }
        jQuery(document).ready(function($) {
            $('#inblue_select_cover').on('click', function(e) {
                e.preventDefault();
                var frame = wp.media({
                    title: '<?php echo esc_js(__('Seleccionar portada', 'inblue-library')); ?>',
                    library: { type: 'image' },
                    multiple: false,
                });
                frame.on('select', function() {
                    var att = frame.state().get('selection').first().toJSON();
                    $('#inblue_cover_id').val(att.id);
                    var url = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
                    $('#inblue_cover_preview').html('<img src="' + url + '" style="max-width:180px;height:auto">');
                });
                frame.open();
            });

            // Al marcar "Mostrar como actualizado": rellenar la fecha de
            // actualización con hoy si está vacía.
            $('#book_show_updated').on('change', function() {
                if (this.checked && !$('#book_update_date').val()) {
                    var today = new Date();
                    var mm = String(today.getMonth() + 1).padStart(2, '0');
                    var dd = String(today.getDate()).padStart(2, '0');
                    $('#book_update_date').val(today.getFullYear() + '-' + mm + '-' + dd);
                }
            });

            // Estado de los DOIs en vivo: ✓ coinciden / ⚠ no coinciden.
            function normDoi(v) {
                return (v || '')
                    .trim()
                    .replace(/^https?:\/\/(dx\.)?doi\.org\//i, '')
                    .replace(/^doi:\s*/i, '')
                    .replace(/[\s.,;]+$/, '')
                    .toLowerCase();
            }
            function updateDoiStatus() {
                var a = normDoi($('#book_doi').val());
                var b = normDoi($('#book_doi_pdf').val());
                var $out = $('#inblue-doi-status');
                if (!$out.length) return;

                var key = 'empty';
                if (!a && !b) {
                    key = 'empty';
                    $out.text('<?php echo esc_js(__('— Sin DOI', 'inblue-library')); ?>');
                } else if (!a || !b) {
                    key = 'missing';
                    $out.text('<?php echo esc_js(__('⚠ Incompleto: falta uno de los dos DOI', 'inblue-library')); ?>');
                } else if (a === b) {
                    key = 'match';
                    $out.text('<?php echo esc_js(__('✓ Los DOIs coinciden', 'inblue-library')); ?>');
                } else {
                    key = 'mismatch';
                    $out.text('<?php echo esc_js(__('⚠ Los DOIs no coinciden', 'inblue-library')); ?>');
                }
                $out.attr('class', 'inblue-doi-status inblue-doi-status-' + key);
            }
            $('#book_doi, #book_doi_pdf').on('input change', updateDoiStatus);
            updateDoiStatus();

            $('#inblue_remove_cover_btn').on('click', function() {
                $('#inblue_cover_id').val('');
                $('#inblue_cover_preview').empty();
            });

            $('#inblue_upload_pdf').on('click', function(e) {
                e.preventDefault();
                var frame = wp.media({
                    title: '<?php echo esc_js(__('Seleccionar PDF', 'inblue-library')); ?>',
                    library: { type: 'application/pdf' },
                    multiple: false,
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#book_pdf_file_id').val(attachment.id);
                    $('#inblue_pdf_filename').html('<a href="' + attachment.url + '" target="_blank">' + attachment.filename + '</a>');
                });
                frame.open();
            });

            $('#inblue_get_cover').on('click', function() {
                var btn = $(this);
                var status = $('#inblue_cover_status');
                btn.prop('disabled', true);
                status.text('<?php echo esc_js(__('Extrayendo...', 'inblue-library')); ?>');

                $.post(ajaxurl, {
                    action: 'inblue_get_cover',
                    post_id: $('#post_ID').val(),
                    nonce: $('#inblue_book_nonce').val(),
                    pdf_source: $('input[name="book_pdf_source"]:checked').val(),
                    pdf_url: $('#book_pdf_url').val(),
                    pdf_file_id: $('#book_pdf_file_id').val(),
                }, function(resp) {
                    btn.prop('disabled', false);
                    if (resp.success) {
                        status.html('<?php echo esc_js(__('¡Portada asignada!', 'inblue-library')); ?>');
                        if (resp.data.attach_id) {
                            $('#_thumbnail_id').val(resp.data.attach_id);
                            $('#inblue_cover_id').val(resp.data.attach_id);
                        }
                        if (resp.data.thumbnail_url) {
                            $('#inblue_cover_preview').html('<img src="' + resp.data.thumbnail_url + '" style="max-width:180px;height:auto">');
                            var img = $('#postimagediv img');
                            if (img.length) {
                                img.attr('src', resp.data.thumbnail_url);
                            } else {
                                $('#postimagediv .inside').prepend('<img src="' + resp.data.thumbnail_url + '" style="max-width:100%">');
                            }
                        }
                    } else {
                        status.text(resp.data ? resp.data : '<?php echo esc_js(__('Error', 'inblue-library')); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function save_metabox($post_id) {
        if (!isset($_POST['inblue_book_nonce'])) return;
        if (!wp_verify_nonce($_POST['inblue_book_nonce'], 'inblue_book_details')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['book_description'])) {
            $content = sanitize_textarea_field($_POST['book_description']);
            global $wpdb;
            $wpdb->update(
                $wpdb->posts,
                array('post_content' => $content),
                array('ID' => $post_id)
            );
            clean_post_cache($post_id);
        }

        $fields = array(
            'book_authors'     => '_book_authors',
            'book_pub_date'    => '_book_pub_date',
            'book_update_date' => '_book_update_date',
            'book_isbn'        => '_book_isbn',
            'book_doi'         => '_book_doi',
            'book_doi_pdf'     => '_book_doi_pdf',
            'book_pages'       => '_book_pages',
            'book_pdf_source'  => '_book_pdf_source',
            'book_pdf_url'     => '_book_pdf_url',
            'book_pdf_file_id'  => '_book_pdf_file_id',
            'book_publisher'   => '_book_publisher',
            'book_language'    => '_book_language',
        );

        foreach ($fields as $name => $meta_key) {
            if (isset($_POST[$name])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$name]));
            }
        }

        // "Mostrar como actualizado": checkbox (presente = 1, ausente = 0).
        // Solo tiene efecto con fecha de actualización; si no la hay, se limpia.
        $show_updated = isset($_POST['book_show_updated']) ? '1' : '0';
        $update_date  = get_post_meta($post_id, '_book_update_date', true);
        if ('1' === $show_updated && empty($update_date)) {
            $show_updated = '0';
        }
        update_post_meta($post_id, '_book_show_updated', $show_updated);

        if (isset($_POST['inblue_cover_id'])) {
            $cover_id = intval($_POST['inblue_cover_id']);
            if ($cover_id > 0) {
                set_post_thumbnail($post_id, $cover_id);
            } else {
                delete_post_thumbnail($post_id);
            }
        }

        if (isset($_POST['inblue_book_featured'])) {
            $limit = apply_filters('inblue_library_featured_limit', 5);
            $featured_ids = get_posts(array(
                'post_type'      => 'book',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'exclude'        => array($post_id),
                'meta_key'       => '_book_featured',
                'meta_value'     => '1',
                'orderby'        => 'date',
                'order'          => 'DESC',
            ));
            if (count($featured_ids) >= $limit) {
                $oldest = array_pop($featured_ids);
                if ($oldest) {
                    delete_post_meta($oldest, '_book_featured');
                }
            }
            update_post_meta($post_id, '_book_featured', '1');
        } else {
            delete_post_meta($post_id, '_book_featured');
        }

        if (isset($_POST['book_pub_date']) && !empty($_POST['book_pub_date'])) {
            $year = substr(sanitize_text_field($_POST['book_pub_date']), 0, 4);
            update_post_meta($post_id, '_book_year', $year);
        }
    }
}
