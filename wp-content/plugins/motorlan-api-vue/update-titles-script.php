<?php
/**
 * Script to update all 'publicacion' post titles based on the current formatting rules.
 * 
 * Usage: Run this script directly from a browser or via CLI.
 * Make sure it's in the WordPress environment.
 */

// Load WordPress environment
// Adjust the path if this script is moved
require_once( dirname(__FILE__) . '/../../../wp-load.php' );

// Check permissions if run from browser
if ( php_sapi_name() !== 'cli' && !current_user_can('administrator') ) {
    die('Unauthorized access.');
}

// Ensure the helper functions exist
$helpers_path_motor = dirname(__FILE__) . '/includes/api/motor-helpers.php';
$helpers_path_pub =  dirname(__FILE__) . '/includes/api/publicaciones/helpers.php';

if ( file_exists( $helpers_path_motor ) && file_exists( $helpers_path_pub ) ) {
    require_once( $helpers_path_motor );
    require_once( $helpers_path_pub );
} else {
    die('Error: includes/api/motor-helpers.php o includes/api/publicaciones/helpers.php no encontrados.');
}

$is_cli = (php_sapi_name() === 'cli' || empty($_SERVER['REMOTE_ADDR']));
$batch_size = isset($_GET['batch']) ? intval($_GET['batch']) : ($is_cli ? -1 : 50);
$offset     = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

// Get a batch of publications
$args = array(
    'post_type'      => 'publicacion',
    'post_status'    => 'any',
    'posts_per_page' => $batch_size,
    'offset'         => $offset,
    'fields'         => 'ids',
    'orderby'        => 'ID',
    'order'          => 'ASC'
);

$query = new WP_Query($args);
$post_ids = $query->posts;

$total_found = $query->found_posts;

echo "<h2>Actualizando Títulos y Slugs (En lotes)</h2>";
echo "Procesando offset <b>$offset</b>. Restantes: <b>$total_found</b> publicaciones en total.<br>\n<hr>";

$updated_count = 0;
$skipped_count = 0;
$error_count = 0;

foreach ( $post_ids as $post_id ) {
    $old_title = get_the_title($post_id);
    $new_title = motorlan_format_motor_name($post_id);

    // If for some reason the formatter returns empty, we might not want to wipe the title
    if ( empty($new_title) ) {
        echo "Post ID $post_id: Skipping (New title is empty).<br>\n";
        $skipped_count++;
        continue;
    }

    $post = get_post($post_id);
    if (!$post) {
        $skipped_count++;
        continue;
    }

    $old_slug = $post->post_name ?? '';
    
    $update_data = array(
        'ID' => $post_id,
    );

    $has_changes = false;

    if ( $old_title !== $new_title ) {
        $update_data['post_title'] = $new_title;
        $has_changes = true;
    }

    // Generar nuevo slug
    if ( function_exists('motorlan_generate_slug_by_post_id') ) {
        $new_slug = motorlan_generate_slug_by_post_id($post_id);
        if ( !empty($new_slug) && $new_slug !== $old_slug ) {
            $update_data['post_name'] = $new_slug;
            $has_changes = true;
        }
    }

    if ( !$has_changes ) {
        echo "Post ID $post_id: Skipping (Title and slug already match).<br>\n";
        $skipped_count++;
        continue;
    }

    $result = wp_update_post($update_data);

    if ( is_wp_error($result) ) {
        echo "Post ID $post_id: Error updating - " . $result->get_error_message() . "<br>\n";
        $error_count++;
    } else {
        echo "Post ID $post_id: Updated from '$old_title' to '$new_title'.<br>\n";
        $updated_count++;
    }

    // Optional: Add a small delay if the database is huge
    // usleep(50000); 
}

echo "<br>\nSummary del lote:<br>\n";
echo "Total procesado hoy: " . count($post_ids) . "<br>\n";
echo "Actualizados: $updated_count<br>\n";
echo "Saltados (ya estaban bien): $skipped_count<br>\n";
echo "Errores: $error_count<br>\n";

echo "<hr>";
if ( count($post_ids) === $batch_size ) {
    $next_offset = $offset + $batch_size;
    echo "<h3>Avanzando al siguiente lote en 2 segundos... No cierres esta pestaña.</h3>";
    echo "<script>
        setTimeout(function() {
            window.location.href = '?offset=' + $next_offset + '&batch=' + $batch_size;
        }, 2000);
    </script>";
} else {
    echo "<h3 style='color:green'>¡ACTUALIZACIÓN COMPLETADA TOTALMENTE!</h3>";
    echo "<p>Ya puedes cerrar esta ventana.</p>";
}
