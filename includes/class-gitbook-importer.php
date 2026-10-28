<?php
if (!defined('ABSPATH')) exit;

class Inblue_Gitbook_Importer {

    const CATEGORIES = array(
        'ciencias-sociales'               => 'Ciencias Sociales',
        'ciencias-de-las-ingenierias-y-tic' => 'Ciencias de las Ingenierías y TIC',
        'ciencias-de-la-salud'            => 'Ciencias de la Salud',
        'literatura'                      => 'Literatura',
    );

    const GITBOOK_BASE = 'https://inblue-editorial.gitbook.io/inblue-editorial-servicios-editoriales/libros-publicados';

    private $results = array();

    public function import_all() {
        $this->results = array(
            'total'     => 0,
            'created'   => 0,
            'updated'   => 0,
            'skipped'   => 0,
            'errors'    => 0,
            'log'       => array(),
            'started'   => current_time('mysql'),
        );

        foreach (self::CATEGORIES as $slug => $name) {
            $this->import_category($slug, $name);
        }

        $this->results['finished'] = current_time('mysql');
        return $this->results;
    }

    public function import_category($category_slug, $category_name = '') {
        if (empty($category_name) && isset(self::CATEGORIES[$category_slug])) {
            $category_name = self::CATEGORIES[$category_slug];
        }

        $term = term_exists($category_name, 'book_category');
        if (!$term) {
            $term = wp_insert_term($category_name, 'book_category');
            if (is_wp_error($term)) {
                $this->log(sprintf('Error al crear la categoría "%s": %s', $category_name, $term->get_error_message()), 'error');
                return;
            }
        }
        $term_id = is_array($term) ? $term['term_id'] : $term;

        $category_url = self::GITBOOK_BASE . '/' . $category_slug . '.md';
        $markdown = $this->fetch_url($category_url);
        if (is_wp_error($markdown)) {
                $this->log(sprintf('Error al obtener la categoría "%s": %s', $category_name, $markdown->get_error_message()), 'error');
            return;
        }

        $book_links = $this->parse_book_links($markdown, $category_slug);
        if (empty($book_links)) {
            $this->log(sprintf('No books found in category "%s"', $category_name), 'warning');
            return;
        }

        $this->log(sprintf('Found %d books in "%s"', count($book_links), $category_name));

        $book_urls = array();
        $link_map = array();
        $skipped = 0;
        foreach ($book_links as $i => $link) {
            $clean_url = str_replace('.md', '', $link['url']);
            $existing = $this->get_book_by_gitbook_url($clean_url);
            if ($existing && has_post_thumbnail($existing)) {
                $skipped++;
                $this->log(sprintf('Omitido (ya tiene portada): %s', $link['title']));
                continue;
            }
            $book_urls[] = $clean_url . '.md';
            $link_map[$clean_url] = $link;
        }

        if (empty($book_urls)) {
            $this->log(sprintf('Los %d libros ya tienen portada en "%s"', $skipped, $category_name));
            return;
        }

        $this->log(sprintf('Obteniendo %d páginas (lote de 10, %d ya tenían portada)...', count($book_urls), $skipped));

        $pages = $this->fetch_urls_concurrent($book_urls, 10);

        foreach ($link_map as $clean_url => $link) {
            $markdown = isset($pages[$clean_url . '.md']) ? $pages[$clean_url . '.md'] : null;

            if (!$markdown || is_wp_error($markdown)) {
                $this->log(sprintf('Error al obtener "%s": %s', $link['title'], is_wp_error($markdown) ? $markdown->get_error_message() : 'respuesta vacía'), 'error');
                $this->results['errors']++;
                $this->results['total']++;
                continue;
            }

            $result = $this->import_book($clean_url, $link['title'], $link['subtitle'], $term_id, $markdown);
            if ($result === 'created') $this->results['created']++;
            elseif ($result === 'updated') $this->results['updated']++;
            elseif ($result === 'skipped') $this->results['skipped']++;
            else $this->results['errors']++;
            $this->results['total']++;
        }
    }

