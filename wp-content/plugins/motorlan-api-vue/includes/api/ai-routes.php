<?php
/**
 * AI routes for the WordPress-hosted assistant and semantic search surfaces.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

require_once MOTORLAN_API_VUE_PATH . 'includes/ai/class-motorlan-ai-search-service.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/ai/class-motorlan-ai-assistant-service.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/ai/class-motorlan-ai-orchestrator.php';

function motorlan_get_ai_orchestrator() {
    static $instance = null;

    if ( null === $instance ) {
        $instance = new Motorlan_AI_Orchestrator();
    }

    return $instance;
}

function motorlan_register_ai_routes() {
    register_rest_route(
        'motorlan/v1',
        '/ai/status',
        array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => 'motorlan_ai_status_response',
                'permission_callback' => '__return_true',
            ),
        )
    );

    register_rest_route(
        'motorlan/v1',
        '/ai/chat',
        array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => 'motorlan_ai_chat_response',
                'permission_callback' => '__return_true',
                'args'                => array(
                    'message' => array(
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_textarea_field',
                    ),
                ),
            ),
        )
    );

    register_rest_route(
        'motorlan/v1',
        '/ai/search',
        array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => 'motorlan_ai_search_response',
                'permission_callback' => '__return_true',
                'args'                => array(
                    'query' => array(
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            ),
        )
    );
}
add_action( 'rest_api_init', 'motorlan_register_ai_routes' );

function motorlan_ai_status_response() {
    return new WP_REST_Response( motorlan_get_ai_orchestrator()->get_status(), 200 );
}

function motorlan_ai_chat_response( WP_REST_Request $request ) {
    $message = (string) $request->get_param( 'message' );
    $context = (array) $request->get_param( 'context' );

    return new WP_REST_Response( motorlan_get_ai_orchestrator()->chat( $message, $context ), 200 );
}

function motorlan_ai_search_response( WP_REST_Request $request ) {
    $query   = (string) $request->get_param( 'query' );
    $filters = (array) $request->get_param( 'filters' );

    return new WP_REST_Response( motorlan_get_ai_orchestrator()->search( $query, $filters ), 200 );
}
