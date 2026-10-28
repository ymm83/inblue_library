<?php
if (!defined('ABSPATH')) exit;

class Inblue_Import_Export {

    private static function get_all_books() {
        $books = get_posts(array(
            'post_type'      => 'book',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ));

        usort($books, function($a, $b) {
            $da = get_post_meta($a->ID, '_book_pub_date', true);
            $db = get_post_meta($b->ID, '_book_pub_date', true);
            if ($da && $db) return strcmp($db, $da);
            if ($da) return -1;
            if ($db) return 1;
            return strcmp($b->post_date, $a->post_date);
        });

        return $books;
    }

    private static function csv_headers($include_covers) {
        $headers = array(
            'ID', 'Title', 'Subtitle', 'Description', 'Authors', 'Publication Date', 'Update Date',
            'Year', 'ISBN', 'DOI', 'DOI PDF', 'Publisher', 'Language', 'Pages',
            'PDF Source', 'PDF URL', 'PDF File ID',
            'Categories', 'Tags', 'Featured', 'GitBook URL', 'Permalink',
        );
        if ($include_covers) {
            $headers[] = 'Cover URL';
        }
        return $headers;
    }

    /**
     * Devuelve solo los libros cuya fecha de actualización (meta o modificación del post)
     * es posterior o igual a $since (formato 'Y-m-d H:i:s' o timestamp).
     */
    private static function get_books_updated_since($since) {
        $books = self::get_all_books();
        if (empty($since)) {
            return $books;
        }
        if (is_numeric($since)) {
            $since_str = date('Y-m-d H:i:s', (int) $since);
        } else {
            $since_str = $since;
        }

        $filtered = array();
        foreach ($books as $book) {
            $meta_date  = get_post_meta($book->ID, '_book_update_date', true);
            $meta_date2 = get_post_meta($book->ID, '_book_pub_date', true);
            $modified   = $book->post_modified; // 'Y-m-d H:i:s'

            $latest = $modified;
            foreach (array($meta_date, $meta_date2) as $d) {
                if (!empty($d) && strcmp($d, $latest) > 0) {
                    $latest = $d;
                }
            }
            if (strcmp($latest, $since_str) >= 0) {
                $filtered[] = $book;
            }
        }
        return $filtered;
    }

    /**
     * Exporta un backup. Si $since no está vacío, solo incluye libros
     * actualizados desde esa fecha (sincronización incremental).
     */
    public static function export_zip($include_data = true, $include_covers = true, $since = null) {
        if (!$include_data && !$include_covers) {
            $include_data = true;
        }

        $books = $since ? self::get_books_updated_since($since) : self::get_all_books();

        $upload_dir = wp_upload_dir();
        $backup_dir = trailingslashit($upload_dir['basedir']) . 'inblue-backup-' . wp_generate_password(10, false);
        wp_mkdir_p($backup_dir);

        $csv_path = $backup_dir . '/books.csv';
        $zip_path = $backup_dir . '/backup.zip';

        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return;
        }

        $cover_refs = array();
        if ($include_covers) {
            foreach ($books as $book) {
                $id = $book->ID;
                $cover_id = get_post_thumbnail_id($id);
                if (!$cover_id) continue;
                $cover_file = get_attached_file($cover_id);
                if (!$cover_file || !file_exists($cover_file)) continue;

                $ext = pathinfo($cover_file, PATHINFO_EXTENSION);
                $ref = 'covers/' . $id . '.' . $ext;
                if ($zip->addFile($cover_file, $ref)) {
                    $cover_refs[$id] = $ref;
                }
            }
        }