    private function import_book($book_url, $default_title, $subtitle, $term_id, $markdown) {
        $existing = $this->get_book_by_gitbook_url($book_url);

        $data = $this->parse_book_page($markdown, $default_title, $subtitle, $book_url);
        if (!$data) {
            $this->log(sprintf('No se pudo procesar: %s', $default_title), 'error');
            return 'error';
        }

        $data['gitbook_url'] = $book_url;

        if ($existing) {
            $this->update_book($existing, $data, $term_id);
            $this->log(sprintf('Actualizado: %s', $data['title']));
            return 'updated';
        }

        $post_id = $this->create_book($data, $term_id);
        if (!$post_id) {
            $this->log(sprintf('Error al crear: %s', $data['title']), 'error');
            return 'error';
        }
        $this->log(sprintf('Creado: %s', $data['title']));
        return 'created';
    }

    private function fetch_urls_concurrent($urls, $batch_size = 10) {
        $results = array();
        $batches = array_chunk($urls, $batch_size);

        foreach ($batches as $batch) {
            $multi = curl_multi_init();
            $curl_handles = array();

            foreach ($batch as $i => $url) {
                $ch = curl_init();
                curl_setopt_array($ch, array(
                    CURLOPT_URL            => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 10,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS      => 3,
                    CURLOPT_SSL_VERIFYPEER => false,
                ));
                curl_multi_add_handle($multi, $ch);
                $curl_handles[$i] = array('handle' => $ch, 'url' => $url);
            }

            $running = 0;
            do {
                $status = curl_multi_exec($multi, $running);
                if ($status !== CURLM_OK) break;
                curl_multi_select($multi, 1);
            } while ($running > 0);

            foreach ($curl_handles as $item) {
                $body = curl_multi_getcontent($item['handle']);
                $info = curl_getinfo($item['handle']);
                $error = curl_error($item['handle']);
                curl_multi_remove_handle($multi, $item['handle']);
                curl_close($item['handle']);

                if ($error || $info['http_code'] !== 200 || empty($body)) {
                    $results[$item['url']] = new WP_Error('fetch_failed', $error ?: sprintf('HTTP %d', $info['http_code']));
                } else {
                    $this->cache_response($item['url'], $body);
                    $results[$item['url']] = $body;
                }
            }

            curl_multi_close($multi);
        }

        return $results;
    }

