<?php
/**
 * ACF helpers for Purchases admin screens.
 *
 * - Prefill missing values in admin edit form for legacy records.
 * - Keep compatibility meta in sync when saving.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

if ( ! function_exists( 'motorlan_normalize_user_id' ) ) {
    // Fallback, but the canonical helper exists in purchases-routes.php
    function motorlan_normalize_user_id( $user_value ) {
        if ( is_numeric( $user_value ) ) return (int) $user_value;
        if ( $user_value instanceof WP_User ) return (int) $user_value->ID;
        if ( is_object( $user_value ) && isset( $user_value->ID ) ) return (int) $user_value->ID;
        if ( is_array( $user_value ) && isset( $user_value['ID'] ) ) return (int) $user_value['ID'];
        return 0;
    }
}

// Prefill Usuario from legacy buyer meta when empty
add_filter( 'acf/load_value/name=usuario', function( $value, $post_id, $field ) {
    if ( $value ) return $value;

    $buyer = function_exists( 'get_field' ) ? get_field( 'comprador', $post_id ) : null;
    if ( ! $buyer ) $buyer = get_post_meta( $post_id, 'comprador', true );
    if ( ! $buyer ) $buyer = get_post_meta( $post_id, 'comprador_id', true );

    return $buyer ? motorlan_normalize_user_id( $buyer ) : $value;
}, 10, 3 );

// Prefill Precio de Compra when empty (from meta, offer, or publication price)
add_filter( 'acf/load_value/name=precio_compra', function( $value, $post_id, $field ) {
    if ( $value !== null && $value !== '' ) return $value;

    $existing = get_post_meta( $post_id, 'precio_compra', true );
    if ( $existing !== '' && $existing !== null ) return (float) $existing;

    // From offer amount, if linked
    $offer_id = (int) get_post_meta( $post_id, 'offer_id', true );
    if ( $offer_id ) {
        global $wpdb; $table = $wpdb->prefix . 'motorlan_offers';
        $amount = $wpdb->get_var( $wpdb->prepare( "SELECT offer_amount FROM $table WHERE id = %d", $offer_id ) );
        if ( null !== $amount ) return (float) $amount;
    }

    // From related publication's sale price
    $related = function_exists( 'get_field' ) ? get_field( 'publicacion', $post_id ) : null;
    if ( ! $related ) $related = get_post_meta( $post_id, 'publicacion', true );
    if ( ! $related ) $related = ( function_exists( 'get_field' ) ? get_field( 'motor', $post_id ) : null );
    if ( ! $related ) $related = get_post_meta( $post_id, 'motor', true );

    $publicacion_id = null;
    if ( $related instanceof WP_Post ) $publicacion_id = (int) $related->ID;
    elseif ( is_array( $related ) && isset( $related['ID'] ) ) $publicacion_id = (int) $related['ID'];
    elseif ( is_numeric( $related ) ) $publicacion_id = (int) $related;

    if ( $publicacion_id ) {
        $price = function_exists( 'get_field' ) ? get_field( 'precio_de_venta', $publicacion_id ) : get_post_meta( $publicacion_id, 'precio_de_venta', true );
        if ( $price !== '' && $price !== null ) return (float) $price;
    }

    return $value;
}, 10, 3 );

// Keep meta synchronized after saving a purchase
add_action( 'acf/save_post', function( $post_id ) {
    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'compra' ) return;

    $usuario = function_exists( 'get_field' ) ? get_field( 'usuario', $post_id ) : null;
    if ( $usuario ) {
        $uid = motorlan_normalize_user_id( $usuario );
        update_field( 'comprador', $uid, $post_id );
        update_post_meta( $post_id, 'comprador_id', $uid );
    }

    $precio = function_exists( 'get_field' ) ? get_field( 'precio_compra', $post_id ) : get_post_meta( $post_id, 'precio_compra', true );
    if ( $precio !== '' && $precio !== null ) {
        update_post_meta( $post_id, 'precio_compra', (float) $precio );
    }
}, 20 );