        if ($include_data) {
            $csv_handle = fopen($csv_path, 'w');
            fprintf($csv_handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($csv_handle, self::csv_headers($include_covers));

            foreach ($books as $book) {
                $id = $book->ID;
                $cats = wp_get_post_terms($id, 'book_category', array('fields' => 'names'));
                $tags = wp_get_post_terms($id, 'book_tag', array('fields' => 'names'));
                $featured = get_post_meta($id, '_book_featured', true) === '1' ? 'yes' : 'no';

                $row = array(
                    $id,
                    get_the_title($id),
                    get_post_meta($id, '_book_subtitle', true),
                    $book->post_content,
                    get_post_meta($id, '_book_authors', true),
                    get_post_meta($id, '_book_pub_date', true),
                    get_post_meta($id, '_book_update_date', true),
                    get_post_meta($id, '_book_year', true),
                    get_post_meta($id, '_book_isbn', true),
                    get_post_meta($id, '_book_doi', true),
                    get_post_meta($id, '_book_doi_pdf', true),
                    get_post_meta($id, '_book_publisher', true),
                    get_post_meta($id, '_book_language', true),
                    get_post_meta($id, '_book_pages', true),
                    get_post_meta($id, '_book_pdf_source', true),
                    get_post_meta($id, '_book_pdf_url', true),
                    get_post_meta($id, '_book_pdf_file_id', true),
                    implode(';', $cats),
                    implode(';', $tags),
                    $featured,
                    get_post_meta($id, '_book_gitbook_url', true),
                    get_permalink($id),
                );
                if ($include_covers) {
                    $row[] = isset($cover_refs[$id]) ? $cover_refs[$id] : '';
                }
                fputcsv($csv_handle, $row);
            }
            fclose($csv_handle);

            $zip->addFile($csv_path, 'books.csv');
        }

        $zip->close();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $suffix = '';
        if ($include_data && !$include_covers) $suffix = '-datos';
        if (!$include_data && $include_covers) $suffix = '-portadas';

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename=inblue-library-backup-' . date('Y-m-d') . $suffix . '.zip');
        header('Content-Length: ' . filesize($zip_path));
        readfile($zip_path);

        array_map('unlink', glob($backup_dir . '/covers/*'));
        @unlink($csv_path);
        @unlink($zip_path);
        @rmdir($backup_dir . '/covers');
        @rmdir($backup_dir);
        exit;
    }

    public static function import_csv($file_path, $preview = false, $options = array()) {
        $defaults = array(
            'apply_data'   => true,
            'apply_covers' => true,
            'update_only'  => false,
        );
        $options = wp_parse_args($options, $defaults);

        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', __('Archivo no encontrado.', 'inblue-library'));
        }

        $zip_base = null;
        $is_zip = strtolower(pathinfo($file_path, PATHINFO_EXTENSION)) === 'zip';
        if ($is_zip) {
            $zip = new ZipArchive();
            if ($zip->open($file_path) !== true) {
                return new WP_Error('invalid_zip', __('Archivo ZIP no válido.', 'inblue-library'));
            }
            $upload_dir = wp_upload_dir();
            $zip_base = trailingslashit($upload_dir['basedir']) . 'inblue-restore-' . wp_generate_password(10, false);
            wp_mkdir_p($zip_base);
            $zip->extractTo($zip_base);
            $zip->close();

            $file_path = $zip_base . '/books.csv';
            if (!file_exists($file_path)) {
                // ZIP solo-portadas: sin CSV, asignar covers/{id}.ext por ID de post
                if (is_dir($zip_base . '/covers') && $options['apply_covers']) {
                    $results = array(
                        'total'     => 0,
                        'created'   => 0,
                        'updated'   => 0,
                        'existing'  => 0,
                        'skipped'   => 0,
                        'errors'    => 0,
                        'preview'   => (bool) $preview,
                        'new_books' => array(),
                        'log'       => array(),
                    );
                    foreach (glob($zip_base . '/covers/*') as $cover_file) {
                        $results['total']++;
                        $name = basename($cover_file);
                        $post_id = (int) pathinfo($name, PATHINFO_FILENAME);
                        if ($post_id <= 0 || !get_post($post_id)) {
                            $results['errors']++;
                            $results['log'][] = sprintf('Portada sin libro correspondiente (ID %d): %s', $post_id, $name);
                            continue;
                        }
                        if ($preview) {
                            $results['updated']++;
                            $results['log'][] = sprintf('Se asignaría portada al libro ID %d: %s', $post_id, get_the_title($post_id));
                        } else {
                            $attach = self::sideload_cover_file($cover_file, $post_id);
                            if ($attach) {
                                $results['updated']++;
                                $results['log'][] = sprintf('Portada asignada a: %s', get_the_title($post_id));
                            } else {
                                $results['errors']++;
                                $results['log'][] = sprintf('Error asignando portada al ID %d', $post_id);
                            }
                        }
                    }
                    self::remove_dir($zip_base);
                    return $results;
                }
                self::remove_dir($zip_base);
                return new WP_Error('missing_csv', __('El ZIP no contiene books.csv.', 'inblue-library'));
            }
        }