    private function fetch_url($url, $bypass = false) {
        $cached = get_transient('inblue_gitbook_' . md5($url));
        if ($cached !== false && !$bypass) {
            return $cached;
        }

        $response = wp_remote_get($url, array(
            'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'timeout'     => 10,
            'redirection' => 3,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('http_error', sprintf('HTTP %d', $code));
        }

        $body = wp_remote_retrieve_body($response);
        set_transient('inblue_gitbook_' . md5($url), $body, HOUR_IN_SECONDS);

        return $body;
    }

    private function cache_response($url, $body) {
        set_transient('inblue_gitbook_' . md5($url), $body, HOUR_IN_SECONDS);
    }

    private function parse_book_links($markdown, $category_slug) {
        $links = array();
        $escaped = preg_quote($category_slug, '/');

        foreach (explode("\n", $markdown) as $line) {
            $line = trim($line);
            if (preg_match('/^- \[([^\]]+)\]\(([^)]+libros-publicados\/' . $escaped . '\/[^)]+)\)(?:\s*:\s*(.*))?$/', $line, $m)) {
                $links[] = array(
                    'title'    => trim($m[1]),
                    'url'      => trim($m[2]),
                    'subtitle' => isset($m[3]) ? trim($m[3]) : '',
                );
            }
        }

        return $links;
    }

    private function parse_book_page($markdown, $default_title, $subtitle, $book_url) {
        $data = array(
            'title'    => $default_title,
            'subtitle' => $subtitle,
            'authors'  => '',
            'isbn'     => '',
            'doi'      => '',
            'doi_pdf'  => '',
            'pub_date' => '',
            'year'     => '',
            'pdf_url'  => '',
        );

        if (preg_match('/^#\s+(.+)/m', $markdown, $m)) {
            $data['title'] = trim($m[1]);
        }

        if (preg_match('/^Autor[^:]*:\s*(.+)/mi', $markdown, $m)) {
            $data['authors'] = trim($m[1]);
        }

        if (preg_match('/^ISBN[^:]*:\s*([\d-]+[X\d]?)/mi', $markdown, $m)) {
            $data['isbn'] = trim($m[1]);
        }

        // Un libro puede declarar dos DOI en GitBook: el de la página del
        // libro y el de la página que aloja el PDF. Se extraen con el helper
        // compartido (maneja enlaces markdown y entidades HTML).
        $parsed_dois = Inblue_Library_Admin::extract_dois_from_markdown($markdown);
        $data['doi']     = $parsed_dois['page'];
        $data['doi_pdf'] = $parsed_dois['pdf'];

        if (preg_match('/^Fecha[^:]*:\s*(.+)/mi', $markdown, $m)) {
            $raw_date = trim($m[1]);
            $data['pub_date'] = $this->normalize_date($raw_date);
            $data['year'] = substr($raw_date, 0, 4);
        }

        if (preg_match('/{%\s*embed\s*url="<([^">]+)>\s*"?\s*%}/', $markdown, $m)) {
            $data['pdf_url'] = trim($m[1]);
        } elseif (preg_match('/{%\s*embed\s*url="([^"]+)"\s*%}/', $markdown, $m)) {
            $data['pdf_url'] = trim($m[1]);
        } elseif (preg_match('/https:\/\/drive\.google\.com\/file\/d\/([^\/\s"]+)/', $markdown, $m)) {
            $data['pdf_url'] = 'https://drive.google.com/file/d/' . $m[1] . '/view';
        }

        $data['pdf_url'] = str_replace(array('<', '>'), '', $data['pdf_url']);

        return $data;
    }

    private function normalize_date($raw) {
        $raw = trim($raw);
        if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $raw, $m)) {
            // Formato día/mes/año
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        if (preg_match('/^(\d{4})[\/\-](\d{1,2})$/', $raw, $m)) {
            return sprintf('%04d-%02d-01', $m[1], $m[2]);
        }
        if (preg_match('/^\d{4}$/', $raw)) return $raw . '-01-01';
        return '';
    }

    private function get_book_by_gitbook_url($url) {
        $posts = get_posts(array(
            'post_type'      => 'book',
            'meta_key'       => '_book_gitbook_url',
            'meta_value'     => $url,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ));
        return !empty($posts) ? $posts[0] : false;
    }

    private function create_book($data, $term_id) {
        $post_id = wp_insert_post(array(
            'post_title'   => $data['title'],
            'post_type'    => 'book',
            'post_status'  => 'publish',
            'post_content' => $data['subtitle'],
        ));

        if (is_wp_error($post_id)) return false;

        $this->save_book_meta($post_id, $data);
        wp_set_post_terms($post_id, array((int)$term_id), 'book_category');

        return $post_id;
    }

    private function update_book($post_id, $data, $term_id) {
        wp_update_post(array(
            'ID'         => $post_id,
            'post_title' => $data['title'],
        ));

        if (get_post_meta($post_id, '_book_gitbook_url', true)) {
            $this->save_book_meta($post_id, $data);
            $current = wp_get_post_terms($post_id, 'book_category', array('fields' => 'ids'));
            if (!in_array((int)$term_id, $current)) {
                wp_set_post_terms($post_id, array_merge($current, array((int)$term_id)), 'book_category', true);
            }
        }

        return $post_id;
    }

