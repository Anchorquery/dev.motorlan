<?php
/**
 * REST API routes for motor questions.
 *
 * @package motorlan-api-vue
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

function format_publication_response( $post ) {
    if ( ! $post ) {
        return null;
    }

    $post_id = $post->ID;
    $publicacion_item = array(
        'id'           => $post_id,
        'uuid'         => get_post_meta( $post_id, 'uuid', true ),
        'title'        => get_the_title( $post_id ),
        'slug'         => $post->post_name,
        'status'       => get_field( 'publicar_acf', $post_id ),
        'imagen_destacada' => get_field( 'motor_image', $post_id, true ),
        'author_id'    => (int) $post->post_author,
        'acf'          => array(),
    );

    if ( function_exists( 'get_field' ) ) {
        $acf_fields = [
            'marca',
            'tipo_o_referencia',
            'precio_de_venta',
        ];

        foreach ( $acf_fields as $field_name ) {
            $value = get_field( $field_name, $post_id );
            if ( $field_name === 'marca' && $value ) {
                $term = get_term( $value, 'marca' );
                if ( $term && ! is_wp_error( $term ) ) {
                    $publicacion_item['acf']['marca'] = array(
                        'id'   => $term->term_id,
                        'name' => $term->name,
                    );
                }
            } else {
                $publicacion_item['acf'][ $field_name ] = $value;
            }
        }
    }

    return $publicacion_item;
}

/*
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
        'callback' => 'motorlan_get_received_questions_callback',
        'permission_callback' => 'motorlan_is_user_authenticated',
    ) );

    register_rest_route( $namespace, '/user/publications-list', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_user_publications_list_callback',
        'permission_callback' => 'motorlan_is_user_authenticated',
    ) );
}
add_action( 'rest_api_init', 'motorlan_register_question_rest_routes' );
*/

function motorlan_get_user_publications_list_callback( WP_REST_Request $request ) {
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return new WP_Error( 'rest_not_logged_in', 'Sorry, you are not allowed to do that.', array( 'status' => 401 ) );
    }

    $args = array(
        'post_type'      => 'publicacion',
        'posts_per_page' => -1,
        'author'         => $user_id,
        'fields'         => 'ids',
    );

    $user_publications = get_posts( $args );

    $publications_list = array();
    foreach ( $user_publications as $pub_id ) {
        $publications_list[] = array(
            'value' => $pub_id,
            'title' => get_the_title( $pub_id ),
        );
    }

    return new WP_REST_Response( $publications_list, 200 );
}

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
    update_field( 'publication_owner', $publication_author_id, $post_id );
    
    $publication_title = get_the_title( $publicacion_id );
    $publication_url = get_permalink( $publicacion_id );
    $user_who_asked = get_userdata( $user_id );

    $notification_manager = new Motorlan_Notification_Manager();
    $notification_manager->create_notification(
        $publication_author_id,
        'new_question',
        "Nueva pregunta de {$user_who_asked->display_name} en \"{$publication_title}\"",
        $pregunta,
        [
            'publication_id' => $publicacion_id,
            'question_id'    => $post_id,
            'url'            => '/dashboard/publications/questions',
        ],
        ['web', 'email']
    );

    return new WP_REST_Response( array( 'success' => true, 'id' => $post_id ), 201 );
}

function motorlan_answer_question_callback( WP_REST_Request $request ) {
    $question_id = (int) $request['id'];
    $respuesta   = sanitize_text_field( $request->get_param( 'respuesta' ) );

    update_field( 'respuesta', $respuesta, $question_id );
    update_field( 'answer_date', current_time( 'mysql' ), $question_id );

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

function motorlan_get_received_questions_callback( WP_REST_Request $request ) {
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return new WP_Error( 'rest_not_logged_in', 'Sorry, you are not allowed to do that.', array( 'status' => 401 ) );
    }

    $params = $request->get_params();
    $per_page = isset( $params['per_page'] ) ? (int) $params['per_page'] : 10;
    $page = isset( $params['page'] ) ? (int) $params['page'] : 1;

    $questions_args = array(
        'post_type'      => 'pregunta',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'meta_query'     => array(
            'relation' => 'AND',
            array(
                'key'     => 'publication_owner',
                'value'   => $user_id,
                'compare' => '=',
            ),
        ),
    );

    if ( ! empty( $params['search'] ) ) {
        $questions_args['s'] = sanitize_text_field( $params['search'] );
    }

    if ( ! empty( $params['publication_id'] ) ) {
        $questions_args['meta_query'][] = array(
            'key'   => 'publicacion',
            'value' => (int) $params['publication_id'],
        );
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
            $question_post = get_post( $qid );
            $publicacion_id = get_field( 'publicacion', $qid );
            $publicacion = get_post( $publicacion_id );
            $formateada = format_publication_response( $publicacion );
            $respuesta = get_field( 'respuesta', $qid );
            $user_field = get_field( 'usuario', $qid );
            $user_id = is_array($user_field) ? $user_field['ID'] : $user_field;
            $user_data = get_userdata( $user_id );
            $user_display_name = $user_data ? $user_data->display_name : 'Usuario desconocido';

            $answer_date = get_field( 'answer_date', $qid );

            $questions[] = array(
                'id'            => $qid,
                'pregunta'      => get_field( 'pregunta', $qid ),
                'respuesta'     => $respuesta,
                'publicacion'   => $formateada,
                'question_date' => $question_post->post_date,
                'user_name'     => $user_display_name,
                'answer_date'   => $answer_date,
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
