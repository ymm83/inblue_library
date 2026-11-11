# INBLUE Library

Plugin de WordPress para gestionar el catálogo de libros de **INBLUE Editorial**: portadas, autores múltiples, ISBN, DOI, PDF (subido o alojado en Google Drive), buscador AJAX, visor embebido, importación desde GitBook y backup/restauración.

- **Versión actual:** 1.3.8
- **Text domain:** `inblue-library`
- **Autor:** YMM by INBLUE Editorial
- **Sitio:** <https://inblueeditorial.com>

---

## Características principales

- **CPT `book`** con campos propios (autores, ISBN, DOI, fechas, editorial, idioma, páginas, PDF, portada, destacado…).
- **Taxonomías** `book_category` (jerárquica) y `book_tag`.
- **Biblioteca pública** con búsqueda AJAX, filtros por categoría y año, paginación y contador de visitas.
- **Shortcodes** para cuadrícula, buscador completo y carrusel de destacados/recientes.
- **Plantilla de libro** propia (`single-book.php`) con migas de pan, metadatos, botón de descarga y visor de PDF configurable.
- **Etiquetas automáticas** «Nuevo» y «Actualizado» según fechas configurables.
- **Importación desde GitBook** por categorías, con detección de libros nuevos y extracción de portadas.
- **Gestión de DOIs**: DOI de la página y DOI del PDF, comprobación de coincidencia, edición en lote y relleno automático.
- **Import / Export** en CSV o ZIP (datos + portadas), con exportación incremental y restauración.
- **Extracción de portada** desde PDF local (Imagick/Ghostscript) o desde Google Drive.

---

## Requisitos

| Requisito | Mínimo |
| --- | --- |
| WordPress | 5.0 |
| PHP | 7.4 |
| Extensiones PHP | `curl` (importación concurrente), `zip` (backup), `gd` o `imagick` (validar/generar imágenes) |

Opcionales:

- **Imagick con soporte PDF/Ghostscript** o el binario `gs` para generar portadas desde PDFs locales.
- Permisos de escritura en `wp-content/uploads` (portadas y backups temporales).
- Conexión saliente a Internet para GitBook, Google Drive y `doi.org`.

---

## Instalación

1. Copia la carpeta `inblue_library` en `wp-content/plugins/`.
2. Activa el plugin en **Plugins → Plugins instalados**.
3. Al activar se registran el CPT, las taxonomías y las reglas de permalink, y se crean las opciones por defecto.
4. Ve a **INBLUE Biblioteca → Settings** y configura la página de la biblioteca (o créala desde ahí).
5. Guarda los enlaces permanentes una vez (**Ajustes → Enlaces permanentes → Guardar**) si los libros no resuelven.

> El plugin refresca las reglas de reescritura automáticamente cuando cambia `INBLUE_LIBRARY_VERSION` o cuando se guarda/borra una página, pero un guardado manual de enlaces permanentes resuelve cualquier caso residual.

---

## Primeros pasos

### 1. Crear la página de la biblioteca

En **INBLUE Biblioteca → Settings**:

- Selecciona una página existente en **Página de la biblioteca**, o
- Escribe un título y pulsa **Crear página de biblioteca**. Se crea una página publicada con el shortcode `[library_search]` y se marca como página de la biblioteca.

La página detectada se usa para:

- Construir las URLs de los libros (`/{pagina}/{categoria}/{libro}`).
- El enlace **«Ver todos →»** del bloque de destacados.
- Las migas de pan y el botón **Regresar** del libro.

### 2. Configurar opciones

| Opción | Descripción | Valor por defecto |
| --- | --- | --- |
| Página de la biblioteca | Página que contiene `[library_search]` | — |
| Icono del título | Imagen junto al título de la biblioteca | — |
| Ocultar header de la página | Oculta el título/hero del tema en la página de la biblioteca | Activado |
| Tipo de visor de PDF | `browser` (`<embed>`), `iframe`, `drive` o `download` | `browser` |
| Libros por página | Resultados por página en la biblioteca | `12` |
| Días para marcar «Nuevo» | Vigencia de la etiqueta «Nuevo» | `180` |
| DOI | Activa el menú **DOIs** | Activado |
| Origen del DOI del PDF | `gitbook` (página del libro) o `pdf` (contenido del archivo) | `gitbook` |

---

## Uso

### Shortcodes

