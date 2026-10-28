<?php
if (!defined('ABSPATH')) exit;

class Inblue_Library_Public {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_filter('template_include', array($this, 'template_override'));
        add_filter('body_class', array($this, 'body_class'));
        add_filter('kadence_post_layout', array($this, 'kadence_post_layout'));
        add_action('wp', array($this, 'maybe_count_book_view'));
    }

    public function maybe_count_book_view() {
        if (is_singular('book') && !is_preview() && !is_admin()) {
            Inblue_CPT::increment_book_views(get_queried_object_id());
        }
    }

    public function enqueue_assets() {
        $content = is_singular() ? (get_post()->post_content ?? '') : '';
        $has_lib_shortcode = preg_match('/\[library_/', $content);
        if (!is_singular('book') && !$has_lib_shortcode) {
            return;
        }

        wp_enqueue_style('inblue-library-public', INBLUE_LIBRARY_PLUGIN_URL . 'assets/css/public.css', array(), @filemtime(INBLUE_LIBRARY_PLUGIN_DIR . 'assets/css/public.css'));

        wp_enqueue_script('inblue-library-public', INBLUE_LIBRARY_PLUGIN_URL . 'assets/js/public.js', array('jquery'), @filemtime(INBLUE_LIBRARY_PLUGIN_DIR . 'assets/js/public.js'), true);

        wp_localize_script('inblue-library-public', 'inblueLib', array(
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('inblue_library_search'),
            'coverNonce'    => wp_create_nonce('inblue_book_details'),
            'featuredNonce' => wp_create_nonce('inblue_toggle_featured'),
            'perPage'    => get_option('inblue_library_books_per_page', 12),
            'startPage'  => max(1, (int) get_query_var('paged')),
            'i18n'       => array(
                'oneBook'  => _n('%d libro disponible', '%d libros disponibles', 1, 'inblue-library'),
                'manyBooks' => _n('%d libro disponible', '%d libros disponibles', 2, 'inblue-library'),
                'noBooks'  => __('No se encontraron libros.', 'inblue-library'),
            ),
        ));
    }

    public function body_class($classes) {
        if (is_singular()) {
            $post = get_post();
            if ($post && preg_match('/\[library_/', (string) $post->post_content)) {
                $classes[] = 'inblue-library-page';
                if ((int) get_option('inblue_library_hide_page_header', 1)) {
                    $classes[] = 'inblue-hide-page-header';
                }
            }
        }
        return $classes;
    }

    public function kadence_post_layout($layout) {
        if (is_page() && preg_match('/\[library_/', (string) get_post()->post_content)
            && (int) get_option('inblue_library_hide_page_header', 1)) {
            $layout['title'] = 'hide';
        }
        return $layout;
    }

    public function template_override($template) {
        if (is_singular('book')) {
            $file = INBLUE_LIBRARY_PLUGIN_DIR . 'public/templates/single-book.php';
            return file_exists($file) ? $file : $template;
        }
        return $template;
    }
}