        $handle = fopen($file_path, 'r');
        if (!$handle) {
            if ($zip_base) self::remove_dir($zip_base);
            return new WP_Error('cannot_open', __('No se puede abrir el archivo.', 'inblue-library'));
        }

        $bom = fread($handle, 3);
        if ($bom !== chr(0xEF) . chr(0xBB) . chr(0xBF)) {
            rewind($handle);
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            if ($zip_base) self::remove_dir($zip_base);
            return new WP_Error('invalid_csv', __('Archivo CSV no válido.', 'inblue-library'));
        }

        $header_map = array();
        foreach ($headers as $i => $h) {
            $header_map[trim(strtolower($h))] = $i;
        }

        $results = array(
            'total'     => 0,
            'created'   => 0,
            'updated'   => 0,
            'existing'  => 0,
            'skipped'   => 0,
            'errors'    => 0,
            'preview'   => (bool) $preview,
            'new_books' => array(),
            'log'       => array(),
        );

        while (($row = fgetcsv($handle)) !== false) {
            $results['total']++;

            $data = self::map_row($row, $header_map);
            if (!$data || empty($data['title'])) {
                $results['errors']++;
                $results['log'][] = sprintf('Row %d: missing title', $results['total']);
                continue;
            }

            $existing = self::find_existing_book($data);
            if ($existing) {
                if ($preview) {
                    $results['existing']++;
                    $results['log'][] = sprintf('Existente (se actualizaría): %s', $data['title']);
                } else {
                    self::update_book($existing, $data, $zip_base, $options);
                    $results['updated']++;
                    $results['log'][] = sprintf('Updated: %s', $data['title']);
                }
            } else {
                if ($options['update_only']) {
                    $results['skipped']++;
                    $results['log'][] = sprintf('Omitido (no existe en producción): %s', $data['title']);
                    continue;
                }
                if ($preview) {
                    $results['new_books'][] = array(
                        'title'    => $data['title'],
                        'authors'  => $data['authors'],
                        'isbn'     => $data['isbn'],
                        'category' => $data['categories'],
                    );
                    $results['created']++;
                    $results['log'][] = sprintf('Nuevo (se crearían): %s', $data['title']);
                } else {
                    $post_id = self::create_book($data, $zip_base, $options);
                    if ($post_id) {
                        $results['created']++;
                        $results['log'][] = sprintf('Created: %s', $data['title']);
                    } else {
                        $results['errors']++;
                        $results['log'][] = sprintf('Error creating: %s', $data['title']);
                    }
                }
            }
        }

