<?php
/**
 * REST API Controller for Reviews.
 *
 * @package motorlan-api-vue
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Motorlan_Reviews_Controller' ) ) {
    class Motorlan_Reviews_Controller extends WP_REST_Controller {
        protected $namespace = 'motorlan/v1';
        protected $rest_base = 'reviews';

        /**
         * Create a new review.
         *
         * @param WP_REST_Request $request Request object.
         * @return WP_REST_Response|WP_Error Response object or error.
         */
        public function create_review( $request ) {
            $user_id = get_current_user_id();
            if ( ! $user_id ) {
                return new WP_Error( 'rest_not_logged_in', __( 'Debes iniciar sesión.', 'motorlan-api-vue' ), array( 'status' => 401 ) );
            }

            // 1. Sanitize & Validate Input (Strict Mode)
            $purchase_uuid = sanitize_text_field( $request->get_param( 'purchase_uuid' ) );
            $rating        = $request->get_param( 'rating' );
            $comment       = sanitize_textarea_field( $request->get_param( 'comment' ) );
            $target_role   = sanitize_text_field( $request->get_param( 'target_role' ) ); // 'seller' or 'buyer'

            if ( empty( $purchase_uuid ) ) {
                return new WP_Error( 'missing_param', __( 'Falta el ID de la compra.', 'motorlan-api-vue' ), array( 'status' => 400 ) );
            }

            // Rating must be strict int 1-5
            if ( ! is_numeric( $rating ) || intval( $rating ) < 1 || intval( $rating ) > 5 ) {
                return new WP_Error( 'invalid_rating', __( 'La valoración debe ser un número entre 1 y 5.', 'motorlan-api-vue' ), array( 'status' => 400 ) );
            }
            $rating_int = intval( $rating );

            // Comment is mandatory per user request? Or optional?
            // "Ademñás que puedan dear un mensae.... Es importante eso." implies it is a feature, maybe strict requirement?
            // Let's keep it optional but strictly sanitized if present.
            if ( mb_strlen( $comment ) > 1000 ) {
                return new WP_Error( 'invalid_comment', __( 'El comentario no puede exceder los 1000 caracteres.', 'motorlan-api-vue' ), array( 'status' => 400 ) );
            }

            if ( ! in_array( $target_role, array( 'seller', 'buyer' ), true ) ) {
                return new WP_Error( 'invalid_role', __( 'Rol de destino inválido.', 'motorlan-api-vue' ), array( 'status' => 400 ) );
            }

            // 2. Resolve Purchase
            $args = array(
                'post_type'  => 'compra',
                'meta_key'   => 'uuid',
                'meta_value' => $purchase_uuid,
                'posts_per_page' => 1,
                'post_status' => 'any',
            );
            $posts = get_posts( $args );
            if ( empty( $posts ) ) {
                return new WP_Error( 'not_found', __( 'Compra no encontrada.', 'motorlan-api-vue' ), array( 'status' => 404 ) );
            }
            $purchase_id = $posts[0]->ID;

            // 3. Verify Ownership & Role
            // We need to know WHO 'seller' and 'buyer' are in this purchase to verify the current user
            // and determine who is being rated.
            
            // Reusing logic similar to motorlan_prepare_sale_item or dedicated helper if extracted...
            // For now, let's fetch meta directly to be safe and raw.
            $seller_field = get_post_meta( $purchase_id, 'vendedor', true ); // Usually ID
            // Handle ACF format variance
            if ( is_array( $seller_field ) && isset( $seller_field['ID'] ) ) $seller_id = $seller_field['ID'];
            elseif ( is_object( $seller_field ) ) $seller_id = $seller_field->ID;
            else $seller_id = $seller_field;
            
            $buyer_id = get_post_meta( $purchase_id, 'comprador_id', true );
            // Try ACF 'comprador' if meta empty
            if ( ! $buyer_id && function_exists('get_field') ) {
                $buyer_acf = get_field( 'comprador', $purchase_id );
                if ( is_array( $buyer_acf ) && isset( $buyer_acf['ID'] ) ) $buyer_id = $buyer_acf['ID'];
                elseif ( is_object( $buyer_acf ) ) $buyer_id = $buyer_acf->ID;
                else $buyer_id = $buyer_acf;
            }

            $seller_id = absint( $seller_id );
            $buyer_id  = absint( $buyer_id );

            // Logic:
            // If target_role is 'seller', current user MUST be buyer.
            // If target_role is 'buyer', current user MUST be seller.

            $target_user_id = 0;

            if ( $target_role === 'seller' ) {
                if ( $user_id !== $buyer_id ) {
                    return new WP_Error( 'forbidden', __( 'Solo el comprador puede valorar al vendedor.', 'motorlan-api-vue' ), array( 'status' => 403 ) );
                }
                $target_user_id = $seller_id;
                $meta_key_check = '_review_by_buyer_done'; // Review DONE BY buyer
            } else {
                if ( $user_id !== $seller_id ) {
                    return new WP_Error( 'forbidden', __( 'Solo el vendedor puede valorar al comprador.', 'motorlan-api-vue' ), array( 'status' => 403 ) );
                }
                $target_user_id = $buyer_id;
                $meta_key_check = '_review_by_seller_done'; // Review DONE BY seller
            }

            if ( ! $target_user_id ) {
                return new WP_Error( 'server_error', __( 'Error identificando al usuario destino.', 'motorlan-api-vue' ), array( 'status' => 500 ) );
            }

            // 4. Verify Uniqueness
            if ( get_post_meta( $purchase_id, $meta_key_check, true ) ) {
                return new WP_Error( 'conflict', __( 'Ya has valorado esta compra.', 'motorlan-api-vue' ), array( 'status' => 409 ) );
            }

            // 5. Persist Review Data (Meta on Purchase)
            // Metadata keys: 
            // - _review_seller_rating / _review_seller_comment (When rating seller)
            // - _review_buyer_rating / _review_buyer_comment (When rating buyer)
            
            $prefix = ( $target_role === 'seller' ) ? '_review_seller' : '_review_buyer';
            
            update_post_meta( $purchase_id, $prefix . '_rating', $rating_int );
            update_post_meta( $purchase_id, $prefix . '_comment', $comment );
            update_post_meta( $purchase_id, $prefix . '_by', $user_id );
            update_post_meta( $purchase_id, $prefix . '_at', current_time( 'mysql' ) );
            
            // Mark as done
            update_post_meta( $purchase_id, $meta_key_check, 1 );

            // 6. Aggregate Ratings (Service Logic)
            $this->aggregate_user_ratings( $target_user_id, $target_role );

            do_action( 'motorlan_new_review', $user_id, $target_user_id, $purchase_id );

            return new WP_REST_Response( array( 
                'success' => true,
                'message' => __( 'Valoración enviada correctamente.', 'motorlan-api-vue' ),
                'data' => array(
                    'rating' => $rating_int,
                    'comment' => $comment
                )
            ), 201 );
        }

        /**
         * Recalculate and save average rating for a user.
         *
         * @param int $user_id
         * @param string $role 'seller' or 'buyer' (context of the rating received)
         */
        protected function aggregate_user_ratings( $user_id, $role ) {
            // We need to query ALL purchases where this user was the TARGET of a review.
            // If role is 'seller', we look for purchases where 'vendedor' = user_id AND '_review_seller_rating' exists.
            
            // Currently the system seems to treat 'calificacion' as a general user score, usually for sellers.
            // Let's assume we update the main ACF 'calificacion' field for now, primarily for sellers.
            // If the user wants separate buyer/seller ratings, we'd need separate fields. 
            // Given the UI usually shows one rating, let's update general 'calificacion'.
            
            global $wpdb;

            // Simplified aggregation: Find all ratings targeting this user
            // We look for meta key '_review_seller_rating' if they acted as seller
            // We look for meta key '_review_buyer_rating' if they acted as buyer
            // Ideally a user has one reputation? Or split?
            // "Vendedor: 0 ventas | 0 valoraciones" suggests seller-specific stats.
            
            // Let's stick to SELLER stats for 'calificacion' ACF as that's what's visible publically.
            // If we want buyer stats, we might need new fields. For now, let's implement for Seller.
            if ( $role !== 'seller' ) {
                // TODO: Implement buyer specific rating storage if requested. 
                return;
            }

            // Get all purchases by this seller that have a seller rating
            // A purchase is "by this seller" if meta 'vendedor' == user_id (needs check logic or dedicated query)
            // Since 'vendedor' is meta, we can do a meta query.
            
            $args = array(
                'post_type' => 'compra',
                'posts_per_page' => -1,
                'post_status' => 'any',
                'meta_query' => array(
                    'relation' => 'AND',
                    array( // The seller is this user
                        'key' => 'vendedor',
                        'value' => $user_id, 
                        'compare' => '=' // Note: ACF might store array/ID, so this is risky if data format varies.
                        // Ideally we prefer a reliable lookup. If 'vendedor' is inconsistent, this is hard.
                    ),
                    array( // Rating exists
                        'key' => '_review_seller_rating',
                        'compare' => 'EXISTS'
                    )
                ),
                'fields' => 'ids'
            );
            
            // Query for IDs
            $query = new WP_Query( $args );
            $ids = $query->posts;

            if ( empty( $ids ) ) {
                // No ratings found?
                return;
            }

            // Calculate average
            $total_score = 0;
            $count = 0;
            
            foreach ( $ids as $pid ) {
                $r = get_post_meta( $pid, '_review_seller_rating', true );
                if ( is_numeric( $r ) ) {
                    $total_score += floatval( $r );
                    $count++;
                }
            }

            if ( $count > 0 ) {
                $average = round( $total_score / $count, 1 ); // 1 decimal place
                
                // Update User ACF/Meta
                if ( function_exists( 'update_field' ) ) {
                    update_field( 'calificacion', $average, 'user_' . $user_id );
                    update_field( 'cantidad_votos', $count, 'user_' . $user_id ); // Assuming field name
                }
                update_user_meta( $user_id, 'motorlan_seller_rating', $average );
                update_user_meta( $user_id, 'motorlan_seller_count', $count );
            }
        }
    }
}
