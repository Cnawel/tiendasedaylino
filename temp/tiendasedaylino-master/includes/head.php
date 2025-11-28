<?php
/**
 * ========================================================================
 * HEAD COMÚN (Meta/SEO y CSS/JS) - Tienda Seda y Lino
 * ========================================================================
 * Provee función para imprimir meta tags, título y assets compartidos.
 * Diseñado para ser llamado dentro de <head> en cada página.
 * 
 * Funciones
 * - render_head(string $title, array $options = []): imprime head común.
 *   $options soporta:
 *     - description (string)
 *     - keywords (string)
 *     - og_image (string URL)
 *     - noindex (bool)
 *     - css_version (string) para cache busting de css/style.css
 * 
 * @package TiendaSedaYLino
 * @version 1.0
 * ========================================================================
 */

if (!function_exists('render_head')) {
    /**
     * Renderiza el contenido del <head> con meta/SEO y assets compartidos.
     *
     * @param string $title       Título de la página (se agregarán sufijos de marca).
     * @param array  $options     Opciones de meta/SEO (description, keywords, og_image, noindex, css_version).
     * @return void
     */
    function render_head($title, $options = []) {
        // Configuración por defecto
        $description = isset($options['description']) ? (string)$options['description'] : 'Elegancia en seda y lino. Moda de calidad artesanal.';
        $keywords    = isset($options['keywords']) ? (string)$options['keywords'] : 'seda, lino, moda, camisas, blusas, pantalones, shorts';
        $ogImage     = isset($options['og_image']) ? (string)$options['og_image'] : 'imagenes/imagen.png';
        $noIndex     = !empty($options['noindex']);
        $cssVersion  = isset($options['css_version']) ? (string)$options['css_version'] : '2.0';

        // Sanitización básica para evitar HTML no deseado
        $safeTitle       = htmlspecialchars(trim($title ?: 'Seda y Lino'), ENT_QUOTES, 'UTF-8');
        $safeDescription = htmlspecialchars(trim($description), ENT_QUOTES, 'UTF-8');
        $safeKeywords    = htmlspecialchars(trim($keywords), ENT_QUOTES, 'UTF-8');
        $safeOgImage     = htmlspecialchars(trim($ogImage), ENT_QUOTES, 'UTF-8');

        ?>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $safeTitle; ?> | Seda y Lino</title>
        <meta name="description" content="<?php echo $safeDescription; ?>">
        <meta name="keywords" content="<?php echo $safeKeywords; ?>">
        <meta name="author" content="Seda y Lino">
        <?php if ($noIndex): ?>
            <meta name="robots" content="noindex, nofollow">
        <?php else: ?>
            <meta name="robots" content="index, follow">
        <?php endif; ?>

        <!-- Open Graph / Social -->
        <meta property="og:title" content="<?php echo $safeTitle; ?> | Seda y Lino">
        <meta property="og:description" content="<?php echo $safeDescription; ?>">
        <meta property="og:image" content="<?php echo $safeOgImage; ?>">
        <meta property="og:type" content="website">

        <!-- Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <!-- Custom CSS (includes color variables) -->
        <link rel="stylesheet" href="css/style.css?v=<?php echo htmlspecialchars($cssVersion, ENT_QUOTES, 'UTF-8'); ?>">
        <?php
    }
}


