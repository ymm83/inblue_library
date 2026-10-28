<?php
/**
 * Plugin Name: INBLUE Library
 * Plugin URI: https://inblueeditorial.com
 * Description: Catálogo de libros con portada, autores múltiples, ISBN, DOI, PDF (subido o Drive), buscador AJAX y visor embebido.
 * Version: 1.3.8
 * Author: YMM by INBLUE Editorial
 * Author URI: https://inblueeditorial.com
 * Text Domain: inblue-library
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package INBLUE_Library
 */

if (!defined('ABSPATH')) {
    exit;
}

define('INBLUE_LIBRARY_VERSION', '1.3.8');
define('INBLUE_LIBRARY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('INBLUE_LIBRARY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('INBLUE_LIBRARY_PLUGIN_BASENAME', plugin_basename(__FILE__));

final class Inblue_Library {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    private function __clone() {}

    public function __wakeup() {
        throw new \Exception('Cannot unserialize singleton');
    }

    private function includes() {
        require_once INBLUE_LIBRARY_PLUGIN_DIR . 'includes/class-cpt.php';
        require_once INBLUE_LIBRARY_PLUGIN_DIR . 'includes/class-metaboxes.php';
        require_once INBLUE_LIBRARY_PLUGIN_DIR . 'includes/class-search.php';
        require_once INBLUE_LIBRARY_PLUGIN_DIR . 'includes/class-shortcodes.php';
        require_once INBLUE_LIBRARY_PLUGIN_DIR . 'includes/class-gitbook-importer.php';
        require_once INBLUE_LIBRARY_PLUGIN_DIR . 'includes/class-import-export.php';
        require_once INBLUE_LIBRARY_PLUGIN_DIR . 'admin/class-admin.php';
        require_once INBLUE_LIBRARY_PLUGIN_DIR . 'public/class-public.php';
    }

    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'init_components'), 10);
        add_action('init', array($this, 'init_cpt'), 10);
        add_action('init', array($this, 'load_textdomain'));
        add_action('admin_init', array($this, 'maybe_flush_rewrite_rules'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    public function load_textdomain() {
        load_plugin_textdomain('inblue-library', false, dirname(INBLUE_LIBRARY_PLUGIN_BASENAME) . '/languages');
    }

    public function maybe_flush_rewrite_rules() {
        if (get_option('inblue_library_rewrite_version') !== INBLUE_LIBRARY_VERSION) {
            $this->init_cpt();
            flush_rewrite_rules();
            update_option('inblue_library_rewrite_version', INBLUE_LIBRARY_VERSION);
        }
    }

    public function init_cpt() {
        Inblue_CPT::get_instance()->register();
    }

    public function init_components() {
        Inblue_Metaboxes::get_instance();
        Inblue_Search::get_instance();
        Inblue_Library_Shortcodes::get_instance();

        if (is_admin()) {
            Inblue_Library_Admin::get_instance();
        }
        if (!is_admin()) {
            Inblue_Library_Public::get_instance();
        }
    }

    public function activate() {
        $this->init_cpt();
        flush_rewrite_rules();
        add_option('inblue_library_viewer_type', 'browser');
        add_option('inblue_library_books_per_page', 12);
    }

    public function deactivate() {
        flush_rewrite_rules();
    }
}

function inblue_library() {
    return Inblue_Library::get_instance();
}

inblue_library();
