<?php get_header(); ?>
<?php while (have_posts()) : the_post();
    $id = get_the_ID();
    $authors      = get_post_meta($id, '_book_authors', true);
    $pub_date     = get_post_meta($id, '_book_pub_date', true);
    $update_date  = get_post_meta($id, '_book_update_date', true);
    $isbn         = get_post_meta($id, '_book_isbn', true);
    $doi          = get_post_meta($id, '_book_doi', true);
    $num_pages    = get_post_meta($id, '_book_pages', true);
    $publisher    = get_post_meta($id, '_book_publisher', true);
    $language     = get_post_meta($id, '_book_language', true);
    $pdf_source   = get_post_meta($id, '_book_pdf_source', true);
    $pdf_url      = get_post_meta($id, '_book_pdf_url', true);
    $pdf_file_id  = get_post_meta($id, '_book_pdf_file_id', true);
    $gitbook_url  = get_post_meta($id, '_book_gitbook_url', true);
    $pdf_file_url = $pdf_file_id ? wp_get_attachment_url($pdf_file_id) : '';
    $final_pdf_url = $pdf_source === 'url' ? $pdf_url : $pdf_file_url;
    $subtitle     = get_post_meta($id, '_book_subtitle', true) ?: '';

    $viewer_type = get_option('inblue_library_viewer_type', 'browser');

    $cats = wp_get_post_terms($id, 'book_category');
    $cat_names = array();
    foreach ($cats as $c) {
        $cat_names[] = $c->name;
    }

    $langs = array(
        'es' => __('Spanish', 'inblue-library'),
        'en' => __('English', 'inblue-library'),
        'pt' => __('Portuguese', 'inblue-library'),
        'fr' => __('French', 'inblue-library'),
        'other' => __('Other', 'inblue-library'),
    );
    $lang_label = isset($langs[$language]) ? $langs[$language] : $language;

    $author_list = array();
    if (!empty($authors)) {
        $author_list = array_map('trim', explode(',', $authors));
    }
