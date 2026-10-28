<?php
if (!defined('ABSPATH')) exit;

class Inblue_CPT {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function register() {
        $this->register_book_cpt();
        $this->register_taxonomies();
        add_action('save_post_page', array($this, 'maybe_refresh_library_base'), 10, 2);
        add_action('trashed_post', array($this, 'maybe_refresh_library_base'), 10, 1);
        add_action('untrashed_post', array($this, 'maybe_refresh_library_base'), 10, 1);
        add_action('pre_get_posts', array($this, 'ignore_search_var_on_library_page'));
    }

    /**
     * En la página de la biblioteca la búsqueda la hace el shortcode (parámetro
     * "q"). Si alguien llega con ?s=..., WordPress lo interpreta como búsqueda
     * global y devuelve 404, así que lo neutralizamos en esa página.
     */
    public function ignore_search_var_on_library_page($query) {
        if (is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query()) {
            return;
        }

        $library_id = self::get_library_base_page_id();
        if (! $library_id) {
            return;
        }

        $is_library_page = (int) $query->get('page_id') === $library_id
            || (int) $query->get('p') === $library_id;

        if (! $is_library_page) {
            $pagename = $query->get('pagename');
            if ($pagename) {
                $found = get_page_by_path($pagename);
                $is_library_page = $found && (int) $found->ID === $library_id;
            }
        }

        if ($is_library_page) {
            // "s" (búsqueda) y "year" (archives por fecha) son query vars
            // nativas: hacen que la página devuelva 404. La búsqueda y el
            // filtro de año del shortcode usan "q" y "year"; el guard las
            // anula del query principal pero el shortcode las lee de $_GET.
            foreach (array('s', 'year') as $native_var) {
                if ($query->get($native_var)) {
                    $query->set($native_var, '');
                }
            }
        }
    }

