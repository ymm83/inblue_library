<?php
if (!defined('ABSPATH')) exit;

class Inblue_Search {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_inblue_search_books', array($this, 'ajax_search'));
        add_action('wp_ajax_nopriv_inblue_search_books', array($this, 'ajax_search'));
    }

    public function ajax_search() {
        $query    = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
        $category = isset($_POST['category']) ? intval($_POST['category']) : 0;
        $year     = isset($_POST['year']) ? intval($_POST['year']) : 0;
        $page     = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : get_option('inblue_library_books_per_page', 12);
        if ($per_page < 1) {
            $per_page = get_option('inblue_library_books_per_page', 12);
        }
        if ($per_page > 300) {
            $per_page = 300;
        }

        $args = array(
            'post_type'      => 'book',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        );

        if (!empty($query)) {
            $ids = self::find_ids_by_search($query);
            $args['post__in'] = empty($ids) ? array(0) : $ids;
        }

        if ($category > 0) {
            $args['tax_query'][] = array(
                'taxonomy' => 'book_category',
                'field'    => 'term_id',
                'terms'    => $category,
            );
        }

        if ($year > 0) {
            $args['meta_query'][] = array(
                'key'   => '_book_year',
                'value' => $year,
                'type'  => 'NUMERIC',
            );
        }

        $matching = get_posts($args);

        // Mismo orden que la cuadrícula servida por el servidor:
        // por _book_pub_date DESC, libros sin fecha al final.
        $dated   = array();
        $undated = array();
        foreach ($matching as $id) {
            $date = get_post_meta($id, '_book_pub_date', true);
            if ($date) {
                $dated[$id] = strtotime($date);
            } else {
                $undated[] = $id;
            }
        }
        arsort($dated);
        $sorted = array_merge(array_keys($dated), $undated);

        $total     = count($sorted);
        $max_pages = $total > 0 ? (int) ceil($total / $per_page) : 0;
        if ($max_pages > 0 && $page > $max_pages) {
            $page = $max_pages;
        }
        $page_ids = array_slice($sorted, ($page - 1) * $per_page, $per_page);

        $books = array();

        if (!empty($page_ids)) {
            $wp_query = new WP_Query(array(
                'post_type'      => 'book',
                'post_status'    => 'publish',
                'posts_per_page' => count($page_ids),
                'post__in'       => $page_ids,
                'orderby'        => 'post__in',
                'no_found_rows'  => true,
            ));

            while ($wp_query->have_posts()) {
                $wp_query->the_post();
                $id = get_the_ID();
                $cats = wp_get_post_terms($id, 'book_category');
                $cat_names = array();
                foreach ($cats as $c) {
                    $cat_names[] = $c->name;
                }
                $authors = Inblue_CPT::get_card_authors($id);

                $pub_date = get_post_meta($id, '_book_pub_date', true);
                $books[] = array(
                    'id'         => $id,
                    'title'      => get_the_title(),
                    'permalink'  => get_permalink(),
                    'thumbnail'  => get_the_post_thumbnail_url($id, 'medium') ?: '',
                    'authors'    => $authors,
                    'year'       => get_post_meta($id, '_book_year', true),
                    'views'      => Inblue_CPT::get_book_views($id),
                    'is_new'     => Inblue_CPT::is_new_book($id),
                    'is_updated' => Inblue_CPT::is_updated_book($id),
                    'pub_date'   => $pub_date ? date_i18n(get_option('date_format'), strtotime($pub_date)) : '',
                    'isbn'       => get_post_meta($id, '_book_isbn', true),
                    'categories' => implode(', ', $cat_names),
                    'excerpt'    => get_the_excerpt(),
                );
            }

            wp_reset_postdata();
        }

        global $wpdb;
        $years_list = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_book_year' AND meta_value != '' ORDER BY meta_value DESC");

        wp_send_json(array(
            'success'     => true,
            'books'       => $books,
            'total'       => $total,
            'max_pages'   => $max_pages,
            'current'     => $page,
            'years'       => $years_list,
        ));
    }

    public static function get_years() {
        global $wpdb;
        return $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_book_year' AND meta_value != '' ORDER BY meta_value DESC");
    }

    public static function find_ids_by_search($query) {
        global $wpdb;

        $like = '%' . $wpdb->esc_like($query) . '%';

        $sql = $wpdb->prepare(
            "SELECT DISTINCT p.ID
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
             WHERE p.post_type = 'book'
               AND p.post_status = 'publish'
               AND (
                   p.post_title LIKE %s
                   OR p.post_excerpt LIKE %s
                   OR (pm.meta_key IN ('_book_authors','_book_isbn','_book_doi') AND pm.meta_value LIKE %s)
               )",
            $like,
            $like,
            $like
        );

        return array_map('intval', $wpdb->get_col($sql));
    }

    public static function get_categories() {
        return get_terms(array(
            'taxonomy'   => 'book_category',
            'hide_empty' => true,
        ));
    }
}
