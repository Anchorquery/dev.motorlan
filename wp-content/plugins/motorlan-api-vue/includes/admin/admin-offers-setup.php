<?php

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Register the "Ofertas" admin page and remove the default CPT menu.
 */
function motorlan_register_offers_menu() {
    // Add our custom page
    // We use 'dashicons-tag' which is the common icon for offers/tags, or match the CPT's icon
    $hook = add_menu_page(
        'Ofertas',
        'Ofertas',
        'manage_options',
        'motorlan-offers',
        'motorlan_render_offers_page',
        'dashicons-tag',
        30 // Position roughly where CPTs usually appear
    );

    // Load screen options if needed (optional)
    add_action( "load-$hook", 'motorlan_offers_screen_options' );

    // Remove the default CPT menu if it exists
    remove_menu_page( 'edit.php?post_type=oferta' );
}
add_action( 'admin_menu', 'motorlan_register_offers_menu', 99 );

/**
 * Screen options for the custom list table.
 */
function motorlan_offers_screen_options() {
    // This is where you could add 'per_page' options
}

/**
 * Render the Offers admin page.
 */
function motorlan_render_offers_page() {
    // Ensure class is loaded
    if ( ! class_exists( 'Motorlan_Offers_List_Table' ) ) {
        require_once MOTORLAN_API_VUE_PATH . 'includes/classes/class-motorlan-offers-list-table.php';
    }

    $offers_table = new Motorlan_Offers_List_Table();
    $offers_table->prepare_items();

    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Ofertas de la Plataforma</h1>
        <!-- Optional: <a href="#" class="page-title-action">Añadir Nueva</a> -->
        <hr class="wp-header-end">

        <form method="get">
            <input type="hidden" name="page" value="motorlan-offers" />
            <?php
                //$offers_table->search_box( 'Buscar', 'search_id' );
                $offers_table->display(); 
            ?>
        </form>
    </div>
    <?php
}
