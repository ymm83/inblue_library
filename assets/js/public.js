(function($) {
    'use strict';

    var $searchInput   = $('#inblue-search-input');
    var $filterCat     = $('#inblue-filter-category');
    var $filterYear    = $('#inblue-filter-year');
    var $results       = $('#inblue-results');
    var $pagination    = $('#inblue-pagination');

    var activeRequest = null;
    var searchTimer   = null;
    var resizeTimer   = null;
    var currentQuery  = '';
    var currentPage   = Math.max(1, parseInt(inblueLib.startPage, 10) || 1);
    var currentPerPage = parseInt(inblueLib.perPage, 10) || 12;
    var basePerPage    = currentPerPage;

    var totalBooks = parseInt($results.attr('data-total') || '0', 10) || 0;

    // Estado inicial de la biblioteca (URL actual) como punto de retorno.
    // Solo si esta página ES la biblioteca (existe el grid de resultados),
    // para no sobrescribir el estado cuando el script carga en un libro.
    if ($results.length) {
        storeReturnUrl(window.location.pathname + window.location.search);
    }

    function buildQuery() {
        var parts = {
            query:    $searchInput.val() ? $searchInput.val().trim() : '',
            category: $filterCat.length ? $filterCat.val() : '0',
            year:     $filterYear.length ? $filterYear.val() : '0',
        };
        return parts;
    }

    function updateCount(total) {
        var $desc = $('#inblue-archive-desc');
        if (!$desc.length) return;
        var i18n = (window.inblueLib && inblueLib.i18n) ? inblueLib.i18n : null;
        var template = total === 1
            ? (i18n ? i18n.oneBook : '%d libro disponible')
            : (i18n ? i18n.manyBooks : '%d libros disponibles');
        if (!total && i18n) {
            template = i18n.noBooks;
        }
        $desc.text(template.replace('%d', Number(total).toLocaleString('es')));
    }

    function noResultsText() {
        var i18n = (window.inblueLib && inblueLib.i18n) ? inblueLib.i18n : null;
        return (i18n && i18n.noBooks) ? i18n.noBooks : 'No se encontraron libros.';
    }

    function updateUrl() {
        if (!window.history || !window.history.replaceState) return;
        var params = new URLSearchParams(window.location.search);

        var search = $searchInput.length ? $searchInput.val().trim() : '';
        if (search) {
            params.set('q', search);
        } else {
            params.delete('q');
        }
        // "s" es la query var de búsqueda de WordPress: nunca debe enviarse
        params.delete('s');

        var catId = $filterCat.length ? $filterCat.val() : '0';
        if (catId && catId !== '0') {
            var $opt = $filterCat.find('option[value="' + catId + '"]');
            var slug = $opt.attr('data-slug');
            if (slug) {
                params.set('category', slug);
            }
        } else {
            params.delete('category');
        }

        var year = $filterYear.length ? $filterYear.val() : '0';
        if (year && year !== '0') {
            params.set('year', year);
        } else {
            params.delete('year');
        }

        params.delete('paged');

        var qs = params.toString();

        // Paginación en formato pretty-permalink (/biblioteca/page/N/)
        // para evitar el redirect 301 de WP desde ?paged=N
        var base = window.location.pathname.replace(/\/page\/\d+\/?$/, '');
        var newUrl;
        if (currentPage > 1) {
            newUrl = base.replace(/\/$/, '') + '/page/' + currentPage + '/' + (qs ? '?' + qs : '');
        } else {
            newUrl = base + (qs ? '?' + qs : '');
        }
        history.replaceState(null, '', newUrl);
        storeReturnUrl(newUrl);
    }

    // Guarda el estado actual de la biblioteca (paginación + filtros) para
    // que el botón "Regresar" de la página del libro vuelva a este punto exacto.
    function storeReturnUrl(url) {
        if (!window.sessionStorage) return;
        try {
            sessionStorage.setItem('inblueLibraryReturnUrl', url || (window.location.pathname + window.location.search));
        } catch (err) {}
    }

    function esc(str) {
        return String(str == null ? '' : str);
    }

    function renderGrid(books) {
        var html = '';
        if (books && books.length) {
            $.each(books, function(i, book) {
                html += '<div class="inblue-grid-item">';
                html += '<a href="' + book.permalink + '">';
                if (book.thumbnail) {
                    html += '<img src="' + book.thumbnail + '" alt="" loading="lazy">';
                } else {
                    html += '<div class="inblue-no-cover">Sin portada</div>';
                }
                html += '<div class="inblue-grid-info">';
                html += '<h3>' + esc(book.title) + '</h3>';
                html += '</div>';
                html += '<div class="inblue-grid-footer">';
                if (book.is_new) {
                    html += '<span class="inblue-grid-year is-new">Nuevo</span>';
                } else if (book.is_updated) {
                    html += '<span class="inblue-grid-year is-updated">Actualizado</span>';
                } else if (book.year) {
                    html += '<span class="inblue-grid-year">' + esc(book.year) + '</span>';
                }
                html += '<span class="inblue-grid-views" title="Visitas">' + Number(book.views || 0).toLocaleString('es') + '</span>';
                html += '</div>';
                html += '</a>';
                html += '</div>';
            });
        } else {
            html = '<p class="inblue-no-results">' + esc(noResultsText()) + '</p>';
        }
        return html;
    }

    function renderPagination(data) {
        if (!data.max_pages || data.max_pages <= 1) {
            return '';
        }
        var html = '<ul class="page-numbers">';
        if (data.current > 1) {
            html += '<li><button type="button" class="page-numbers" data-page="' + (data.current - 1) + '">&laquo;</button></li>';
        }
        for (var i = 1; i <= data.max_pages; i++) {
            var cls = (i === data.current) ? 'page-numbers active' : 'page-numbers';
            html += '<li><button type="button" class="' + cls + '" data-page="' + i + '">' + i + '</button></li>';
        }
        if (data.current < data.max_pages) {
            html += '<li><button type="button" class="page-numbers" data-page="' + (data.current + 1) + '">&raquo;</button></li>';
        }
        html += '</ul>';
        return html;
    }

    function getColumns() {
        // Must match the CSS in public.css:
        // desktop: repeat(auto-fill, minmax(200px, 1fr)) with gap 22px
        // mobile (max-width: 768px): repeat(2, 1fr) with gap 14px
        var container = $results[0];
        if (!container) {
            return 1;
        }
        var width = container.clientWidth;
        if (!width) {
            return 1;
        }
        if (window.matchMedia('(max-width: 768px)').matches) {
            return 2;
        }
        var minCol = 200;
        var gap    = 22;
        return Math.max(1, Math.floor((width + gap) / (minCol + gap)));
    }

    function desiredPerPage(cols) {
        // Always computed from the configured base per_page, so it is stable:
        // it can grow AND shrink back as the column count changes instead of
        // getting stuck on a non-multiple value.
        var rows = Math.max(1, Math.ceil(basePerPage / cols));
        return rows * cols;
    }

    function fillGrid(force) {
        if (totalBooks <= 0) {
            return;
        }
        var cols = getColumns();
        var desired = desiredPerPage(cols);
        var effective = Math.min(desired, totalBooks);
        if (effective !== currentPerPage) {
            currentPerPage = effective;
            doSearch(currentPage);
        }
    }

    function doSearch(page) {
        page = page || 1;
        currentPage = page;
        var q = buildQuery();
        var queryKey = JSON.stringify(q) + '|page=' + page + '|per=' + currentPerPage;

        if (activeRequest) {
            activeRequest.abort();
        }

        currentQuery = queryKey;

        $results.addClass('inblue-loading');

        activeRequest = $.ajax({
            url: inblueLib.ajaxUrl,
            type: 'POST',
            data: {
                action:   'inblue_search_books',
                nonce:    inblueLib.nonce,
                query:    q.query,
                category: q.category,
                year:     q.year,
                page:     page,
                per_page: currentPerPage,
            },
            dataType: 'json',
            timeout: 60000,
            success: function(resp) {
                if (queryKey !== currentQuery) {
                    return;
                }
                $results.removeClass('inblue-loading');
                if (resp.success) {
                    totalBooks = parseInt(resp.total, 10) || 0;
                    updateCount(totalBooks);
                    updateUrl();
                    $results.html(renderGrid(resp.books));
                    $pagination.html(renderPagination(resp));
                    fillGrid(false);
                } else {
                    totalBooks = 0;
                    updateCount(0);
                    updateUrl();
                    $results.html('<p class="inblue-no-results">' + esc(noResultsText()) + '</p>');
                    $pagination.html('');
                }
                activeRequest = null;
            },
            error: function(xhr, status) {
                if (queryKey !== currentQuery) {
                    return;
                }
                $results.removeClass('inblue-loading');
                var msg = 'Server error. Try again.';
                if (status === 'timeout') {
                    msg = 'The request timed out. Try again.';
                }
                $results.html('<p class="inblue-error">' + msg + '</p>');
                activeRequest = null;
            }
        });
    }

    function onFilterChange() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() {
            doSearch(1);
        }, 300);
    }

    function onResize() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            fillGrid(true);
        }, 250);
    }

    if ($searchInput.length) {
        $searchInput.on('input', onFilterChange);
    }
    if ($filterCat.length) {
        $filterCat.on('change', onFilterChange);
    }
    if ($filterYear.length) {
        $filterYear.on('change', onFilterChange);
    }
    $(window).on('resize', onResize);

    $pagination.on('click', 'button.page-numbers', function(e) {
        e.preventDefault();
        var page = parseInt($(this).data('page'), 10);
        if (!isNaN(page)) {
            doSearch(page);
        }
    });

    // Fill the last row on load (and on resize) so the grid never shows
    // empty cells. The AJAX sort now matches the server, so no reordering.
    if ($results.length) {
        fillGrid(true);
    }

