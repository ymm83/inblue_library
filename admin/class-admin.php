<?php
if (!defined('ABSPATH')) exit;

class Inblue_Library_Admin {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_pages'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_filter('manage_book_posts_columns', array($this, 'custom_columns'));
        add_action('manage_book_posts_custom_column', array($this, 'custom_column_content'), 10, 2);
        add_filter('manage_edit-book_category_columns', array($this, 'taxonomy_columns'));
        add_action('manage_book_category_custom_column', array($this, 'taxonomy_column_content'), 10, 3);
        add_action('wp_ajax_inblue_get_cover', array($this, 'ajax_get_cover'));
        add_action('wp_ajax_inblue_get_cover_list', array($this, 'ajax_get_cover_list'));
        add_action('wp_ajax_inblue_toggle_featured', array($this, 'ajax_toggle_featured'));
    }

    public function enqueue_assets($hook) {
        if ('post.php' === $hook || 'post-new.php' === $hook) {
            wp_enqueue_media();
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            if ($screen && 'book' === $screen->post_type) {
                wp_enqueue_style('inblue-library-admin', INBLUE_LIBRARY_PLUGIN_URL . 'assets/css/admin.css', array(), INBLUE_LIBRARY_VERSION);
            }
        }
        if (strpos($hook, 'inblue-library') !== false) {
            wp_enqueue_style('inblue-library-admin', INBLUE_LIBRARY_PLUGIN_URL . 'assets/css/admin.css', array(), INBLUE_LIBRARY_VERSION);
        }
        if (strpos($hook, 'inblue-library-settings') !== false) {
            wp_enqueue_media();
            wp_enqueue_script('inblue-admin-settings', INBLUE_LIBRARY_PLUGIN_URL . 'assets/js/admin-settings.js', array('jquery'), INBLUE_LIBRARY_VERSION, true);
        }
        if ('edit.php' === $hook && isset($_GET['post_type']) && $_GET['post_type'] === 'book') {
            wp_enqueue_script('inblue-admin-cover', INBLUE_LIBRARY_PLUGIN_URL . 'assets/js/admin-cover.js', array('jquery'), INBLUE_LIBRARY_VERSION, true);
            wp_localize_script('inblue-admin-cover', 'inblueAdmin', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('inblue_get_cover_list'),
            ));
        }
    }

    public function add_admin_pages() {
        add_submenu_page(
            'edit.php?post_type=book',
            __('Import from GitBook', 'inblue-library'),
            __('Import from GitBook', 'inblue-library'),
            'manage_options',
            'inblue-library-gitbook',
            array($this, 'render_gitbook_import')
        );

        add_submenu_page(
            'edit.php?post_type=book',
            __('Libros nuevos', 'inblue-library'),
            __('Libros nuevos', 'inblue-library'),
            'manage_options',
            'inblue-library-new-books',
            array($this, 'render_new_books')
        );

        add_submenu_page(
            'edit.php?post_type=book',
            __('Import / Export', 'inblue-library'),
            __('Import / Export', 'inblue-library'),
            'manage_options',
            'inblue-library-import-export',
            array($this, 'render_import_export')
        );

        if (self::dois_enabled()) {
            add_submenu_page(
                'edit.php?post_type=book',
                __('DOIs', 'inblue-library'),
                __('DOIs', 'inblue-library'),
                'manage_options',
                'inblue-library-dois',
                array($this, 'render_dois')
            );
        }

        add_submenu_page(
            'edit.php?post_type=book',
            __('Settings', 'inblue-library'),
            __('Settings', 'inblue-library'),
            'manage_options',
            'inblue-library-settings',
            array($this, 'render_settings')
        );
    }

    public function render_gitbook_import() {
        if (!current_user_can('manage_options')) return;

        $results = false;
        $cover_results = false;

        if (isset($_POST['import_gitbook']) && check_admin_referer('inblue_gitbook_import')) {
            $importer = new Inblue_Gitbook_Importer();
            $results = $importer->import_all();
        }

        if (isset($_POST['extract_covers']) && check_admin_referer('inblue_gitbook_import')) {
            $importer = new Inblue_Gitbook_Importer();
            $cover_results = $importer->extract_all_covers();
        }

        if (isset($_POST['clear_gitbook_cache']) && check_admin_referer('inblue_gitbook_import')) {
            $importer = new Inblue_Gitbook_Importer();
            $importer->clear_cache();
            echo '<div class="notice notice-info"><p>' . __('GitBook cache cleared.', 'inblue-library') . '</p></div>';
        }

        include INBLUE_LIBRARY_PLUGIN_DIR . 'admin/views/gitbook-import.php';
    }

    public function render_new_books() {
        if (!current_user_can('manage_options')) return;

        $availability = false;
        $import_new_results = false;

        if (isset($_POST['check_gitbook_availability']) && check_admin_referer('inblue_gitbook_availability')) {
            $importer = new Inblue_Gitbook_Importer();
            $availability = $importer->check_availability();
            set_transient('inblue_gitbook_new_books', $availability['new'], HOUR_IN_SECONDS);
        }

        if (isset($_POST['import_selected_books']) && check_admin_referer('inblue_gitbook_availability')) {
            $selected = isset($_POST['selected_books']) ? array_map('intval', (array) $_POST['selected_books']) : array();
            $new_books = get_transient('inblue_gitbook_new_books');
            if (!empty($new_books) && !empty($selected)) {
                $items = array();
                foreach ($selected as $idx) {
                    if (isset($new_books[$idx])) {
                        $items[] = $new_books[$idx];
                    }
                }
                if (!empty($items)) {
                    $importer = new Inblue_Gitbook_Importer();
                    $import_new_results = $importer->import_new_books($items);
                    delete_transient('inblue_gitbook_new_books');
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Selecciona al menos un libro nuevo para importar.', 'inblue-library') . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>' . __('No hay libros nuevos para importar. Primero comprueba la disponibilidad.', 'inblue-library') . '</p></div>';
            }
        }

        include INBLUE_LIBRARY_PLUGIN_DIR . 'admin/views/new-books.php';
    }

    public function render_import_export() {
        if (!current_user_can('manage_options')) return;

        if (isset($_POST['export_zip_submit']) && check_admin_referer('inblue_export_zip')) {
            $include_data = isset($_POST['export_data']);
            $include_covers = isset($_POST['export_covers']);
            if (!$include_data && !$include_covers) {
                echo '<div class="notice notice-error"><p>' . __('Selecciona al menos una opción: datos o portadas.', 'inblue-library') . '</p></div>';
            } else {
                $since = null;
                if (!empty($_POST['export_since'])) {
                    $since_date = sanitize_text_field(wp_unslash($_POST['export_since']));
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $since_date)) {
                        $since = $since_date . ' 00:00:00';
                    }
                }
                Inblue_Import_Export::export_zip($include_data, $include_covers, $since);
                return;
            }
        }

        $import_results = false;
        if (isset($_FILES['import_csv']) && isset($_POST['import_csv_submit']) && check_admin_referer('inblue_import_csv')) {
            $replace = isset($_POST['replace_library']);
            $options = array(
                'apply_data'   => isset($_POST['import_data']),
                'apply_covers' => isset($_POST['import_covers']),
                'update_only'  => isset($_POST['update_only']),
            );
            if (!$options['apply_data'] && !$options['apply_covers']) {
                echo '<div class="notice notice-error"><p>' . __('Selecciona al menos una opción para importar: datos o portadas.', 'inblue-library') . '</p></div>';
            } else {
                $file = $_FILES['import_csv'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($file['error'] === UPLOAD_ERR_OK && in_array($ext, array('csv', 'zip'), true)) {
                    if ($replace) {
                        Inblue_Import_Export::delete_all_books();
                    }
                    $import_results = Inblue_Import_Export::import_csv($file['tmp_name'], false, $options);
                    if (is_wp_error($import_results)) {
                        echo '<div class="notice notice-error"><p>' . esc_html($import_results->get_error_message()) . '</p></div>';
                        $import_results = false;
                    }
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Please upload a valid CSV file.', 'inblue-library') . '</p></div>';
                }
            }
        }

        include INBLUE_LIBRARY_PLUGIN_DIR . 'admin/views/import-export.php';
    }

    /**
     * Opción global "DOI" de la configuración del plugin.
     */
    public static function dois_enabled() {
        return 1 === (int) get_option('inblue_library_doi_enabled', 1);
    }

    /**
     * Limpia un DOI introducido a mano: quita el prefijo del enlace
     * (https://doi.org/...), el prefijo "doi:" y puntuación final.
     */
    public static function normalize_doi($value) {
        $value = trim((string) $value);
        if ('' === $value) {
            return '';
        }

        // GitBook emite entidades HTML (p. ej. "10.56168/x&#x20;"). PHP no
        // decodifica las numéricas sin punto y coma, así que lo normalizamos.
        $value = preg_replace('/(&#\d+|&#x[0-9a-fA-F]+)(?![0-9a-zA-Z;])/', '$1;', $value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Enlace markdown: [texto](destino). Se prefiere el destino si es un
        // DOI o un enlace de doi.org; si no, el texto visible.
        if (preg_match('/\[(.*?)\]\(\s*<?([^)>]*)>?\s*\)/', $value, $m)) {
            $text   = trim($m[1]);
            $target = trim($m[2]);
            if (preg_match('#^https?://(dx\.)?doi\.org/#i', $target) || preg_match('#^10\.\d{4,9}/#', $target)) {
                $value = $target;
            } else {
                $value = '' !== $text ? $text : $target;
            }
        }

        $value = trim($value, " \t\n\r\0\x0B<>`");
        $value = preg_replace('#^https?://(dx\.)?doi\.org/#i', '', $value);
        $value = preg_replace('#^doi:\s*#i', '', $value);

        // Paréntesis de cierre sobrante: ".../abc)" -> ".../abc". Solo se
        // quita si sobra, porque algunos DOI contienen paréntesis propios.
        while ($value !== '' && ')' === substr($value, -1) && substr_count($value, ')') > substr_count($value, '(')) {
            $value = substr($value, 0, -1);
        }

        $value = preg_replace('/[\s.,;]+$/u', '', $value);
        return trim($value);
    }

    /**
     * Estado de la pareja de DOIs de un libro.
     *
     * @return array{key:string,label:string,note:string}
     */
    public static function doi_status($page_doi, $pdf_doi) {
        $page = self::normalize_doi($page_doi);
        $pdf  = self::normalize_doi($pdf_doi);

        if ('' === $page && '' === $pdf) {
            return array(
                'key'   => 'empty',
                'label' => __('— Sin DOI', 'inblue-library'),
                'note'  => '',
            );
        }
        if ('' === $page || '' === $pdf) {
            return array(
                'key'   => 'missing',
                'label' => __('⚠ Incompleto', 'inblue-library'),
                'note'  => __('Falta uno de los dos DOI', 'inblue-library'),
            );
        }
        if (0 === strcasecmp($page, $pdf)) {
            return array(
                'key'   => 'match',
                'label' => __('✓ Coinciden', 'inblue-library'),
                'note'  => __('Ambos DOI apuntan al mismo registro', 'inblue-library'),
            );
        }
        return array(
            'key'   => 'mismatch',
            'label' => __('⚠ No coinciden', 'inblue-library'),
            'note'  => __('El DOI de la página y el del PDF en GitBook son distintos', 'inblue-library'),
        );
    }

    /**
     * Origen del segundo DOI (el del PDF), configurable en Ajustes.
     *
     * - gitbook: se lee de la página de GitBook del libro (el DOI que
     *            publica GitBook, que es el del propio PDF).
     * - pdf:     se extrae del archivo PDF al que enlaza GitBook.
     */
    const DOI_PDF_SOURCES = array(
        'gitbook' => 'Página de GitBook',
        'pdf'     => 'PDF de GitBook',
    );

    public static function doi_pdf_source() {
        $src = get_option('inblue_library_doi_pdf_source', 'gitbook');
        return isset(self::DOI_PDF_SOURCES[$src]) ? $src : 'gitbook';
    }

    /**
     * Extrae los DOI de una página de GitBook en markdown.
     *
     * @return array{page:string,pdf:string}
     */
    public static function extract_dois_from_markdown($markdown) {
        $out = array('page' => '', 'pdf' => '');
        if (!is_string($markdown) || '' === $markdown) {
            return $out;
        }
        if (!preg_match_all('/^\s*[\*\_]*DOI([^\n:]{0,60})[:：]\s*(.+)$/mi', $markdown, $dois, PREG_SET_ORDER)) {
            return $out;
        }

        $pages = array();
        foreach ($dois as $entry) {
            $label = strtolower(trim($entry[1]));
            $value = self::normalize_doi($entry[2]);
            if ('' === $value) {
                continue;
            }
            if (false !== strpos($label, 'pdf')) {
                if ('' === $out['pdf']) {
                    $out['pdf'] = $value;
                }
            } else {
                $pages[] = $value;
            }
        }

        if ('' === $out['page'] && !empty($pages)) {
            $out['page'] = $pages[0];
        }
        // Sin etiqueta explícita de "PDF": el segundo DOI genérico se toma
        // como el de la página del PDF.
        if ('' === $out['pdf'] && count($pages) > 1) {
            $out['pdf'] = $pages[count($pages) - 1];
        }

        return $out;
    }

    /**
     * Lee el DOI del campo de la página de GitBook del libro.
     */
    public static function extract_doi_from_gitbook_url($gitbook_url) {
        if (empty($gitbook_url)) {
            return '';
        }
        $md_url = rtrim($gitbook_url, '/') . '.md';
        $cache_key = 'inblue_gbdoi_' . md5($md_url);
        $markdown = get_transient($cache_key);

        if (false === $markdown) {
            $response = wp_remote_get($md_url, array(
                'timeout'     => 15,
                'redirection' => 3,
                'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ));
            if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
                return '';
            }
            $markdown = wp_remote_retrieve_body($response);
            set_transient($cache_key, $markdown, HOUR_IN_SECONDS);
        }

        $dois = self::extract_dois_from_markdown($markdown);
        return $dois['page'];
    }

    /**
     * Busca un DOI dentro del contenido binario de un PDF.
     *
     * Se puntuán los candidatos: los del prefijo propio (10.56168/) y los que
     * aparecen como destino de un enlace doi.org pesan más, para descartar las
     * referencias bibliográficas citadas en el texto.
     */
    public static function extract_doi_from_pdf_content($body) {
        if (false === $body || '' === $body || '%PDF-' !== substr($body, 0, 5)) {
            return '';
        }

        $candidates = array();
        self::collect_doi_candidates($body, 10, $candidates);

        // Enlaces / anotaciones a doi.org en texto plano.
        if (preg_match_all('#https?://(?:dx\.)?doi\.org/(10\.\d{4,9}/[^\s"\'<>()\\\\]+)#i', $body, $m)) {
            foreach ($m[1] as $raw) {
                $doi = self::normalize_doi($raw);
                if ('' !== $doi) {
                    $candidates[$doi] = isset($candidates[$doi]) ? max($candidates[$doi], 60) : 60;
                }
            }
        }

        // Streams con FlateDecode (zlib).
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $body, $streams)) {
            foreach ($streams[1] as $stream) {
                $data = @gzuncompress($stream);
                if (false === $data) {
                    $data = @gzinflate($stream);
                }
                if (false === $data || '' === $data) {
                    continue;
                }
                self::collect_doi_candidates($data, 30, $candidates);
            }
        }

        if (empty($candidates)) {
            return '';
        }
        arsort($candidates);
        return (string) key($candidates);
    }

    private static function collect_doi_candidates($text, $weight, &$candidates) {
        if (!preg_match_all('/10\.\d{4,9}\/[-._;()\/:a-zA-Z0-9]+/', $text, $m)) {
            return;
        }
        foreach (array_unique($m[0]) as $raw) {
            $doi = self::normalize_doi($raw);
            if ('' === $doi) {
                continue;
            }
            $score = $weight;
            if (0 === stripos($doi, '10.56168/')) {
                $score += 100;
            }
            if (!isset($candidates[$doi]) || $score > $candidates[$doi]) {
                $candidates[$doi] = $score;
            }
        }
    }

    /**
     * Descarga el PDF (Google Drive o URL directa) y extrae su DOI.
     */
    public static function extract_doi_from_pdf_url($pdf_url) {
        if (empty($pdf_url)) {
            return '';
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        if (preg_match('#drive\.google\.com/file/d/([^/]+)#', $pdf_url, $m)) {
            $pdf_url = 'https://drive.google.com/uc?export=download&confirm=t&id=' . $m[1];
        }

        $response = wp_remote_get($pdf_url, array(
            'timeout'     => 90,
            'redirection' => 5,
            'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ));
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return '';
        }

        return self::extract_doi_from_pdf_content(wp_remote_retrieve_body($response));
    }

    /**
     * Rellena _book_doi_pdf según la fuente configurada, por lotes.
     *
     * @param bool   $overwrite También reescribe los que ya tienen valor.
     * @param int    $offset    Posición desde la que continuar.
     * @param int    $limit     Libros a procesar en esta pasada.
     * @return array{source:string,processed:int,filled:int,failed:int,remaining:int,next_offset:int}
     */
    public function fill_doi_pdf_batch($overwrite, $offset = 0, $limit = 10) {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $source = self::doi_pdf_source();
        $result = array(
            'source' => $source, 'processed' => 0, 'filled' => 0,
            'failed' => 0, 'remaining' => 0, 'next_offset' => $offset,
        );

        $ids = get_posts(array(
            'post_type'      => 'book',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ));

        $todo = array();
        foreach ($ids as $id) {
            if (!$overwrite && '' !== trim((string) get_post_meta($id, '_book_doi_pdf', true))) {
                continue;
            }
            $todo[] = $id;
        }

        $slice = array_slice($todo, $offset, max(1, (int) $limit));
        foreach ($slice as $id) {
            $value = '';
            if ('gitbook' === $source) {
                $value = self::extract_doi_from_gitbook_url(get_post_meta($id, '_book_gitbook_url', true));
            } elseif ('pdf' === $source) {
                $value = self::extract_doi_from_pdf_url(get_post_meta($id, '_book_pdf_url', true));
            }

            if ('' !== $value) {
                update_post_meta($id, '_book_doi_pdf', $value);
                $result['filled']++;
            } else {
                $result['failed']++;
            }
            $result['processed']++;
        }

        $result['remaining']   = max(0, count($todo) - $offset - $result['processed']);
        $result['next_offset'] = $offset + $result['processed'];

        return $result;
    }

    /**
     * Rellena _book_doi (el DOI de la página del libro) desde su página de
     * GitBook, por lotes. A diferencia del DOI del PDF, el origen no es
     * configurable: siempre es la página de GitBook del propio libro.
     *
     * @param bool   $overwrite También reescribe los que ya tienen valor.
     * @param int    $offset     Posición desde la que continuar.
     * @param int    $limit      Libros a procesar en esta pasada.
     * @return array{source:string,processed:int,filled:int,failed:int,remaining:int,next_offset:int}
     */
    public function fill_doi_page_batch($overwrite, $offset = 0, $limit = 10) {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $result = array(
            'source' => 'gitbook', 'processed' => 0, 'filled' => 0,
            'failed' => 0, 'remaining' => 0, 'next_offset' => $offset,
        );

        $ids = get_posts(array(
            'post_type'      => 'book',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ));

        $todo = array();
        foreach ($ids as $id) {
            if (!$overwrite && '' !== trim((string) get_post_meta($id, '_book_doi', true))) {
                continue;
            }
            $todo[] = $id;
        }

        $slice = array_slice($todo, $offset, max(1, (int) $limit));
        foreach ($slice as $id) {
            $value = self::normalize_doi(
                self::extract_doi_from_gitbook_url(get_post_meta($id, '_book_gitbook_url', true))
            );

            if ('' !== $value) {
                update_post_meta($id, '_book_doi', $value);
                $result['filled']++;
            } else {
                $result['failed']++;
            }
            $result['processed']++;
        }

        $result['remaining']   = max(0, count($todo) - $offset - $result['processed']);
        $result['next_offset'] = $offset + $result['processed'];

        return $result;
    }

    public function render_dois() {
        if (!current_user_can('manage_options')) return;

        $fill_result = null;
        $page_fill_result = null;
        $source_saved = false;
        $doi_pdf_source = self::doi_pdf_source();

        // El selector vive en la propia barra de relleno: se guarda primero
        // para que el origen nuevo se aplique en esa misma pasada.
        if (isset($_POST['doi_pdf_source']) && check_admin_referer('inblue_doi_fill')) {
            $src = sanitize_text_field(wp_unslash($_POST['doi_pdf_source']));
            if (isset(self::DOI_PDF_SOURCES[$src])) {
                update_option('inblue_library_doi_pdf_source', $src);
                $doi_pdf_source = $src;
                $source_saved = isset($_POST['save_doi_source']);
            }
        }

        if (isset($_POST['fill_doi_pdf']) && check_admin_referer('inblue_doi_fill')) {
            $offset = isset($_POST['fill_offset']) ? max(0, absint($_POST['fill_offset'])) : 0;
            $fill_result = $this->fill_doi_pdf_batch(! empty($_POST['fill_overwrite']), $offset, 10);
        }
        if (isset($_POST['fill_doi_page']) && check_admin_referer('inblue_doi_fill')) {
            $page_offset = isset($_POST['page_offset']) ? max(0, absint($_POST['page_offset'])) : 0;
            $page_fill_result = $this->fill_doi_page_batch(! empty($_POST['fill_overwrite']), $page_offset, 10);
        }

        if (isset($_POST['save_dois']) && check_admin_referer('inblue_doi_save')) {
            $rows  = isset($_POST['dois']) ? (array) $_POST['dois'] : array();
            $saved = 0;
            foreach ($rows as $book_id => $values) {
                $book_id = (int) $book_id;
                if (!$book_id || !current_user_can('edit_post', $book_id)) {
                    continue;
                }
                if (!get_post($book_id)) {
                    continue;
                }
                $page = isset($values['page_doi'])
                    ? self::normalize_doi(sanitize_text_field(wp_unslash($values['page_doi'])))
                    : '';
                $pdf = isset($values['pdf_doi'])
                    ? self::normalize_doi(sanitize_text_field(wp_unslash($values['pdf_doi'])))
                    : '';
                update_post_meta($book_id, '_book_doi', $page);
                update_post_meta($book_id, '_book_doi_pdf', $pdf);
                $saved++;
            }
            echo '<div class="notice notice-success"><p>'
                . sprintf(__('Guardado: %d libro(s) actualizados.', 'inblue-library'), $saved)
                . '</p></div>';
        }

        // Todos los libros publicados + sus DOIs en una sola query de metas.
        $book_ids = get_posts(array(
            'post_type'      => 'book',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
            'suppress_filters' => false,
        ));
        if ($book_ids) {
            update_meta_cache('post', $book_ids);
        }

        $rows = array();
        foreach ($book_ids as $book_id) {
            $page_doi = self::normalize_doi(get_post_meta($book_id, '_book_doi', true));
            $pdf_doi  = self::normalize_doi(get_post_meta($book_id, '_book_doi_pdf', true));
            $rows[] = array(
                'id'        => (int) $book_id,
                'title'     => get_the_title($book_id),
                'page_doi'  => $page_doi,
                'pdf_doi'   => $pdf_doi,
                'status'    => self::doi_status($page_doi, $pdf_doi),
            );
        }

        $counts = array('match' => 0, 'mismatch' => 0, 'missing' => 0, 'empty' => 0);
        foreach ($rows as $row) {
            $counts[$row['status']['key']]++;
        }
        $grand_total = count($rows);

        // Filtros (GET) y paginación.
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $filter = isset($_GET['doi_status']) ? sanitize_text_field(wp_unslash($_GET['doi_status'])) : 'all';
        $paged  = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

        $filtered = $rows;
        if ('' !== $search) {
            $needle = mb_strtolower($search);
            $filtered = array_values(array_filter($filtered, function ($row) use ($needle) {
                return false !== mb_stripos($row['title'], $needle)
                    || false !== mb_stripos($row['page_doi'], $needle)
                    || false !== mb_stripos($row['pdf_doi'], $needle);
            }));
        }
        if ('all' !== $filter) {
            $filtered = array_values(array_filter($filtered, function ($row) use ($filter) {
                return $row['status']['key'] === $filter;
            }));
        }

        $per_page = 50;
        $total    = count($filtered);
        $pages    = max(1, (int) ceil($total / $per_page));
        $paged    = min($paged, $pages);
        $rows     = array_slice($filtered, ($paged - 1) * $per_page, $per_page);

        include INBLUE_LIBRARY_PLUGIN_DIR . 'admin/views/dois.php';
    }

    public function render_settings() {
        if (!current_user_can('manage_options')) return;

        if (isset($_POST['create_library_page']) && check_admin_referer('inblue_library_settings', '_wpnonce')) {
            $page_title = isset($_POST['new_page_title']) ? sanitize_text_field(wp_unslash($_POST['new_page_title'])) : '';
            if ('' === trim($page_title)) {
                $page_title = __('Biblioteca', 'inblue-library');
            }
            $page_id = wp_insert_post(array(
                'post_title'   => $page_title,
                'post_content' => '[library_search title="' . esc_attr($page_title) . '"]',
                'post_type'    => 'page',
                'post_status'  => 'publish',
            ));
            if ($page_id && !is_wp_error($page_id)) {
                update_option('inblue_library_page_id', $page_id);
                wp_cache_delete('inblue_library_base_page', 'inblue_library');
                echo '<div class="notice notice-success"><p>' . sprintf(__('Página "%s" creada (ID %d) con el shortcode [library_search]. Ya está seleccionada como página de la librería.', 'inblue-library'), esc_html($page_title), $page_id) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . __('No se pudo crear la página.', 'inblue-library') . '</p></div>';
            }
        }

        if (isset($_POST['save']) && check_admin_referer('inblue_library_settings', '_wpnonce')) {
            update_option('inblue_library_viewer_type', sanitize_text_field($_POST['viewer_type']));
            update_option('inblue_library_books_per_page', absint($_POST['books_per_page']));
            $new_days = isset($_POST['new_book_days']) ? absint($_POST['new_book_days']) : 180;
            update_option('inblue_library_new_book_days', $new_days > 0 ? $new_days : 180);
            $library_page = isset($_POST['library_page']) ? absint($_POST['library_page']) : 0;
            update_option('inblue_library_page_id', $library_page);
            $hide_page_header = isset($_POST['hide_page_header']) ? 1 : 0;
            update_option('inblue_library_hide_page_header', $hide_page_header);
            $title_icon = isset($_POST['title_icon']) ? absint($_POST['title_icon']) : 0;
            update_option('inblue_library_title_icon', $title_icon);
            $doi_enabled = isset($_POST['doi_enabled']) ? 1 : 0;
            update_option('inblue_library_doi_enabled', $doi_enabled);
            $doi_pdf_source = isset($_POST['doi_pdf_source']) ? sanitize_text_field($_POST['doi_pdf_source']) : 'gitbook';
            if (! isset(Inblue_Library_Admin::DOI_PDF_SOURCES[$doi_pdf_source])) {
                $doi_pdf_source = 'gitbook';
            }
            update_option('inblue_library_doi_pdf_source', $doi_pdf_source);
            wp_cache_delete('inblue_library_base_page', 'inblue_library');
            echo '<div class="notice notice-success"><p>' . __('Settings saved.', 'inblue-library') . '</p></div>';
        }

        $viewer_type = get_option('inblue_library_viewer_type', 'browser');
        $per_page = get_option('inblue_library_books_per_page', 12);
        $new_days = get_option('inblue_library_new_book_days', 180);
        $library_page_id = get_option('inblue_library_page_id', 0);
        $hide_page_header = get_option('inblue_library_hide_page_header', 1);
        $title_icon = get_option('inblue_library_title_icon', 0);
        $doi_enabled = get_option('inblue_library_doi_enabled', 1);
        $doi_pdf_source = self::doi_pdf_source();
        $new_page_title = get_option('inblue_library_title', __('Biblioteca', 'inblue-library'));
        include INBLUE_LIBRARY_PLUGIN_DIR . 'admin/views/settings.php';
    }

    public function custom_columns($columns) {
        $new = array();
        foreach ($columns as $key => $label) {
            if ($key === 'title') {
                $new[$key] = $label;
                $new['book_cover'] = __('Portada', 'inblue-library');
                $new['book_authors'] = __('Autores', 'inblue-library');
                $new['book_pub_date'] = __('Publicado', 'inblue-library');
                $new['book_isbn'] = __('ISBN', 'inblue-library');
                $new['book_category'] = __('Categoría', 'inblue-library');
                continue;
            }
            if ($key === 'date') continue;
            $new[$key] = $label;
        }
        $new['date'] = __('Date', 'inblue-library');
        return $new;
    }

    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'book_cover':
                if (has_post_thumbnail($post_id) && !self::is_thumbnail_blank($post_id)) {
                    echo get_the_post_thumbnail($post_id, array(50, 70));
                } else {
                    echo '<button type="button" class="button button-small inblue-get-cover" data-post-id="' . esc_attr($post_id) . '">' . __('Obtener Portada', 'inblue-library') . '</button>';
                    echo '<span class="inblue-cover-spinner" style="display:none"><span class="spinner is-active" style="float:none;margin:0"></span></span>';
                    echo '<span class="inblue-cover-error" style="display:none;color:#d63638;font-size:11px"></span>';
                }
                break;
            case 'book_authors':
                echo esc_html(get_post_meta($post_id, '_book_authors', true) ?: '—');
                break;
            case 'book_pub_date':
                $d = get_post_meta($post_id, '_book_pub_date', true);
                echo $d ? esc_html(date_i18n(get_option('date_format'), strtotime($d))) : '—';
                break;
            case 'book_year':
                echo esc_html(get_post_meta($post_id, '_book_year', true) ?: '—');
                break;
            case 'book_isbn':
                echo esc_html(get_post_meta($post_id, '_book_isbn', true) ?: '—');
                break;
            case 'book_category':
                $terms = wp_get_post_terms($post_id, 'book_category');
                $names = array();
                foreach ($terms as $t) {
                    $names[] = $t->name;
                }
                echo esc_html(implode(', ', $names));
                break;
        }
    }

    public function taxonomy_columns($columns) {
        $columns['books_count'] = __('Books', 'inblue-library');
        return $columns;
    }

    public function taxonomy_column_content($content, $column_name, $term_id) {
        if ($column_name === 'books_count') {
            $term = get_term($term_id, 'book_category');
            return $term->count;
        }
        return $content;
    }

    public function ajax_toggle_featured() {
        if (!check_ajax_referer('inblue_toggle_featured', 'nonce', false)) {
            wp_send_json_error(__('Nonce inválido', 'inblue-library'));
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(__('Sin permisos', 'inblue-library'));
        }

        if (get_post_meta($post_id, '_book_featured', true) === '1') {
            delete_post_meta($post_id, '_book_featured');
            $featured = false;
            $removed  = false;
        } else {
            $removed = false;

            $limit = apply_filters('inblue_library_featured_limit', 5);
            $featured_ids = get_posts(array(
                'post_type'      => 'book',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_key'       => '_book_featured',
                'meta_value'     => '1',
                'orderby'        => 'date',
                'order'          => 'DESC',
            ));

            if (count($featured_ids) >= $limit) {
                $oldest = array_pop($featured_ids);
                if ($oldest && (int) $oldest !== (int) $post_id) {
                    delete_post_meta($oldest, '_book_featured');
                    $removed = $oldest;
                }
            }

            update_post_meta($post_id, '_book_featured', '1');
            $featured = true;
        }

        $message = $featured ? __('Añadido a destacados', 'inblue-library') : __('Quitado de destacados', 'inblue-library');
        if ($removed) {
            $message .= ' ' . sprintf(__('Se quitó el libro más antiguo: %s', 'inblue-library'), get_the_title($removed));
        }

        wp_send_json_success(array(
            'featured'  => $featured,
            'removed'   => $removed ? (int) $removed : null,
            'message'   => $message,
        ));
    }

    public function ajax_get_cover() {
        if (!wp_verify_nonce($_POST['nonce'], 'inblue_book_details')) {
            wp_send_json_error(__('Invalid nonce', 'inblue-library'));
        }

        $post_id = intval($_POST['post_id']);
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(__('No permission', 'inblue-library'));
        }

        $pdf_source = isset($_POST['pdf_source']) ? sanitize_text_field($_POST['pdf_source']) : '';
        $pdf_url    = isset($_POST['pdf_url']) ? esc_url_raw($_POST['pdf_url']) : '';
        $pdf_file_id = isset($_POST['pdf_file_id']) ? intval($_POST['pdf_file_id']) : 0;

        $result = self::obtener_portada($post_id, $pdf_source, $pdf_url, $pdf_file_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array(
            'attach_id'     => $result,
            'thumbnail_url' => wp_get_attachment_image_url($result, 'medium'),
            'message'       => __('Portada extraída correctamente', 'inblue-library'),
        ));
    }

    public static function is_thumbnail_blank($post_id) {
        $thumb_id = get_post_thumbnail_id($post_id);
        if (!$thumb_id) {
            return false;
        }

        $file = get_attached_file($thumb_id);
        if (!$file || !file_exists($file)) {
            return false;
        }

        if (filesize($file) < 500 || !self::image_is_decodable($file)) {
            return true;
        }

        $data = get_post_meta($thumb_id, '_wp_attachment_metadata', true);
        if (!empty($data['sizes']) && !empty($data['sizes']['medium']['file'])) {
            $medium_path = dirname($file) . '/' . $data['sizes']['medium']['file'];
            if (file_exists($medium_path) && (filesize($medium_path) < 500 || !self::image_is_decodable($medium_path))) {
                return true;
            }
        }

        return false;
    }

    private static function image_is_decodable($path) {
        $content = @file_get_contents($path);
        if ($content === false || strlen($content) < 16) {
            return false;
        }

        return self::image_body_is_decodable($content);
    }

    private static function image_body_is_decodable($content) {
        if (strlen($content) < 16) {
            return false;
        }

        if (function_exists('imagecreatefromstring')) {
            $img = @imagecreatefromstring($content);
            if ($img === false) {
                return false;
            }
            imagedestroy($img);
            return true;
        }

        $info = @getimagesizefromstring($content);
        return $info !== false;
    }

    private static function remote_get_noproxy($url, $args = array()) {
        $filter = function ($handle) {
            curl_setopt($handle, CURLOPT_PROXY, '');
            curl_setopt($handle, CURLOPT_NOPROXY, '*');
            return $handle;
        };
        add_filter('http_api_curl', $filter, 10, 1);
        $response = wp_remote_get($url, $args);
        remove_filter('http_api_curl', $filter);
        return $response;
    }

    private static function remote_get_drive($url, $args = array()) {
        return self::remote_get_noproxy($url, $args);
    }

    private static function log_drive($message, $context = array()) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        $line = '[inblue-library] ' . $message;
        if (!empty($context)) {
            $line .= ' ' . wp_json_encode($context);
        }
        error_log($line);
    }

    public static function delete_thumbnail($post_id) {
        $thumb_id = get_post_thumbnail_id($post_id);
        if (!$thumb_id) {
            return false;
        }

        delete_post_thumbnail($post_id);
        wp_delete_attachment($thumb_id, true);

        return true;
    }

    private static function detect_image_extension($content_type, $body) {
        if (strpos($body, "\x89PNG") === 0) return 'png';
        if (strpos($body, 'RIFF') === 0 && substr($body, 8, 4) === 'WEBP') return 'webp';
        if (strpos($body, "\xFF\xD8\xFF") === 0) return 'jpg';
        if (strpos($body, 'GIF89a') === 0 || strpos($body, 'GIF87a') === 0) return 'gif';
        if (substr($body, 0, 2) === 'BM') return 'bmp';

        $map = array(
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            'image/bmp'  => 'bmp',
        );

        if (isset($map[$content_type])) {
            return $map[$content_type];
        }

        return 'jpg';
    }

    public function ajax_get_cover_list() {
        if (!wp_verify_nonce($_POST['nonce'], 'inblue_get_cover_list')) {
            wp_send_json_error(__('Invalid nonce', 'inblue-library'));
        }

        $post_id = intval($_POST['post_id']);
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(__('No permission', 'inblue-library'));
        }

        if (has_post_thumbnail($post_id) && self::is_thumbnail_blank($post_id)) {
            self::delete_thumbnail($post_id);
        }

        $result = self::obtener_portada($post_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array(
            'attach_id'     => $result,
            'thumbnail_url' => wp_get_attachment_image_url($result, array(50, 70)),
        ));
    }

    public static function obtener_portada($post_id, $pdf_source = '', $pdf_url = '', $pdf_file_id = 0) {
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }
        @ini_set('max_execution_time', 120);

        if (empty($pdf_source)) {
            $pdf_source = get_post_meta($post_id, '_book_pdf_source', true);
        }
        if (empty($pdf_url)) {
            if ($pdf_source === 'url') {
                $pdf_url = get_post_meta($post_id, '_book_pdf_url', true);
            }
        }
        if (empty($pdf_file_id)) {
            $pdf_file_id = get_post_meta($post_id, '_book_pdf_file_id', true);
        }

        if (empty($pdf_url) && $pdf_source !== 'url' && !empty($pdf_file_id)) {
            $pdf_url = wp_get_attachment_url($pdf_file_id);
        }

        if (empty($pdf_url)) {
            return new WP_Error('no_pdf', __('No PDF found for this book', 'inblue-library'));
        }

        $pdf_url = str_replace(array('<', '>'), '', $pdf_url);
        $image_url = '';

        if (strpos($pdf_url, 'drive.google.com') !== false) {
            preg_match('/\/d\/([^\/]+)/', $pdf_url, $m);
            if (!empty($m[1])) {
                $file_id = $m[1];
                self::log_drive('Obteniendo portada de Drive', array('file_id' => $file_id, 'book' => $post_id));

                $candidates = array(
                    'https://drive.google.com/thumbnail?id=' . $file_id . '&sz=w800',
                    'https://drive.google.com/thumbnail?id=' . $file_id,
                    'https://drive.google.com/uc?export=view&id=' . $file_id,
                    'https://lh3.googleusercontent.com/d/' . $file_id,
                    'https://lh3.googleusercontent.com/d/' . $file_id . '=w800',
                    'https://lh3.googleusercontent.com/d/' . $file_id . '=s1000',
                    'https://lh3.googleusercontent.com/d/' . $file_id . '=w1000-h1000',
                );

                $http_responses = 0;
                foreach ($candidates as $try_url) {
                    $response = self::remote_get_drive($try_url, array(
                        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'timeout'    => 15,
                        'headers'    => array('Accept' => 'image/webp,image/avif,image/*,*/*;q=0.8'),
                        'redirection' => 5,
                    ));

                    if (is_wp_error($response)) {
                        self::log_drive('Falló descarga de candidato', array('url' => $try_url, 'error' => $response->get_error_message()));
                        continue;
                    }
                    $http_responses++;
                    $code = wp_remote_retrieve_response_code($response);
                    $content_type = wp_remote_retrieve_header($response, 'content-type');
                    self::log_drive('Candidato respondió', array('url' => $try_url, 'code' => $code, 'type' => $content_type));
                    if ($code !== 200) continue;
                    if (strpos($content_type, 'image/') === false) continue;

                    $body = wp_remote_retrieve_body($response);
                    if (strlen($body) < 500 || !self::image_body_is_decodable($body)) {
                        self::log_drive('Candidato no es imagen decodificable', array('url' => $try_url, 'len' => strlen($body)));
                        continue;
                    }

                    $ext = self::detect_image_extension($content_type, $body);
                    $upload = wp_upload_bits($file_id . '.' . $ext, null, $body);
                    if (!empty($upload['error'])) {
                        self::log_drive('wp_upload_bits falló', array('error' => $upload['error']));
                        continue;
                    }

                    $file_path = $upload['file'];
                    $wp_filetype = wp_check_filetype($file_path, null);
                    $attachment = array(
                        'post_mime_type' => $wp_filetype['type'],
                        'post_title'     => sanitize_file_name(basename($file_path)),
                        'post_content'   => '',
                        'post_status'    => 'inherit',
                    );
                    $attach_id = wp_insert_attachment($attachment, $file_path, $post_id);
                    require_once ABSPATH . 'wp-admin/includes/image.php';
                    $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
                    wp_update_attachment_metadata($attach_id, $attach_data);
                    set_post_thumbnail($post_id, $attach_id);
                    return $attach_id;
                }

                $dl_url = 'https://drive.google.com/uc?export=download&confirm=t&id=' . $file_id;
                $dl = self::remote_get_drive($dl_url, array('timeout' => 30, 'redirection' => 5));
                if (!is_wp_error($dl) && 200 === wp_remote_retrieve_response_code($dl)) {
                    $temp_file = wp_upload_bits($file_id . '.pdf', null, wp_remote_retrieve_body($dl));
                    if (empty($temp_file['error'])) {
                        $pdf_attach_id = wp_insert_attachment(array(
                            'post_mime_type' => 'application/pdf',
                            'post_title'     => $file_id,
                            'post_content'   => '',
                            'post_status'    => 'inherit',
                        ), $temp_file['file'], $post_id);
                        require_once ABSPATH . 'wp-admin/includes/image.php';
                        $pdf_attach_data = wp_generate_attachment_metadata($pdf_attach_id, $temp_file['file']);
                        if (!empty($pdf_attach_data) && !empty($pdf_attach_data['thumb'])) {
                            $thumb_url = wp_get_attachment_image_url($pdf_attach_id, 'medium');
                            if ($thumb_url) {
                                set_post_thumbnail($post_id, $pdf_attach_id);
                                return $pdf_attach_id;
                            }
                        }
                    }
                }

                if (0 === $http_responses) {
                    return new WP_Error('drive_connection', __('No se pudo conectar con Google Drive. Comprueba tu conexión a Internet y que el archivo esté compartido como público.', 'inblue-library'));
                }

                return new WP_Error('drive_thumb', __('Google Drive no tiene una miniatura pública para este archivo. Comparte el archivo como "Cualquier persona con el enlace" o sube la portada manualmente.', 'inblue-library'));
            }
        }

        if (empty($image_url) && !empty($pdf_file_id)) {
            $thumb_url = wp_get_attachment_image_url($pdf_file_id, 'medium');
            if ($thumb_url) {
                $image_url = $thumb_url;
            } else {
                $cover_attach_id = self::render_local_pdf_cover($pdf_file_id, $post_id);
                if ($cover_attach_id) {
                    return $cover_attach_id;
                }
            }
        }

        if (empty($image_url)) {
            return new WP_Error('no_thumb', __('No se pudo generar la portada a partir del PDF. En el hosting debe estar activo Imagick (con soporte de PDF/Ghostscript) o el preview de PDFs de WordPress. También puedes subir la portada manualmente con la "Imagen destacada".', 'inblue-library'));
        }

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_sideload_image($image_url, $post_id, null, 'id');

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        set_post_thumbnail($post_id, $attachment_id);

        return $attachment_id;
    }

    private static function render_local_pdf_cover($pdf_file_id, $post_id) {
        $pdf_path = get_attached_file($pdf_file_id);
        if (!$pdf_path || !file_exists($pdf_path)) {
            return 0;
        }

        $blob = '';

        if (class_exists('Imagick')) {
            try {
                $imagick = new Imagick();
                $imagick->setResolution(150, 150);
                $imagick->readImage($pdf_path . '[0]');
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(90);
                $blob = $imagick->getImageBlob();
                $imagick->clear();
                $imagick->destroy();
            } catch (Exception $e) {
                $blob = '';
            }
        }

        if (empty($blob)) {
            $blob = self::ghostscript_render_pdf($pdf_path);
        }

        if (empty($blob) || strlen($blob) < 500) {
            return 0;
        }

        $upload = wp_upload_bits('cover-' . $pdf_file_id . '.jpg', null, $blob);
        if (!empty($upload['error'])) {
            return 0;
        }

        $file_path = $upload['file'];
        $wp_filetype = wp_check_filetype($file_path, null);
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title'     => sanitize_file_name(basename($file_path)),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );
        $new_attach_id = wp_insert_attachment($attachment, $file_path, $post_id);
        if (!$new_attach_id || is_wp_error($new_attach_id)) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata($new_attach_id, $file_path);
        wp_update_attachment_metadata($new_attach_id, $attach_data);
        set_post_thumbnail($post_id, $new_attach_id);

        return $new_attach_id;
    }

    private static function ghostscript_render_pdf($pdf_path) {
        if (!function_exists('exec')) {
            return '';
        }

        $temp_dir = sys_get_temp_dir();
        $outfile = $temp_dir . '/inblue-cover-' . wp_unique_id() . '.jpg';
        $cmd = 'gs -q -dNOPAUSE -dBATCH -sDEVICE=jpeg -r150 -dFirstPage=1 -dLastPage=1 -sOutputFile=' . escapeshellarg($outfile) . ' ' . escapeshellarg($pdf_path) . ' 2>&1';

        $output = array();
        $code = 0;
        exec($cmd, $output, $code);

        if ($code !== 0 || !file_exists($outfile)) {
            if (file_exists($outfile)) {
                @unlink($outfile);
            }
            return '';
        }

        $blob = @file_get_contents($outfile);
        @unlink($outfile);

        return $blob ? $blob : '';
    }

}
