<?php
// Find WordPress root
$root = dirname(__FILE__);
while ($root !== DIRECTORY_SEPARATOR && !file_exists($root . DIRECTORY_SEPARATOR . 'wp-load.php')) {
    $parent = dirname($root);
    if ($parent === $root) break;
    $root = $parent;
}

if (!file_exists($root . DIRECTORY_SEPARATOR . 'wp-load.php')) {
    die("Error: Could not find wp-load.php\n");
}

require_once($root . DIRECTORY_SEPARATOR . 'wp-load.php');

$pid = 5241; // ID from screenshot
$image_id = get_post_meta($pid, 'motor_image', true);

echo "Product ID: $pid\n";
echo "Image ID from meta: " . (is_array($image_id) ? json_encode($image_id) : $image_id) . "\n";

if ($image_id) {
    if (is_numeric($image_id)) {
         $url = wp_get_attachment_image_url($image_id, 'thumbnail');
         echo "Calculated Thumbnail URL: " . ($url ? $url : "FAILED TO GET URL") . "\n";
    } else {
         echo "Image meta is not numeric, it might be already a URL or array.\n";
    }
} else {
    echo "No motor_image found for this product.\n";
}
