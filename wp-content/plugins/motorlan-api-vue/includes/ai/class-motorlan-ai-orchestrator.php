<?php
/**
 * Internal AI orchestrator for WordPress-hosted deployments.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Motorlan_AI_Orchestrator' ) ) {
    class Motorlan_AI_Orchestrator {
        /**
         * @var Motorlan_AI_Assistant_Service
         */
        protected $assistant_service;

        /**
         * @var Motorlan_AI_Search_Service
         */
        protected $search_service;

        public function __construct() {
            $this->search_service    = new Motorlan_AI_Search_Service();
            $this->assistant_service = new Motorlan_AI_Assistant_Service( $this->search_service );
        }

        public function get_status() {
            return array(
                'available'    => true,
                'mode'         => 'php-native',
                'service'      => 'motorlan-wordpress-ai',
                'capabilities' => array( 'chat', 'search', 'indexing' ),
                'message'      => 'AI runtime is embedded in the WordPress plugin.',
            );
        }

        public function search( $query, $filters = array() ) {
            return $this->search_service->search( $query, $filters, 8 );
        }

        public function chat( $message, $context = array() ) {
            return $this->assistant_service->respond( $message, $context );
        }
    }
}