    private function register_book_cpt() {
        $labels = array(
            'name'                  => __('Libros', 'inblue-library'),
            'singular_name'         => __('Libro', 'inblue-library'),
            'menu_name'             => __('INBLUE Biblioteca', 'inblue-library'),
            'add_new'               => __('Añadir Libro', 'inblue-library'),
            'add_new_item'          => __('Añadir Nuevo Libro', 'inblue-library'),
            'edit_item'             => __('Editar Libro', 'inblue-library'),
            'view_item'             => __('Ver Libro', 'inblue-library'),
            'search_items'          => __('Buscar Libros', 'inblue-library'),
            'not_found'             => __('No se encontraron libros', 'inblue-library'),
            'not_found_in_trash'    => __('No se encontraron libros en la papelera', 'inblue-library'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'menu_position'      => 5,
            'menu_icon'          => 'dashicons-book-alt',
            'capability_type'    => 'post',
            'supports'           => array('title', 'thumbnail', 'excerpt'),
            'has_archive'        => false,
            'rewrite'            => false,
            'query_var'          => true,
        );

        register_post_type('book', $args);

        $base = self::get_library_base();
        if (!empty($base)) {
            global $wp_rewrite;
            $pagination_base = isset($wp_rewrite) ? $wp_rewrite->pagination_base : 'page';
            // Paginación de la página de librería: /libreria/page/2
            add_rewrite_rule($base . '/' . $pagination_base . '/([0-9]{1,})/?$', 'index.php?pagename=' . $base . '&paged=$matches[1]', 'top');
            add_rewrite_rule($base . '/([^/]+)/([^/]+)/?$', 'index.php?book_category=$matches[1]&book=$matches[2]', 'top');
            add_rewrite_rule($base . '/([^/]+)/?$', 'index.php?book=$matches[1]', 'top');
        }

        add_filter('post_type_link', array($this, 'book_permalink'), 10, 2);
    }

    public function book_permalink($url, $post) {
        if ($post->post_type === 'book') {
            $base = self::get_library_base();
            $terms = wp_get_post_terms($post->ID, 'book_category');
            if (!empty($terms) && !is_wp_error($terms)) {
                $url = home_url(user_trailingslashit($base . '/' . $terms[0]->slug . '/' . $post->post_name));
            } else {
                $url = home_url(user_trailingslashit($base . '/' . $post->post_name));
            }
        }
        return $url;
    }

    public static function get_library_base_page_id() {
        $cached = wp_cache_get('inblue_library_base_page', 'inblue_library');
        if (false !== $cached) {
            return (int) $cached;
        }

        $page_id = (int) get_option('inblue_library_page_id', 0);
        if (!$page_id) {
            $page_id = (int) get_option('inblue_library_base_page', 0);
        }
        if (!$page_id) {
            $page_id = self::find_library_page();
            if ($page_id) {
                update_option('inblue_library_base_page', $page_id);
            }
        }

        wp_cache_set('inblue_library_base_page', (int) $page_id, 'inblue_library', 300);
        return (int) $page_id;
    }

    private static function find_library_page() {
        global $wpdb;
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'page'
               AND post_status = 'publish'
               AND post_content LIKE %s
             ORDER BY ID ASC
             LIMIT 1",
            '%[library_%'
        ));
        return $id ? (int) $id : 0;
    }

    public static function get_library_base() {
        $page_id = self::get_library_base_page_id();
        if ($page_id) {
            $uri = get_page_uri($page_id);
            if (!empty($uri)) {
                return $uri;
            }
        }
        return 'library';
    }

    public static function get_library_page_url() {
        $page_id = self::get_library_base_page_id();
        return $page_id ? get_permalink($page_id) : '';
    }

    public static function get_library_title() {
        $title = get_option('inblue_library_title', '');
        if ('' === trim($title)) {
            $page_id = self::get_library_base_page_id();
            if ($page_id) {
                $title = get_the_title($page_id);
            }
        }
        if ('' === trim($title)) {
            $title = __('Biblioteca', 'inblue-library');
        }
        return $title;
    }

    public static function get_card_authors($post_id, $limit = 3) {
        $authors = get_post_meta($post_id, '_book_authors', true);
        if (empty($authors)) {
            return '';
        }
        $list = array_values(array_filter(array_map('trim', explode(',', (string) $authors))));
        if (empty($list)) {
            return '';
        }
        return implode(', ', array_slice($list, 0, max(1, (int) $limit)));
    }

    public static function get_sorted_book_ids($limit = -1, $tax_query = array()) {
        $args = array(
            'post_type'      => 'book',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        );
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }
        $ids = get_posts($args);

        $dated   = array();
        $undated = array();
        foreach ($ids as $id) {
            $date = get_post_meta($id, '_book_pub_date', true);
            if ($date) {
                $dated[$id] = strtotime($date);
            } else {
                $undated[] = $id;
            }
        }
        arsort($dated);
        $sorted = array_merge(array_keys($dated), $undated);
        if ($limit > 0) {
            $sorted = array_slice($sorted, 0, $limit);
        }
        return $sorted;
    }

    public static function get_book_views($post_id) {
        return (int) get_post_meta($post_id, '_book_views', true);
    }

    public static function is_new_book($post_id) {
        // Un libro aprobado como "Actualizado" ya no muestra la etiqueta "Nuevo"
        if (self::is_updated_book($post_id)) {
            return false;
        }
        $pub_date = get_post_meta($post_id, '_book_pub_date', true);
        if (empty($pub_date)) {
            return false;
        }
        $ts = strtotime($pub_date);
        if (!$ts || $ts <= 0) {
            return false;
        }
        $days = (int) get_option('inblue_library_new_book_days', 180);
        if ($days <= 0) {
            $days = 180;
        }
        return (time() - $ts) <= $days * DAY_IN_SECONDS;
    }

    // Libro con fecha de actualización Y aprobado manualmente para mostrarse
    // como "Actualizado" (checkbox del metabox). La etiqueta dura la mitad
    // de los días configurados para "Nuevo" (inblue_library_new_book_days).
    public static function is_updated_book($post_id) {
        if (get_post_meta($post_id, '_book_show_updated', true) !== '1') {
            return false;
        }
        $update_date = get_post_meta($post_id, '_book_update_date', true);
        if (empty($update_date)) {
            return false;
        }
        $ts = strtotime($update_date);
        if (!$ts || $ts <= 0) {
            return false;
        }
        $days = (int) get_option('inblue_library_new_book_days', 180);
        if ($days <= 0) {
            $days = 180;
        }
        $half = (int) ceil($days / 2);
        return (time() - $ts) <= $half * DAY_IN_SECONDS;
    }

    public static function increment_book_views($post_id) {
        if (!$post_id) {
            return;
        }
        update_post_meta($post_id, '_book_views', self::get_book_views($post_id) + 1);
    }

    public static function get_category_url($term) {
        $page_url = self::get_library_page_url();
        if (!$page_url) {
            return $term ? get_term_link($term) : home_url('/');
        }
        $slug = is_object($term) ? $term->slug : $term;
        return add_query_arg('category', rawurlencode($slug), $page_url);
    }

    public function maybe_refresh_library_base($post_id, $post = null) {
        if (is_object($post_id) && isset($post_id->post_type)) {
            $post = $post_id;
            $post_id = $post->ID;
        }

        if (get_post_type($post_id) !== 'page') {
            return;
        }

        wp_cache_delete('inblue_library_base_page', 'inblue_library');
        delete_option('inblue_library_base_page');

        $page_id = self::find_library_page();
        if ($page_id) {
            update_option('inblue_library_base_page', $page_id);
        }

        $this->init();
        flush_rewrite_rules();
    }

    public function init() {
        $this->register_book_cpt();
        $this->register_taxonomies();
    }

    private function register_taxonomies() {
        $cat_labels = array(
            'name'              => __('Categorías de Libros', 'inblue-library'),
            'singular_name'     => __('Categoría de Libro', 'inblue-library'),
            'search_items'      => __('Buscar Categorías', 'inblue-library'),
            'all_items'         => __('Todas las Categorías', 'inblue-library'),
            'parent_item'       => __('Categoría Padre', 'inblue-library'),
            'parent_item_colon' => __('Categoría Padre:', 'inblue-library'),
            'edit_item'         => __('Editar Categoría', 'inblue-library'),
            'update_item'       => __('Actualizar Categoría', 'inblue-library'),
            'add_new_item'      => __('Añadir Nueva Categoría', 'inblue-library'),
            'new_item_name'     => __('Nombre de Nueva Categoría', 'inblue-library'),
            'menu_name'         => __('Categorías', 'inblue-library'),
        );

        register_taxonomy('book_category', 'book', array(
            'labels'            => $cat_labels,
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => array('slug' => 'library-category'),
        ));

        $tag_labels = array(
            'name'          => __('Etiquetas de Libros', 'inblue-library'),
            'singular_name' => __('Etiqueta de Libro', 'inblue-library'),
            'menu_name'     => __('Etiquetas', 'inblue-library'),
        );

        register_taxonomy('book_tag', 'book', array(
            'labels'            => $tag_labels,
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => array('slug' => 'library-tag'),
        ));
    }
}
