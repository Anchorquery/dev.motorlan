<?php
/**
 * REST API routes for motor questions.
 *
 * @package motorlan-api-vue
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

function motorlan_register_question_rest_routes() {
    $namespace = 'motorlan/v1';

    register_rest_route( $namespace, '/publicaciones/(?P<publicacion_id>\d+)/questions', array(
        array(
            'methods'  => WP_REST_Server::READABLE,
            'callback' => 'motorlan_get_questions_callback',
            'permission_callback' => '__return_true',
        ),
        array(
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => 'motorlan_create_question_callback',
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ),
    ) );

    register_rest_route( $namespace, '/questions/(?P<id>\\d+)/answer', array(
        'methods'  => WP_REST_Server::CREATABLE,
        'callback' => 'motorlan_answer_question_callback',
        'permission_callback' => function ( WP_REST_Request $request ) {
            $question_id = (int) $request['id'];
            $publicacion_id    = (int) get_field( 'publicacion', $question_id );
            $publicacion_post  = get_post( $publicacion_id );
            $current_user = get_current_user_id();

            if ( ! $publicacion_post ) {
                return false;
            }

            return (int) $publicacion_post->post_author === $current_user || current_user_can( 'edit_posts' );
        },
    ) );
}
add_action( 'rest_api_init', 'motorlan_register_question_rest_routes' );

function motorlan_get_questions_callback( WP_REST_Request $request ) {
    $publicacion_id = (int) $request['publicacion_id'];

    $args = array(
        'post_type'      => 'pregunta',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'   => 'publicacion',
                'value' => $publicacion_id,
            ),
        ),
    );

    $query = new WP_Query( $args );
    $questions = array();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $qid = get_the_ID();
            $questions[] = array(
                'id'       => $qid,
                'pregunta' => get_field( 'pregunta', $qid ),
                'respuesta'=> get_field( 'respuesta', $qid ),
            );
        }
        wp_reset_postdata();
    }

    return new WP_REST_Response( array( 'data' => $questions ), 200 );
}

function motorlan_create_question_callback( WP_REST_Request $request ) {
    $publicacion_id = (int) $request['publicacion_id'];
    $user_id  = get_current_user_id();
    $pregunta = sanitize_text_field( $request->get_param( 'pregunta' ) );

    $post_id = wp_insert_post( array(
        'post_type'   => 'pregunta',
        'post_status' => 'publish',
        'post_title'  => wp_trim_words( $pregunta, 8, '...' ),
    ) );

    if ( is_wp_error( $post_id ) ) {
        return new WP_Error( 'cannot_create', 'Cannot create question', array( 'status' => 500 ) );
    }

    update_field( 'usuario', $user_id, $post_id );
    update_field( 'publicacion', $publicacion_id, $post_id );
    update_field( 'pregunta', $pregunta, $post_id );

    return new WP_REST_Response( array( 'success' => true, 'id' => $post_id ), 201 );
}

function motorlan_answer_question_callback( WP_REST_Request $request ) {
    $question_id = (int) $request['id'];
    $respuesta   = sanitize_text_field( $request->get_param( 'respuesta' ) );

    update_field( 'respuesta', $respuesta, $question_id );

    return new WP_REST_Response( array( 'success' => true ), 200 );
}