| Shortcode | Atributos | Descripción |
| --- | --- | --- |
| `[library_search]` | `title` | Biblioteca completa: buscador, filtros y paginación AJAX. |
| `[library_grid]` | `category`, `limit`, `orderby`, `order` | Cuadrícula simple de libros. |
| `[library_featured]` | `count`, `title` | Carrusel de destacados/recientes con enlace «Ver todos». |

Ejemplos:

```text
[library_search title="Biblioteca"]
[library_grid category="literatura" limit="8" orderby="date" order="DESC"]
[library_featured title="Recientes" count="5"]
```

#### Comportamiento de `[library_featured]`

El bloque intenta siempre mostrar `count` libros con este orden de prioridad:

1. Libros marcados como **destacados** (`_book_featured = 1`), ordenados por fecha de publicación.
2. Relleno con libros que tienen fecha de publicación (más reciente primero).
3. Relleno con libros sin fecha de publicación (más recientes primero).
4. **Al final**, libros aprobados como «Actualizado» (`_book_show_updated = 1`), ordenados por fecha de actualización. Estos desplazan a los últimos libros nuevos si hace falta hueco.

El número máximo de destacados manuales es **5** y se puede cambiar con el filtro `inblue_library_featured_limit`. Al marcar un sexto libro, se desmarca automáticamente el más antiguo.

### Página de un libro

La plantilla `public/templates/single-book.php` sustituye la del tema para el CPT `book` y muestra:

- Migas de pan (biblioteca → categoría → título) y botón **Regresar** que recupera la última posición/filtros de la biblioteca mediante `sessionStorage`.
- Portada, subtítulo, contenido, autores, fecha, editorial, ISBN, DOI enlazado a `doi.org`, páginas, idioma y fecha de actualización.
- Botones de **Descargar PDF** y **ver en GitBook** (si existen).
- Acciones para usuarios con permiso de edición: **Editar**, **Destacar/Desmarcar** y **Obtener portada**.
- Visor de PDF según la opción global: `<embed>` nativo, `iframe`, visor de Google Drive o solo descarga.

### URLs de libros

Con una página de biblioteca en `/biblioteca`:

```text
/biblioteca/                                            → listado
/biblioteca/page/2/                                     → paginación
/biblioteca/{categoria}/{libro}/                        → libro
/biblioteca/{libro}/                                    → libro sin categoría
```

La portada y los datos del libro se editan en **INBLUE Biblioteca → Añadir Libro**.

### Búsqueda AJAX

- Acción: `inblue_search_books` (`wp_ajax_` y `wp_ajax_nopriv_`).
- Busca en **título**, **extracto**, **autores**, **ISBN** y **DOI**.
- Filtros opcionales por **categoría** (ID de término) y **año** (`_book_year`).
- Ordena por `_book_pub_date` descendente y deja al final los libros sin fecha.
- Devuelve JSON con libros, total, páginas, página actual y lista de años.
- Los parámetros de la URL son `q` (búsqueda), `category` (slug) y `year`; el plugin neutraliza las query vars nativas `s` y `year` en la página de la biblioteca para evitar 404.

---

## Gestión de contenidos

### Campos del libro

Metadatos guardados por el plugin:

| Meta key | Contenido |
| --- | --- |
| `_book_authors` | Autores separados por comas |
| `_book_pub_date` | Fecha de publicación (`Y-m-d`) |
| `_book_update_date` | Fecha de actualización (`Y-m-d`) |
| `_book_show_updated` | `1` si se muestra la etiqueta «Actualizado» |
| `_book_year` | Año derivado de la fecha de publicación |
| `_book_isbn` | ISBN |
| `_book_doi` | DOI de la página del libro |
| `_book_doi_pdf` | DOI del PDF / página que aloja el PDF |
| `_book_publisher` | Editorial |
| `_book_language` | `es`, `en`, `pt`, `fr`, `other` |
| `_book_pages` | Número de páginas |
| `_book_pdf_source` | `upload` o `url` |
| `_book_pdf_url` | URL externa del PDF (Drive, etc.) |
| `_book_pdf_file_id` | ID del adjunto PDF subido |
| `_book_gitbook_url` | URL de la página del libro en GitBook |
| `_book_featured` | `1` si está destacado |
| `_book_views` | Contador de visitas |
| `_book_subtitle` | Subtítulo (usado en exportación; el subtítulo editable vive en el contenido del post) |

> En el metabox, el campo **Subtítulo** escribe en `post_content` del libro. El editor clásico no está disponible (`supports`: `title`, `thumbnail`, `excerpt`).

