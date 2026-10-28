<?php
$categories = isset($categories) ? $categories : Inblue_Search::get_categories();
$years = isset($years) ? $years : Inblue_Search::get_years();

$show_title = isset($show_title) ? (bool) $show_title : false;
$title = isset($title) ? $title : '';
if ($show_title && '' === trim($title)) {
    $title = get_the_title();
}
if ($show_title && '' === trim($title)) {
    $title = __('Biblioteca', 'inblue-library');
}
$title_icon = (int) get_option('inblue_library_title_icon', 0);

$paged = get_query_var('paged') ? get_query_var('paged') : 1;
$per_page = get_option('inblue_library_books_per_page', 12);

$active_category = isset($_GET['category']) ? sanitize_title(wp_unslash($_GET['category'])) : '';
$active_category_id = 0;
foreach ((array)$categories as $cat) {
    if ($cat->slug === $active_category) {
        $active_category_id = (int) $cat->term_id;
        break;
    }
}
// Slug indicado que no corresponde a ninguna categoría (renombrada, borrada o
// mal escrita): el filtro tiene que dar 0 resultados, no ignorarse y dejar
// pasar el listado completo.
$category_unknown = ('' !== $active_category && ! $active_category_id);
// "year" y "s" son query vars nativas de WordPress (archivos por fecha y
// búsqueda) que harían devolver 404 a la página; el guard en pre_get_posts
// (class-cpt.php) las neutraliza en la página de biblioteca, así que aquí
// las leemos con normalidad.
$active_year = isset($_GET['year']) ? sanitize_text_field(wp_unslash($_GET['year'])) : '';

// "q" en lugar de "s": "s" es la query var nativa de búsqueda. El guard de
// pre_get_posts la anula en la biblioteca, por lo que aquí la leemos con
// normalidad.
$active_query = '';
if (isset($_GET['q'])) {
    $active_query = sanitize_text_field(wp_unslash($_GET['q']));
} elseif (isset($_GET['s'])) {
    $active_query = sanitize_text_field(wp_unslash($_GET['s']));
}

$tax_query = array();
if ($active_category_id) {
    $tax_query = array(array(
        'taxonomy' => 'book_category',
        'field'    => 'term_id',
        'terms'    => $active_category_id,
    ));
}
$ids = Inblue_CPT::get_sorted_book_ids(-1, $tax_query);

if ($category_unknown) {
    $ids = array();
}

if ('' !== $active_query) {
    $matched = Inblue_Search::find_ids_by_search($active_query);
    $ids = array_values(array_intersect($ids, $matched));
}
if ('' !== $active_year) {
    $filtered = array();
    foreach ($ids as $id) {
        if ((string) get_post_meta($id, '_book_year', true) === (string) $active_year) {
            $filtered[] = $id;
        }
    }
    $ids = $filtered;
}

$total       = count($ids);
$total_pages = $total > 0 ? (int) ceil($total / $per_page) : 1;
if ($paged > $total_pages) {
    $paged = $total_pages;
}
$offset    = ($paged - 1) * $per_page;
$page_ids  = array_slice($ids, $offset, $per_page);