?>
    <article id="book-<?php echo esc_attr($id); ?>" class="inblue-single-book">

        <?php $library_page_url = Inblue_CPT::get_library_page_url(); ?>
        <?php $library_label = Inblue_CPT::get_library_title(); ?>
        <nav class="inblue-breadcrumb">
            <a href="<?php echo esc_url($library_page_url ? $library_page_url : home_url('/')); ?>"><?php echo esc_html($library_label); ?></a>
            <?php if (!empty($cat_names)) : ?>
                <span class="inblue-breadcrumb-sep">/</span>
                <?php foreach ($cats as $c) : ?>
                    <a href="<?php echo esc_url(Inblue_CPT::get_category_url($c)); ?>"><?php echo esc_html($c->name); ?></a>
                <?php endforeach; ?>
            <?php endif; ?>
            <span class="inblue-breadcrumb-sep">/</span>
            <span class="inblue-breadcrumb-current"><?php the_title(); ?></span>
        </nav>

        <?php if ($library_page_url) : ?>
            <div class="inblue-back-row">
                <a href="<?php echo esc_url($library_page_url); ?>"
                    class="inblue-back-btn"
                    data-library-url="<?php echo esc_url($library_page_url); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                    <?php _e('Regresar', 'inblue-library'); ?>
                </a>
            </div>
        <?php endif; ?>

        <div class="inblue-single-layout">
            <div class="inblue-single-cover">
                <?php if (has_post_thumbnail()) : ?>
                    <div class="inblue-cover-frame">
                        <?php the_post_thumbnail('large'); ?>
                    </div>
                <?php else : ?>
                    <div class="inblue-cover-frame inblue-cover-frame-empty">
                        <?php if (!empty($final_pdf_url) && current_user_can('edit_post', $id)) : ?>
                            <button type="button" class="inblue-get-cover-front inblue-get-cover-btn"
                                data-post-id="<?php echo esc_attr($id); ?>"
                                data-pdf-source="<?php echo esc_attr($pdf_source ? $pdf_source : 'url'); ?>"
                                data-pdf-url="<?php echo esc_attr($pdf_url); ?>"
                                data-pdf-file-id="<?php echo esc_attr($pdf_file_id ? $pdf_file_id : ''); ?>">
                                <svg class="inblue-btn-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                    <polyline points="21 15 16 10 5 21"></polyline>
                                </svg>
                                <?php _e('Obtener portada', 'inblue-library'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (current_user_can('edit_post', $id)) : ?>
                    <?php $is_featured = get_post_meta($id, '_book_featured', true) === '1'; ?>
                    <div class="inblue-cover-actions-row">
                        <a href="<?php echo esc_url(get_edit_post_link($id)); ?>" class="inblue-edit-link" target="_blank" rel="noopener">
                            <svg class="inblue-btn-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
                            </svg>
                            <?php _e('Editar', 'inblue-library'); ?>
                        </a>
                        <button type="button"
                            class="inblue-featured-toggle <?php echo $is_featured ? 'is-featured' : ''; ?>"
                            data-post-id="<?php echo esc_attr($id); ?>">
                            <svg class="inblue-btn-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                            </svg>
                            <span class="inblue-featured-label"><?php echo $is_featured ? __('Desmarcar', 'inblue-library') : __('Destacar', 'inblue-library'); ?></span>
                        </button>
                    </div>
                    <span class="inblue-featured-status"></span>
                    <span class="inblue-cover-status"></span>
                <?php endif; ?>
            </div>

            <div class="inblue-single-info">
                <h1 class="inblue-single-title"><?php the_title(); ?></h1>

                <?php if ($subtitle) : ?>
                    <p class="inblue-single-subtitle"><?php echo esc_html($subtitle); ?></p>
                <?php endif; ?>

                <div class="inblue-single-desc">
                    <?php the_content(); ?>
                </div>

                <?php if (!empty($author_list)) : ?>
                    <div class="inblue-single-authors">
                        <span class="inblue-authors-label"><?php echo count($author_list) > 1 ? __('Autores:', 'inblue-library') : __('Autor:', 'inblue-library'); ?></span>
                        <?php foreach ($author_list as $author) : ?>
                            <span class="inblue-author-badge"><?php echo esc_html($author); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="inblue-single-meta">
                    <?php if ($pub_date) : ?>
                        <span class="inblue-meta-item">&#128197; <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($pub_date))); ?></span>
                    <?php endif; ?>
                    <?php if ($publisher) : ?>
                        <span class="inblue-meta-item">&#128214; <?php echo esc_html($publisher); ?></span>
                    <?php endif; ?>
                    <?php if ($isbn) : ?>
                        <span class="inblue-meta-item">&#128220; ISBN: <?php echo esc_html($isbn); ?></span>
                    <?php endif; ?>
                    <?php if ($doi) : ?>
                        <span class="inblue-meta-item">&#127760; DOI: <a href="https://doi.org/<?php echo esc_attr($doi); ?>" target="_blank" rel="noopener"><?php echo esc_html($doi); ?></a></span>
                    <?php endif; ?>
                    <?php if ($num_pages) : ?>
                        <span class="inblue-meta-item">&#128196; <?php echo esc_html($num_pages); ?> <?php _e('páginas', 'inblue-library'); ?></span>
                    <?php endif; ?>
                    <?php if ($lang_label) : ?>
                        <span class="inblue-meta-item">&#127468;&#127463; <?php echo esc_html($lang_label); ?></span>
                    <?php endif; ?>
                    <?php if ($update_date) : ?>
                        <span class="inblue-meta-item">&#128260; <?php _e('Actualizado:', 'inblue-library'); ?> <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($update_date))); ?></span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($cat_names)) : ?>
                    <div class="inblue-single-cats">
                        <?php foreach ($cats as $c) : ?>
                            <a href="<?php echo esc_url(Inblue_CPT::get_category_url($c)); ?>" class="inblue-cat-badge"><?php echo esc_html($c->name); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>


                <?php if ($final_pdf_url) : ?>
                    <div class="inblue-single-download">
                        <a href="<?php echo esc_url($final_pdf_url); ?>" class="inblue-download-btn" target="_blank" rel="noopener">
                            &#128229; <?php _e('Descargar PDF', 'inblue-library'); ?>
                        </a>
                    </div>
                <?php endif; ?>
                <?php if ($gitbook_url) : ?>
                    <div class="">
                        <a href="<?php echo esc_url($gitbook_url); ?>" style="text-decoration: none;" class="inblue-meta-item" target="_blank" rel="noopener">
                           🔗 <?php _e('ver en gitbook', 'inblue-library'); ?>
                            ↗
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($final_pdf_url && $viewer_type !== 'download') : ?>
            <hr class="inblue-divider">
            <div class="inblue-pdf-viewer">
                <h2>&#128214; <?php _e('Vista previa', 'inblue-library'); ?></h2>
                <?php if ($viewer_type === 'drive' && strpos($final_pdf_url, 'drive.google.com') !== false) :
                    preg_match('/\/d\/([^\/]+)/', $final_pdf_url, $m);
                    $drive_id = $m[1] ?? '';
                    if ($drive_id) : ?>
                        <div class="inblue-iframe-wrap">
                            <iframe src="https://drive.google.com/file/d/<?php echo esc_attr($drive_id); ?>/preview" width="100%" height="800" allow="autoplay"></iframe>
                        </div>
                    <?php endif; ?>
                <?php elseif ($viewer_type === 'iframe') : ?>
                    <div class="inblue-iframe-wrap">
                        <iframe src="<?php echo esc_url($final_pdf_url); ?>" width="100%" height="800"></iframe>
                    </div>
                <?php else : ?>
                    <div class="inblue-iframe-wrap">
                        <embed src="<?php echo esc_url($final_pdf_url); ?>" type="application/pdf" width="100%" height="800">
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($final_pdf_url && $viewer_type === 'download') : ?>
            <hr class="inblue-divider">
            <div class="inblue-pdf-viewer">
                <p><?php _e('Haz clic en el botón de descarga de arriba para ver el PDF.', 'inblue-library'); ?></p>
            </div>
        <?php endif; ?>
    </article>
<?php endwhile; ?>
<?php get_footer(); ?>