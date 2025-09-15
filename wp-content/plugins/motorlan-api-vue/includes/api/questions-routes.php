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
            'permission_callback' => 'motorlan_permission_callback_true',
        ),
        array(
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => 'motorlan_create_question_callback',
            'permission_callback' => 'motorlan_is_user_authenticated',
        ),
    ) );

    register_rest_route( $namespace, '/questions/(?P<id>\\d+)/answer', array(
        'methods'  => WP_REST_Server::CREATABLE,
        'callback' => 'motorlan_answer_question_callback',
        'permission_callback' => 'motorlan_can_user_answer_question',
    ) );

    register_rest_route( $namespace, '/user/questions', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_user_questions_callback',
        'permission_callback' => 'motorlan_is_user_authenticated',
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

    // Notify publication owner
    $publication_author_id = get_post_field( 'post_author', $publicacion_id );
    $author_data = get_userdata( $publication_author_id );
    $author_email = $author_data->user_email;
    $publication_title = get_the_title( $publicacion_id );
    $publication_url = get_permalink( $publicacion_id );

    $subject = 'Nueva pregunta en tu publicación: ' . $publication_title;
    $message = 'Has recibido una nueva pregunta en tu publicación "' . $publication_title . '".';
    $message .= '\n\nPregunta: ' . $pregunta;
    $message .= '\n\nPuedes ver la publicación aquí: ' . $publication_url;
    
    wp_mail( $author_email, $subject, $message );

    return new WP_REST_Response( array( 'success' => true, 'id' => $post_id ), 201 );
}

function motorlan_answer_question_callback( WP_REST_Request $request ) {
    $question_id = (int) $request['id'];
    $respuesta   = sanitize_text_field( $request->get_param( 'respuesta' ) );

    update_field( 'respuesta', $respuesta, $question_id );

    return new WP_REST_Response( array( 'success' => true ), 200 );
}

function motorlan_can_user_answer_question( WP_REST_Request $request ) {
    $question_id = (int) $request['id'];
    $user_id = get_current_user_id();

    if ( ! $user_id ) {
        return false;
    }

    $publicacion_id = get_field( 'publicacion', $question_id );
    if ( ! $publicacion_id ) {
        return false;
    }

    $publication_author_id = get_post_field( 'post_author', $publicacion_id );

    return $user_id == $publication_author_id;
}

function motorlan_get_user_questions_callback( WP_REST_Request $request ) {
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return new WP_Error( 'rest_not_logged_in', 'Sorry, you are not allowed to do that.', array( 'status' => 401 ) );
    }

    $params = $request->get_params();
    $per_page = isset( $params['per_page'] ) ? (int) $params['per_page'] : 10;
    $page = isset( $params['page'] ) ? (int) $params['page'] : 1;

    $args = array(
        'post_type'      => 'publicacion',
        'posts_per_page' => -1,
        'author'         => $user_id,
        'fields'         => 'ids',
    );

    $user_publications = get_posts( $args );

    if ( empty( $user_publications ) ) {
        return new WP_REST_Response( array( 'data' => [], 'pagination' => array( 'total' => 0 ) ), 200 );
    }

    $questions_args = array(
        'post_type'      => 'pregunta',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'meta_query'     => array(
            'relation' => 'AND',
            array(
                'key'     => 'publicacion',
                'value'   => $user_publications,
                'compare' => 'IN',
            ),
        ),
    );

    if ( ! empty( $params['search'] ) ) {
        $questions_args['s'] = sanitize_text_field( $params['search'] );
    }

    if ( ! empty( $params['status'] ) ) {
        if ( $params['status'] === 'answered' ) {
            $questions_args['meta_query'][] = array(
                'key'     => 'respuesta',
                'compare' => 'EXISTS',
            );
            $questions_args['meta_query'][] = array(
                'key'     => 'respuesta',
                'value'   => '',
                'compare' => '!=',
            );
        } elseif ( $params['status'] === 'pending' ) {
            $questions_args['meta_query'][] = array(
                'relation' => 'OR',
                array(
                    'key'     => 'respuesta',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => 'respuesta',
                    'value'   => '',
                    'compare' => '=',
                ),
            );
        }
    }

    $query = new WP_Query( $questions_args );
    $questions = array();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $qid = get_the_ID();
            $publicacion_id = get_field( 'publicacion', $qid );
            $questions[] = array(
                'id'       => $qid,
                'pregunta' => get_field( 'pregunta', $qid ),
                'respuesta'=> get_field( 'respuesta', $qid ),
                'publication_title' => get_the_title( $publicacion_id ),
                'publication_slug' => get_post_field( 'post_name', $publicacion_id ),
            );
        }
        wp_reset_postdata();
    }

    $total_questions = $query->found_posts;

    $response = array(
        'data' => $questions,
        'pagination' => array(
            'total' => (int) $total_questions,
        ),
    );

    return new WP_REST_Response( $response, 200 );
}