        fclose($handle);
        if ($zip_base) {
            self::remove_dir($zip_base);
        }
        return $results;
    }

    private static function remove_dir($dir) {
        if (!is_dir($dir)) return;
        $items = glob(rtrim($dir, '/\\') . '/*');
        if ($items) {
            foreach ($items as $item) {
                if (is_dir($item)) {
                    self::remove_dir($item);
                } else {
                    @unlink($item);
                }
            }
        }
        @rmdir($dir);
    }

    private static function map_row($row, $header_map) {
        $get = function($key) use ($row, $header_map) {
            $lk = trim(strtolower($key));
            return isset($header_map[$lk]) && isset($row[$header_map[$lk]]) ? trim($row[$header_map[$lk]]) : '';
        };

        $data = array(
            'id'           => $get('ID'),
            'title'        => $get('Title'),
            'subtitle'     => $get('Subtitle'),
            'description'  => $get('Description'),
            'authors'      => $get('Authors'),
            'pub_date'     => $get('Publication Date'),
            'update_date'  => $get('Update Date'),
            'year'         => $get('Year'),
            'isbn'         => $get('ISBN'),
            'doi'          => $get('DOI'),
            'doi_pdf'      => $get('DOI PDF'),
            'publisher'    => $get('Publisher'),
            'language'     => $get('Language'),
            'pages'        => $get('Pages'),
            'pdf_source'   => $get('PDF Source'),
            'pdf_url'      => $get('PDF URL'),
            'pdf_file_id'  => $get('PDF File ID'),
            'categories'   => $get('Categories'),
            'tags'         => $get('Tags'),
            'featured'     => $get('Featured'),
            'cover_url'    => $get('Cover URL'),
            'gitbook_url'  => $get('GitBook URL'),
        );

        if (empty($data['year']) && !empty($data['pub_date'])) {
            $data['year'] = substr($data['pub_date'], 0, 4);
        }

        return $data;
    }

    private static function find_existing_book($data) {
        if (!empty($data['gitbook_url'])) {
            $posts = get_posts(array(
                'post_type' => 'book', 'posts_per_page' => 1, 'fields' => 'ids',
                'meta_key' => '_book_gitbook_url', 'meta_value' => $data['gitbook_url'],
            ));
            if (!empty($posts)) return $posts[0];
        }

        if (!empty($data['doi'])) {
            $posts = get_posts(array(
                'post_type' => 'book', 'posts_per_page' => 1, 'fields' => 'ids',
                'meta_key' => '_book_doi', 'meta_value' => $data['doi'],
            ));
            if (!empty($posts)) return $posts[0];
        }

        if (!empty($data['isbn'])) {
            $posts = get_posts(array(
                'post_type' => 'book', 'posts_per_page' => 1, 'fields' => 'ids',
                'meta_key' => '_book_isbn', 'meta_value' => $data['isbn'],
            ));
            if (!empty($posts)) return $posts[0];
        }

        // Último recurso: mismo ID de post (local y producción suelen compartirlo
        // si el sitio se creó a partir de una copia) y después el título exacto.
        if (!empty($data['id'])) {
            $post_id = (int) $data['id'];
            if ($post_id > 0 && 'book' === get_post_type($post_id)) {
                if (empty($data['title']) || get_the_title($post_id) === $data['title']) {
                    return $post_id;
                }
            }
        }

        if (!empty($data['title'])) {
            $posts = get_posts(array(
                'post_type'      => 'book',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'title'          => $data['title'],
            ));
            if (!empty($posts)) return $posts[0];
        }

        return false;
    }

    private static function create_book($data, $zip_base = null, $options = array()) {
        $options = wp_parse_args($options, array('apply_data' => true, 'apply_covers' => true));
        $content = !empty($data['description']) ? $data['description'] : $data['subtitle'];
        $post_id = wp_insert_post(array(
            'post_title'   => $data['title'],
            'post_content' => $options['apply_data'] ? $content : '',
            'post_type'    => 'book',
            'post_status'  => 'publish',
        ), true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        if ($options['apply_data']) {
            self::save_meta($post_id, $data);
            self::save_terms($post_id, $data);
        }
        if ($options['apply_covers']) {
            self::save_cover($post_id, $data['cover_url'], $zip_base);
        }

        return $post_id;
    }

    private static function update_book($post_id, $data, $zip_base = null, $options = array()) {
        $options = wp_parse_args($options, array('apply_data' => true, 'apply_covers' => true));
        if ($options['apply_data']) {
            $post_data = array(
                'ID'         => $post_id,
                'post_title' => $data['title'],
            );
            if (!empty($data['description'])) {
                $post_data['post_content'] = $data['description'];
            }
            wp_update_post($post_data);

            self::save_meta($post_id, $data);
            self::save_terms($post_id, $data);
        }
        if ($options['apply_covers']) {
            self::save_cover($post_id, $data['cover_url'], $zip_base);
        }
    }

    private static function save_meta($post_id, $data) {
        $fields = array(
            '_book_authors'     => $data['authors'],
            '_book_pub_date'    => $data['pub_date'],
            '_book_update_date' => $data['update_date'],
            '_book_year'        => $data['year'],
            '_book_isbn'        => $data['isbn'],
            '_book_doi'         => $data['doi'],
            '_book_doi_pdf'     => $data['doi_pdf'] ?? '',
            '_book_publisher'   => $data['publisher'],
            '_book_language'    => $data['language'],
            '_book_pages'       => $data['pages'],
            '_book_pdf_source'  => $data['pdf_source'],
            '_book_pdf_url'     => $data['pdf_url'],
            '_book_pdf_file_id' => $data['pdf_file_id'],
            '_book_gitbook_url' => $data['gitbook_url'],
        );

        foreach ($fields as $key => $value) {
            if (!empty($value)) {
                update_post_meta($post_id, $key, sanitize_text_field($value));
            }
        }

        if (isset($data['featured']) && in_array(strtolower($data['featured']), array('1', 'yes', 'true', 'si'))) {
            update_post_meta($post_id, '_book_featured', '1');
        } else {
            delete_post_meta($post_id, '_book_featured');
        }
    }

    private static function sideload_cover_file($file, $post_id) {
        if (!file_exists($file)) return false;

        $upload = wp_upload_bits(basename($file), null, file_get_contents($file));
        if (!empty($upload['error'])) return false;

        $filetype = wp_check_filetype(basename($upload['file']), null);
        $attach_id = wp_insert_attachment(array(
            'post_mime_type' => $filetype['type'],
            'post_title'     => preg_replace('/\.[^.]+$/', '', basename($upload['file'])),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'guid'           => $upload['url'],
        ), $upload['file'], $post_id);

        if (is_wp_error($attach_id) || !$attach_id) return false;

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);
        set_post_thumbnail($post_id, $attach_id);
        return $attach_id;
    }

    private static function save_cover($post_id, $url, $zip_base = null) {
        if (empty($url)) return;

        if ($zip_base && !preg_match('#^https?://#i', $url)) {
            $real_base = realpath($zip_base);
            $local = realpath($zip_base . '/' . $url);
            if ($local === false || $real_base === false || strpos($local, $real_base . DIRECTORY_SEPARATOR) !== 0) {
                return;
            }
            if (!file_exists($local)) return;

            $upload = wp_upload_bits(basename($local), null, file_get_contents($local));
            if (!empty($upload['error'])) return;

            $filetype = wp_check_filetype(basename($upload['file']), null);
            $attach_id = wp_insert_attachment(array(
                'post_mime_type' => $filetype['type'],
                'post_title'     => preg_replace('/\.[^.]+$/', '', basename($upload['file'])),
                'post_content'   => '',
                'post_status'    => 'inherit',
                'guid'           => $upload['url'],
            ), $upload['file'], $post_id);

            if (!is_wp_error($attach_id) && $attach_id) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
                $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
                wp_update_attachment_metadata($attach_id, $attach_data);
                set_post_thumbnail($post_id, $attach_id);
            }
            return;
        }

        if (!preg_match('#^https?://#i', $url)) return;

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attach_id = media_sideload_image($url, $post_id, null, 'id');
        if (!is_wp_error($attach_id) && $attach_id) {
            set_post_thumbnail($post_id, $attach_id);
        }
    }

    public static function delete_all_books() {
        $books = get_posts(array(
            'post_type'      => 'book',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));

        foreach ($books as $book_id) {
            wp_delete_post($book_id, true);
        }

        foreach (array('book_category', 'book_tag') as $tax) {
            $terms = get_terms(array('taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'ids'));
            if (is_wp_error($terms)) continue;
            foreach ($terms as $term_id) {
                $term = get_term($term_id, $tax);
                if ($term && $term->count == 0) {
                    wp_delete_term($term_id, $tax);
                }
            }
        }
    }

    private static function save_terms($post_id, $data) {
        if (!empty($data['categories'])) {
            $slugs = array_map('trim', explode(';', $data['categories']));
            $term_ids = array();
            foreach ($slugs as $name) {
                $term = term_exists($name, 'book_category');
                if (!$term) {
                    $term = wp_insert_term($name, 'book_category');
                }
                if (!is_wp_error($term)) {
                    $term_ids[] = is_array($term) ? $term['term_id'] : $term;
                }
            }
            if (!empty($term_ids)) {
                wp_set_post_terms($post_id, $term_ids, 'book_category');
            }
        }

        if (!empty($data['tags'])) {
            $tag_names = array_map('trim', explode(';', $data['tags']));
            wp_set_post_terms($post_id, $tag_names, 'book_tag');
        }
    }
}