### Etiquetas «Nuevo» y «Actualizado»

- **Nuevo:** fecha de publicación dentro de los días configurados (`inblue_library_new_book_days`, por defecto 180).
- **Actualizado:** requiere fecha de actualización **y** la casilla «Mostrar como actualizado». Dura la mitad de los días configurados para «Nuevo».
- Si un libro es «Actualizado», la etiqueta sustituye a «Nuevo» en las tarjetas.

### Visitas

Cada visita a la página de un libro incrementa `_book_views` (se omite en previews y en el admin). El valor se muestra en las tarjetas de la biblioteca.

---

## Importación desde GitBook

Menú **INBLUE Biblioteca → Import from GitBook**:

1. **Importar todas las categorías**: recorre las 4 categorías configuradas en `Inblue_Gitbook_Importer::CATEGORIES` (Ciencias Sociales, Ciencias de las Ingenierías y TIC, Ciencias de la Salud, Literatura).
2. Descarga los índices `.md` de GitBook, detecta los enlaces a libros y obtiene las páginas en lotes concurrentes de 10.
3. Crea o actualiza cada libro, extrayendo título, autores, ISBN, DOI(s), fecha, año y URL del PDF (incluidos enlaces de Google Drive).
4. **No extrae portadas** durante la importación general: usa **Extraer portadas de todos los libros**.
5. **Limpiar caché** borra los transients `inblue_gitbook_*` (caché de 1 hora).

Menú **Libros nuevos**:

- Compara GitBook con la biblioteca y lista los libros aún no importados.
- Guarda el resultado en un transient de 1 hora y permite importar solo los seleccionados.
- Al crear un libro nuevo desde este flujo, intenta obtener su portada automáticamente.

> La importación omite los libros que ya tienen portada y solo actualiza metadatos de libros que ya tienen `_book_gitbook_url`.

---

## Gestión de DOIs

Menú **INBLUE Biblioteca → DOIs** (visible si la opción DOI está activa):

- Lista todos los libros publicados con **DOI de la página** y **DOI del PDF**.
- Estado por libro: **✓ Coinciden**, **⚠ No coinciden**, **⚠ Incompleto** o **— Sin DOI**.
- Edición en línea de ambos DOIs y guardado por lotes.
- Filtro por estado, búsqueda por título/DOI y paginación de 50 libros.
- **Rellenar DOI PDF** y **Rellenar DOI de la página**: procesan 10 libros por pasada, con opción de sobrescribir los existentes.
- Selector del **origen del DOI del PDF**:
  - `gitbook`: lee el DOI publicado en la página de GitBook del libro.
  - `pdf`: descarga el PDF (Drive o URL directa), descomprime sus streams y busca el DOI dentro del archivo.

Los DOIs se normalizan antes de guardar: se eliminan prefijos `https://doi.org/`, `doi:`, entidades HTML y puntuación sobrante, y se aceptan enlaces Markdown. El DOI propio de la editorial (`10.56168/…`) y los enlaces a `doi.org` tienen prioridad al extraer de un PDF.

---

## Import / Export

Menú **INBLUE Biblioteca → Import / Export**.

### Exportar

- **Datos y metadatos** → `books.csv` dentro del ZIP.
- **Portadas** → carpeta `covers/{ID}.{ext}` dentro del ZIP.
- **Solo modificados desde** una fecha → exportación incremental (compara `_book_update_date`, `_book_pub_date` y `post_modified`).

Columnas del CSV:

```text
ID, Title, Subtitle, Description, Authors, Publication Date, Update Date,
Year, ISBN, DOI, DOI PDF, Publisher, Language, Pages, PDF Source, PDF URL,
PDF File ID, Categories, Tags, Featured, GitBook URL, Permalink, Cover URL
```

### Importar

- Acepta **CSV** o **ZIP** (el ZIP debe contener `books.csv`).
- Opciones: aplicar datos, aplicar portadas y **solo actualizar libros existentes**.
- **Importar desde cero**: borra todos los libros actuales antes de importar (pide confirmación).
- Un ZIP solo con `covers/` asigna cada portada al libro cuyo ID coincide con el nombre del archivo.
- Los libros se emparejan en este orden: **GitBook URL → DOI → ISBN → mismo ID de post → título exacto**.

---

## Estructura del proyecto