$query = new WP_Query(array(
    'post_type'      => 'book',
    'post_status'    => 'publish',
    'posts_per_page' => $per_page,
    // WP ignora por completo un post__in vacío y devolvería TODOS los libros,
    // así que un filtro sin resultados tendría que forzarse con el ID 0.
    'post__in'       => $page_ids ? $page_ids : array(0),
    'orderby'        => 'post__in',
));
$query->found_posts   = $total;
$query->max_num_pages = $total_pages;
?><div class="inblue-library-wrap">

    <header class="inblue-archive-header">
        <?php if ($show_title) : ?>
            <h1 class="inblue-archive-title">
                <?php if ($title_icon) : ?>
                    <?php echo wp_get_attachment_image($title_icon, 'thumbnail', false, array('class' => 'inblue-archive-title-icon')); ?>
                <?php endif; ?>
                <span class="inblue-archive-title-text"><?php echo esc_html($title); ?></span>
            </h1>
        <?php endif; ?>
        <?php if (!empty($categories)) : ?>
            <p class="inblue-archive-desc" id="inblue-archive-desc"><?php echo esc_html(sprintf(_n('%d libro disponible', '%d libros disponibles', $total, 'inblue-library'), $total)); ?></p>
        <?php endif; ?>
    </header>

    <div class="inblue-search-bar">
        <input type="text" id="inblue-search-input" placeholder="<?php esc_attr_e('Buscar libros...', 'inblue-library'); ?>" value="<?php echo esc_attr($active_query); ?>">
        <select id="inblue-filter-category">
            <option value="0"><?php _e('Todas las categorías', 'inblue-library'); ?></option>
            <?php foreach ($categories as $cat) : ?>
                <option value="<?php echo esc_attr($cat->term_id); ?>" data-slug="<?php echo esc_attr($cat->slug); ?>" <?php selected($active_category_id, (int) $cat->term_id); ?>><?php echo esc_html($cat->name); ?></option>
            <?php endforeach; ?>
        </select>
        <select id="inblue-filter-year">
            <option value="0"><?php _e('Todos los años', 'inblue-library'); ?></option>
            <?php foreach ($years as $y) : ?>
                <option value="<?php echo esc_attr($y); ?>" <?php selected($active_year, (string) $y); ?>><?php echo esc_html($y); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div id="inblue-results" class="inblue-grid" data-total="<?php echo esc_attr($total); ?>" data-per-page="<?php echo esc_attr($per_page); ?>">
        <?php if ($query->have_posts()) : ?>
            <?php while ($query->have_posts()) : $query->the_post();
                $id = get_the_ID();
                $pub_date = get_post_meta($id, '_book_pub_date', true);
                $year = get_post_meta($id, '_book_year', true);
                $views = Inblue_CPT::get_book_views($id);
                $is_new = Inblue_CPT::is_new_book($id);
                $is_updated = Inblue_CPT::is_updated_book($id);
            ?>
            <div class="inblue-grid-item">
                <a href="<?php the_permalink(); ?>">
                    <?php if (has_post_thumbnail()) : ?>
                        <?php the_post_thumbnail('medium'); ?>
                    <?php else : ?>
                        <div class="inblue-no-cover"><?php _e('Sin portada', 'inblue-library'); ?></div>
                    <?php endif; ?>
                    <div class="inblue-grid-info">
                        <h3><?php the_title(); ?></h3>
                    </div>
                    <div class="inblue-grid-footer">
                        <?php if ($is_new) : ?>
                            <span class="inblue-grid-year is-new"><?php _e('Nuevo', 'inblue-library'); ?></span>
                        <?php elseif ($is_updated) : ?>
                            <span class="inblue-grid-year is-updated"><?php _e('Actualizado', 'inblue-library'); ?></span>
                        <?php elseif ($year) : ?>
                            <span class="inblue-grid-year"><?php echo esc_html($year); ?></span>
                        <?php endif; ?>
                        <span class="inblue-grid-views" title="<?php esc_attr_e('Visitas', 'inblue-library'); ?>"><?php echo esc_html(number_format_i18n($views)); ?></span>
                    </div>
                </a>
            </div>
            <?php endwhile; ?>
        <?php else : ?>
            <p><?php _e('No se encontraron libros.', 'inblue-library'); ?></p>
        <?php endif; ?>
        <?php wp_reset_postdata(); ?>
    </div>

    <div id="inblue-pagination" class="inblue-pagination">
        <?php
        $big = 999999999;
        echo paginate_links(array(
            'base'    => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
            'format'  => '?paged=%#%',
            'current' => max(1, $paged),
            'total'   => $query->max_num_pages,
            'type'    => 'list',
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
        ));
        ?>
    </div>

</div>
