<?php
if (!defined('ABSPATH')) exit;

class Inblue_Library_Shortcodes {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode('library_grid', array($this, 'grid'));
        add_shortcode('library_search', array($this, 'search'));
        add_shortcode('library_featured', array($this, 'featured'));
    }

    public function grid($atts) {
        $atts = shortcode_atts(array(
            'category' => '',
            'limit'    => 12,
            'orderby'  => 'date',
            'order'    => 'DESC',
        ), $atts);

        $args = array(
            'post_type'      => 'book',
            'posts_per_page' => intval($atts['limit']),
            'orderby'        => $atts['orderby'],
            'order'          => $atts['order'],
        );

        if (!empty($atts['category'])) {
            $args['tax_query'] = array(array(
                'taxonomy' => 'book_category',
                'field'    => 'slug',
                'terms'    => $atts['category'],
            ));
        }

        $query = new WP_Query($args);
        ob_start();

        if ($query->have_posts()) :
        ?>
        <div class="inblue-grid">
            <?php while ($query->have_posts()) : $query->the_post();
                $id = get_the_ID();
                $pub_date = get_post_meta($id, '_book_pub_date', true);
                $is_new = Inblue_CPT::is_new_book($id);
                $is_updated = Inblue_CPT::is_updated_book($id);
            ?>
            <div class="inblue-grid-item">
                <a href="<?php the_permalink(); ?>">
                    <?php if (has_post_thumbnail()) : ?>
                        <?php the_post_thumbnail('medium'); ?>
                    <?php else : ?>
                        <div class="inblue-no-cover"><?php _e('No Cover', 'inblue-library'); ?></div>
                    <?php endif; ?>
                    <div class="inblue-grid-info">
                        <h3><?php the_title(); ?></h3>
                        <?php if ($is_new) : ?>
                            <span class="inblue-grid-year is-new"><?php _e('Nuevo', 'inblue-library'); ?></span>
                        <?php elseif ($is_updated) : ?>
                            <span class="inblue-grid-year is-updated"><?php _e('Actualizado', 'inblue-library'); ?></span>
                        <?php elseif ($pub_date) : ?><span class="inblue-grid-year"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($pub_date))); ?></span><?php endif; ?>
                    </div>
                </a>
            </div>
            <?php endwhile; ?>
        </div>
        <?php
        else :
            echo '<p>' . __('No books found.', 'inblue-library') . '</p>';
        endif;

        wp_reset_postdata();
        return ob_get_clean();
    }

    public function search($atts) {
        $atts = shortcode_atts(array(
            'title' => '',
        ), $atts, 'library_search');

        $custom_title = trim($atts['title']);

        $title = $custom_title;
        if ('' === $title) {
            $title = get_the_title();
        }
        if ('' === trim($title)) {
            $title = __('Biblioteca', 'inblue-library');
        }

        $stored = get_option('inblue_library_title', '');
        if ($stored !== $title) {
            update_option('inblue_library_title', $title);
        }

        $show_title = true;

        $categories = Inblue_Search::get_categories();
        $years = Inblue_Search::get_years();
        $per_page = get_option('inblue_library_books_per_page', 12);
        $viewer_type = get_option('inblue_library_viewer_type', 'browser');

        ob_start();
        include INBLUE_LIBRARY_PLUGIN_DIR . 'public/templates/archive-book.php';
        return ob_get_clean();
    }

    public function featured($atts) {
        $atts = shortcode_atts(array('count' => 5, 'title' => __('Libros destacados', 'inblue-library')), $atts);
        $count = intval($atts['count']);
        if ($count < 1) {
            $count = 5;
        }

        $library_page_id = (int) get_option('inblue_library_page_id', 0);
        $library_url = $library_page_id ? get_permalink($library_page_id) : '';

        $sort_by_pub_date = function ($ids) {
            usort($ids, function ($a, $b) {
                $da = get_post_meta($a, '_book_pub_date', true);
                $db = get_post_meta($b, '_book_pub_date', true);
                if ($da === $db) {
                    $pa = get_post_field('post_date', $a);
                    $pb = get_post_field('post_date', $b);
                    return strcmp($pb, $pa);
                }
                if ($da === '') return 1;
                if ($db === '') return -1;
                return strcmp($db, $da);
            });
            return $ids;
        };

        // Ordena IDs por un meta de fecha DESC (vacíos al final)
        $sort_by_update_date = function ($ids) {
            usort($ids, function ($a, $b) {
                $ua = get_post_meta($a, '_book_update_date', true);
                $ub = get_post_meta($b, '_book_update_date', true);
                if ($ua === $ub) return 0;
                if ($ua === '') return 1;
                if ($ub === '') return -1;
                return strcmp($ub, $ua);
            });
            return $ids;
        };

        // 1) Libros marcados como destacados en la BD, ordenados por fecha de publicación (la última primero)
        $featured_ids = get_posts(array(
            'post_type'      => 'book',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_key'       => '_book_featured',
            'meta_value'     => '1',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ));
        $featured_ids = $sort_by_pub_date($featured_ids);
        $featured_ids = array_slice($featured_ids, 0, $count);
        $exclude = $featured_ids;

        // 2) Relleno: libros con fecha de publicación, la más reciente primero.
        //    Los libros aprobados como "Actualizado" (_book_show_updated='1')
        //    quedan excluidos aquí: van al final, tras los nuevos (paso 4).
        if (count($featured_ids) < $count) {
            $need = $count - count($featured_ids);
            $fill = get_posts(array(
                'post_type'      => 'book',
                'post_status'    => 'publish',
                'posts_per_page' => $need,
                'fields'         => 'ids',
                'post__not_in'   => !empty($exclude) ? $exclude : array(0),
                'meta_query'     => array(
                    'relation' => 'AND',
                    array('key' => '_book_pub_date', 'compare' => 'EXISTS'),
                    array(
                        'relation' => 'OR',
                        array('key' => '_book_show_updated', 'compare' => 'NOT EXISTS'),
                        array('key' => '_book_show_updated', 'value' => '1', 'compare' => '!=')
                    ),
                ),
                'orderby'        => 'meta_value',
                'order'          => 'DESC',
                'no_found_rows'  => true,
            ));
            $featured_ids = array_merge($featured_ids, $fill);
            $exclude = $featured_ids;
        }

        // 3) Si aún faltan: libros sin fecha de publicación, los más recientes primero
        if (count($featured_ids) < $count) {
            $need = $count - count($featured_ids);
            $fill = get_posts(array(
                'post_type'      => 'book',
                'post_status'    => 'publish',
                'posts_per_page' => $need,
                'fields'         => 'ids',
                'post__not_in'   => !empty($exclude) ? $exclude : array(0),
                'meta_query'     => array(
                    'relation' => 'AND',
                    array('key' => '_book_pub_date', 'compare' => 'NOT EXISTS'),
                    array(
                        'relation' => 'OR',
                        array('key' => '_book_show_updated', 'compare' => 'NOT EXISTS'),
                        array('key' => '_book_show_updated', 'value' => '1', 'compare' => '!=')
                    ),
                ),
                'orderby'        => 'date',
                'order'          => 'DESC',
                'no_found_rows'  => true,
            ));
            $featured_ids = array_merge($featured_ids, $fill);
            $exclude = $featured_ids;
        }

        // 4) Al final: libros aprobados como "Actualizado", por fecha de
        //    actualización DESC. Entran SIEMPRE: si hacen falta hueco,
        //    desplazan a los últimos libros nuevos del relleno.
        $approved_updated = get_posts(array(
            'post_type'      => 'book',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                'relation' => 'AND',
                array('key' => '_book_show_updated', 'value' => '1'),
                array('key' => '_book_update_date', 'compare' => 'EXISTS'),
            ),
            'no_found_rows'  => true,
        ));
        // La etiqueta "Actualizado" dura la mitad de los días de "Nuevo":
        // filtrar los que ya expiraron (is_updated_book aplica ese plazo).
        $approved_updated = array_values(array_filter($approved_updated, 'Inblue_CPT::is_updated_book'));
        $approved_updated = $sort_by_update_date($approved_updated);

        if (!empty($approved_updated)) {
            // Reservar hueco: recortar el relleno previo
            $need = count($approved_updated);
            if (count($featured_ids) > $count - $need) {
                $featured_ids = array_slice($featured_ids, 0, max(0, $count - $need));
            }
            // Añadir los aprobados sin duplicar
            foreach ($approved_updated as $uid) {
                if (!in_array($uid, $featured_ids)) {
                    $featured_ids[] = $uid;
                }
            }
            // Recorte final de seguridad
            $featured_ids = array_slice($featured_ids, 0, $count);
        }

        ob_start();

        if (!empty($featured_ids)) :
            $query = new WP_Query(array(
                'post_type'      => 'book',
                'posts_per_page' => count($featured_ids),
                'post__in'       => $featured_ids,
                'orderby'        => 'post__in',
            ));
        ?>
        <section class="inblue-featured">
            <div class="inblue-featured-head">
                <h2><?php echo esc_html($atts['title']); ?></h2>
                <?php if ($library_url) : ?>
                    <a class="inblue-featured-viewall" href="<?php echo esc_url($library_url); ?>"><?php _e('Ver todos →', 'inblue-library'); ?></a>
                <?php endif; ?>
            </div>
            <div class="inblue-featured-wrap">
                <button class="inblue-featured-arrow inblue-featured-arrow--prev is-hidden" aria-label="<?php esc_attr_e('Anterior', 'inblue-library'); ?>">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="13 15 8 10 13 5"/></svg>
                </button>
                <div class="inblue-featured-body">
                <?php while ($query->have_posts()) : $query->the_post(); ?>
                <div class="inblue-featured-item">
                    <a href="<?php the_permalink(); ?>">
                        <div class="inblue-featured-cover">
                            <?php if (has_post_thumbnail()) : ?>
                                <?php the_post_thumbnail('medium'); ?>
                            <?php else : ?>
                                <div class="inblue-no-cover"><?php _e('Sin portada', 'inblue-library'); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="inblue-featured-info">
                            <h3><?php the_title(); ?></h3>
                        </div>
                    </a>
                </div>
                <?php endwhile; ?>
                </div>
                <button class="inblue-featured-arrow inblue-featured-arrow--next" aria-label="<?php esc_attr_e('Siguiente', 'inblue-library'); ?>">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="7 5 12 10 7 15"/></svg>
                </button>
            </div>
        </section>
        <?php
        endif;

        wp_reset_postdata();
        return ob_get_clean();
    }
}