// Obtener portada desde la página pública del libro (solo editores/admins logueados)
    $('.inblue-get-cover-front').on('click', function() {
        var btn = $(this);
        var frame = btn.closest('.inblue-cover-frame');
        var cover = btn.closest('.inblue-single-cover');
        var status = cover.find('.inblue-cover-status');

        btn.prop('disabled', true);
        status.text('Extrayendo...');

        $.post(inblueLib.ajaxUrl, {
            action:       'inblue_get_cover',
            post_id:      btn.data('postId'),
            nonce:        inblueLib.coverNonce,
            pdf_source:   btn.data('pdfSource'),
            pdf_url:      btn.data('pdfUrl'),
            pdf_file_id:  btn.data('pdfFileId'),
        }, function(resp) {
            btn.prop('disabled', false);
            if (resp.success) {
                status.text('');
                if (resp.data && resp.data.thumbnail_url) {
                    frame.removeClass('inblue-cover-frame-empty').html('<img src="' + resp.data.thumbnail_url + '" alt="">');
                } else {
                    status.text('Portada asignada');
                }
            } else {
                status.text(resp.data ? resp.data : 'Error al obtener la portada');
            }
        }).fail(function() {
            btn.prop('disabled', false);
            status.text('Error de conexión. Inténtalo de nuevo.');
        });
    });

    // Añadir / quitar libro de destacados (solo editores/admins logueados)
    $('.inblue-featured-toggle').on('click', function() {
        var btn = $(this);
        var cover = btn.closest('.inblue-single-cover');
        var status = cover.find('.inblue-featured-status');

        btn.prop('disabled', true);

        $.post(inblueLib.ajaxUrl, {
            action: 'inblue_toggle_featured',
            post_id: btn.data('postId'),
            nonce: inblueLib.featuredNonce,
        }, function(resp) {
            btn.prop('disabled', false);
            if (resp.success) {
                var featured = !!resp.data.featured;
                btn.toggleClass('is-featured', featured);
                cover.find('.inblue-featured-label').text(featured ? 'Desmarcar' : 'Destacar');
                status.text(resp.data.message || '');
            } else {
                status.text(resp.data ? resp.data : 'Error al actualizar');
            }
        }).fail(function() {
            btn.prop('disabled', false);
            status.text('Error de conexión. Inténtalo de nuevo.');
        });
    });

    // Featured: arrow navigation + drag/touch scroll
    $('.inblue-featured-wrap').each(function() {
        var $wrap = $(this);
        var $body = $wrap.find('.inblue-featured-body');
        var $prev = $wrap.find('.inblue-featured-arrow--prev');
        var $next = $wrap.find('.inblue-featured-arrow--next');
        var body = $body[0];
        if (!body) return;

        function updateArrows() {
            var sl = Math.round(body.scrollLeft);
            var maxScroll = Math.round(body.scrollWidth - body.clientWidth);
            $prev.toggleClass('is-hidden', sl <= 2);
            $next.toggleClass('is-hidden', sl >= maxScroll - 2);
        }

        $prev.on('click', function() {
            body.scrollBy({ left: -320, behavior: 'smooth' });
        });
        $next.on('click', function() {
            body.scrollBy({ left: 320, behavior: 'smooth' });
        });

        $body.on('scroll', updateArrows);
        updateArrows();

        // Drag/touch scroll (sin bloquear el click en los enlaces)
        var isDragging = false;
        var wasDragging = false;
        var startX = 0;
        var startY = 0;
        var scrollLeftStart = 0;
        var DRAG_THRESHOLD = 8;

        body.addEventListener('pointerdown', function(e) {
            if (e.button && e.button !== 0) return;
            // Mouse: prevenir la selección de texto y el drag nativo de imágenes,
            // sin bloquear el click posterior en el enlace.
            if (e.pointerType === 'mouse') {
                e.preventDefault();
            }
            isDragging = false;
            wasDragging = false;
            startX = e.clientX;
            startY = e.clientY;
            scrollLeftStart = body.scrollLeft;
        });

        body.addEventListener('pointermove', function(e) {
            if (startX === 0 && startY === 0) return;
            var dx = e.clientX - startX;
            var dy = e.clientY - startY;
            if (!isDragging && (Math.abs(dx) > DRAG_THRESHOLD) && Math.abs(dx) > Math.abs(dy)) {
                isDragging = true;
                wasDragging = true;
                body.classList.add('dragging');
            }
            if (isDragging) {
                body.scrollLeft = scrollLeftStart - dx;
            }
        });

        function endDrag() {
            if (isDragging) {
                body.classList.remove('dragging');
            }
            isDragging = false;
            startX = 0;
            startY = 0;
        }

        body.addEventListener('pointerup', endDrag);
        body.addEventListener('pointercancel', endDrag);
        body.addEventListener('pointerleave', endDrag);

        // Si hubo arrastre, suprimir el click que sigue al pointerup
        body.addEventListener('click', function(e) {
            if (wasDragging) {
                e.preventDefault();
                e.stopPropagation();
                wasDragging = false;
            }
        }, true);
    });

    // Botón "Regresar": vuelve a la última página de la biblioteca que el
    // usuario visitó (paginación y filtros incluidos, guardados en sessionStorage).
    $(document).on('click', '.inblue-back-btn', function(e) {
        e.preventDefault();
        var libraryUrl = $(this).attr('data-library-url') || '';
        var returnUrl = '';

        if (window.sessionStorage) {
            try {
                returnUrl = sessionStorage.getItem('inblueLibraryReturnUrl') || '';
            } catch (err) {}
        }

        // Solo usamos el estado guardado si pertenece a la página de biblioteca
        // (comparando la ruta base, sin el sufijo /page/N/)
        if (returnUrl && libraryUrl) {
            try {
                var returnPath = new URL(returnUrl, window.location.origin).pathname.replace(/\/page\/\d+\/?$/, '').replace(/\/$/, '');
                var libraryPath = new URL(libraryUrl, window.location.origin).pathname.replace(/\/$/, '');
                if (returnPath === libraryPath) {
                    window.location.href = returnUrl;
                    return;
                }
            } catch (err) {}
        }

        window.location.href = libraryUrl || window.location.origin;
    });

})(jQuery);