    private function save_book_meta($post_id, $data) {
        $meta = array(
            '_book_authors'     => $data['authors'],
            '_book_isbn'        => $data['isbn'],
            '_book_doi'         => $data['doi'],
            '_book_doi_pdf'     => $this->resolve_doi_pdf($data),
            '_book_pub_date'    => $data['pub_date'],
            '_book_year'        => $data['year'],
            '_book_pdf_source'  => !empty($data['pdf_url']) ? 'url' : '',
            '_book_pdf_url'     => $data['pdf_url'],
            '_book_gitbook_url' => $data['gitbook_url'] ?? '',
        );

        foreach ($meta as $key => $value) {
            if (!empty($value)) {
                update_post_meta($post_id, $key, sanitize_text_field($value));
            }
        }
    }

    /**
     * Segundo DOI según el origen configurado en Ajustes → DOI.
     *
     * Devuelve cadena vacía para no tocar el valor existente (el bucle de
     * guardado solo escribe los campos con contenido).
     */
    private function resolve_doi_pdf($data) {
        // Origen "PDF de GitBook": la extracción descarga el archivo, así que
        // en la importación no se hace; se aplica con "Rellenar DOI PDF".
        if ('pdf' === Inblue_Library_Admin::doi_pdf_source()) {
            return '';
        }

        // Origen "Página de GitBook": es el DOI que publica GitBook.
        return !empty($data['doi']) ? $data['doi'] : '';
    }

    public function check_availability() {
        $result = array(
            'total_remote' => 0,
            'new'          => array(),
            'existing'     => array(),
            'errors'       => 0,
            'log'          => array(),
            'checked'      => current_time('mysql'),
        );

        foreach (self::CATEGORIES as $slug => $name) {
            $category_url = self::GITBOOK_BASE . '/' . $slug . '.md';
            $markdown = $this->fetch_url($category_url, true);
            if (is_wp_error($markdown)) {
                $result['errors']++;
                $result['log'][] = array(
                    'message' => sprintf('Error al obtener la categoría "%s": %s', $name, $markdown->get_error_message()),
                    'type'    => 'error',
                    'time'    => current_time('mysql'),
                );
                continue;
            }

            $book_links = $this->parse_book_links($markdown, $slug);
            foreach ($book_links as $link) {
                $clean_url = str_replace('.md', '', $link['url']);
                $result['total_remote']++;

                $item = array(
                    'title'         => $link['title'],
                    'subtitle'      => $link['subtitle'],
                    'url'           => $clean_url,
                    'category_slug' => $slug,
                    'category_name' => $name,
                );

                $existing = $this->get_book_by_gitbook_url($clean_url);
                if ($existing) {
                    $item['post_id'] = (int) $existing;
                    $result['existing'][] = $item;
                } else {
                    $result['new'][] = $item;
                }
            }
        }

        $result['log'][] = array(
            'message' => sprintf(
                'Comprobación completada: %d libros en GitBook, %d nuevos disponibles, %d ya importados.',
                $result['total_remote'],
                count($result['new']),
                count($result['existing'])
            ),
            'type'    => 'info',
            'time'    => current_time('mysql'),
        );

        return $result;
    }