```text
inblue_library/
├── inblue_library.php              # Bootstrap, constantes, hooks de activación y singleton
├── includes/
│   ├── class-cpt.php               # CPT book, taxonomías, rewrites, helpers de fechas/orden
│   ├── class-metaboxes.php         # Metabox de detalles y guardado
│   ├── class-search.php            # Búsqueda AJAX, búsqueda SQL y catálogos de filtros
│   ├── class-shortcodes.php        # [library_search], [library_grid], [library_featured]
│   ├── class-gitbook-importer.php  # Importación y comprobación de libros en GitBook
│   └── class-import-export.php     # Exportación ZIP/CSV e importación
├── admin/
│   ├── class-admin.php             # Menús, assets, columnas, DOIs y extracción de portadas
│   └── views/
│       ├── settings.php
│       ├── gitbook-import.php
│       ├── new-books.php
│       ├── import-export.php
│       └── dois.php
├── public/
│   ├── class-public.php            # Assets, plantilla, body class, contador de visitas
│   └── templates/
│       ├── archive-book.php        # Biblioteca con filtros y paginación
│       └── single-book.php         # Ficha del libro y visor de PDF
└── assets/
    ├── css/{admin.css, public.css}
    └── js/{admin.js, admin-cover.js, admin-settings.js, public.js}
```

---

## Hooks y filtros

| Hook | Tipo | Descripción |
| --- | --- | --- |
| `inblue_library_featured_limit` | filtro | Número máximo de libros destacados (por defecto `5`). |

```php
add_filter('inblue_library_featured_limit', function () {
    return 8;
});
```

### Acciones AJAX

| Acción | Acceso | Uso |
| --- | --- | --- |
| `inblue_search_books` | público | Búsqueda/filtrado de la biblioteca |
| `inblue_get_cover` | admin + nonce | Extraer portada desde el metabox |
| `inblue_get_cover_list` | admin + nonce | Extraer portada desde el listado de libros |
| `inblue_toggle_featured` | admin + nonce | Destacar/desmarcar un libro |

### Opciones (`wp_options`)

`inblue_library_viewer_type`, `inblue_library_books_per_page`, `inblue_library_new_book_days`, `inblue_library_page_id`, `inblue_library_base_page`, `inblue_library_hide_page_header`, `inblue_library_title_icon`, `inblue_library_title`, `inblue_library_doi_enabled`, `inblue_library_doi_pdf_source`, `inblue_library_rewrite_version`.

---

## Notas de desarrollo

- **Sin build step**: PHP, CSS y JS se sirven tal cual. No hay `composer.json`, `package.json` ni pipeline de compilación.
- **Singleton** por clase (`get_instance()`); el bootstrap está en `inblue_library.php`.
- **Prefijos**: funciones/clases con `Inblue_` / `inblue_`; opciones y metas con `inblue_library_*` y `_book_*`.
- **Versionado de assets**: el admin usa `INBLUE_LIBRARY_VERSION`; los assets públicos usan `filemtime()` para evitar caché durante el desarrollo.
- **Portadas**: desde Drive se prueban varias URLs de miniatura (`thumbnail`, `lh3.googleusercontent.com`, etc.) y se valida que la imagen sea decodificable. En local se usa Imagick y, si falla, Ghostscript (`gs`).
- **Caché**: los índices y páginas de GitBook se cachean 1 hora en transients; la página base de la biblioteca se cachea 5 minutos en el object cache.
- **Internacionalización**: todas las cadenas usan el text domain `inblue-library` y se cargan desde `/languages` (el repositorio no incluye archivos `.pot`).

### Seguridad

- Las acciones de escritura del admin verifican **nonce** y capacidad (`manage_options`, `edit_post`).
- La búsqueda AJAX es de solo lectura y únicamente devuelve libros publicados.
- Las entradas se sanean con `sanitize_text_field`, `sanitize_textarea_field`, `esc_url_raw`, `absint`, etc., y se escapan al imprimir.
- La importación de un ZIP valida que las rutas de portada no salgan del directorio extraído.

---

## Estado del proyecto

Versión **1.3.8**. Para cambios de versión, actualiza de forma conjunta:

1. La cabecera `Version` de `inblue_library.php`.
2. La constante `INBLUE_LIBRARY_VERSION`.

El cambio de constante fuerza la regeneración de las reglas de reescritura en el siguiente acceso al admin.

---

## Autor

**YMM by INBLUE Editorial** — <https://inblueeditorial.com>