    public function import_new_books($items) {
        $this->results = array(
            'total'     => 0,
            'created'   => 0,
            'updated'   => 0,
            'skipped'   => 0,
            'errors'    => 0,
            'log'       => array(),
            'started'   => current_time('mysql'),
        );

        $by_category = array();
        foreach ($items as $item) {
            $slug = isset($item['category_slug']) ? $item['category_slug'] : 'otros';
            $by_category[$slug][] = $item;
        }

        foreach ($by_category as $slug => $cat_items) {
            $name = isset(self::CATEGORIES[$slug]) ? self::CATEGORIES[$slug] : ucfirst($slug);

            $term = term_exists($name, 'book_category');
            if (!$term) {
                $term = wp_insert_term($name, 'book_category');
                if (is_wp_error($term)) {
                    $this->log(sprintf('Error al crear la categoría "%s": %s', $name, $term->get_error_message()), 'error');
                    continue;
                }
            }
            $term_id = is_array($term) ? $term['term_id'] : $term;

            $urls = array();
            foreach ($cat_items as $item) {
                $urls[] = $item['url'] . '.md';
            }

            $pages = $this->fetch_urls_concurrent($urls, 10);

            foreach ($cat_items as $item) {
                $book_url = $item['url'] . '.md';
                $markdown = isset($pages[$book_url]) ? $pages[$book_url] : null;

                if (!$markdown || is_wp_error($markdown)) {
                    $this->results['errors']++;
                    $this->results['total']++;
                    $this->log(sprintf('Error al obtener "%s": %s', $item['title'], is_wp_error($markdown) ? $markdown->get_error_message() : 'respuesta vacía'), 'error');
                    continue;
                }

                $result = $this->import_book($item['url'], $item['title'], $item['subtitle'], $term_id, $markdown);
                if ($result === 'created') {
                    $this->results['created']++;
                    $this->fetch_book_cover($item['url'], $item['title']);
                } elseif ($result === 'updated') {
                    $this->results['updated']++;
                } elseif ($result === 'skipped') {
                    $this->results['skipped']++;
                } else {
                    $this->results['errors']++;
                }
                $this->results['total']++;
            }
        }

        $this->results['finished'] = current_time('mysql');
        return $this->results;
    }

    private function fetch_book_cover($book_url, $title) {
        if (!class_exists('Inblue_Library_Admin')) {
            return;
        }

        $post_id = $this->get_book_by_gitbook_url($book_url);
        if (!$post_id || has_post_thumbnail($post_id)) {
            return;
        }

        $result = Inblue_Library_Admin::obtener_portada($post_id);

        if (is_wp_error($result)) {
            $this->log(sprintf('Portada no obtenida para "%s": %s', $title, $result->get_error_message()), 'warning');
        } else {
            $this->log(sprintf('Portada obtenida: %s', $title));
        }
    }

    public function extract_all_covers() {
        $this->results = array(
            'total'   => 0,
            'success' => 0,
            'skipped' => 0,
            'errors'  => 0,
            'log'     => array(),
            'started' => current_time('mysql'),
        );

        if (!class_exists('Inblue_Library_Admin')) {
            $this->results['log'][] = 'Admin class not loaded';
            return $this->results;
        }

        $books = get_posts(array(
            'post_type'      => 'book',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));

        foreach ($books as $post_id) {
            $this->results['total']++;
            $title = get_the_title($post_id);

            $corrupt = false;
            if (has_post_thumbnail($post_id)) {
                $corrupt = Inblue_Library_Admin::is_thumbnail_blank($post_id);
                if (!$corrupt) {
                    $this->results['skipped']++;
                    $this->log(sprintf('Omitido (ya tiene portada): %s', $title));
                    continue;
                }
                $this->log(sprintf('Regenerando portada en blanco: %s', $title), 'warning');
                Inblue_Library_Admin::delete_thumbnail($post_id);
            }

            $pdf_url = get_post_meta($post_id, '_book_pdf_url', true);
            if (empty($pdf_url)) {
                $this->results['skipped']++;
                $this->log(sprintf('Omitido (sin URL de PDF): %s', $title));
                continue;
            }

            $pdf_source = get_post_meta($post_id, '_book_pdf_source', true) ?: 'url';
            $result = Inblue_Library_Admin::obtener_portada($post_id, $pdf_source, $pdf_url, 0);

            if (is_wp_error($result)) {
                $this->results['errors']++;
                $this->log(sprintf('Error: %s — %s', $title, $result->get_error_message()), 'error');
            } else {
                $this->results['success']++;
                $this->log(sprintf('Portada extraída: %s', $title));
            }
        }

        $this->results['finished'] = current_time('mysql');
        return $this->results;
    }

    private function log($message, $type = 'info') {
        $this->results['log'][] = array(
            'message' => $message,
            'type'    => $type,
            'time'    => current_time('mysql'),
        );
    }

    public function clear_cache() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_inblue_gitbook_%' OR option_name LIKE '_transient_timeout_inblue_gitbook_%'");
    }
}
