<?php
/**
 * Assistant service that wraps the catalog search and formats an agent-like reply.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Motorlan_AI_Assistant_Service' ) ) {
    class Motorlan_AI_Assistant_Service {
        /**
         * @var Motorlan_AI_Search_Service
         */
        protected $search_service;

        public function __construct( $search_service = null ) {
            $this->search_service = $search_service instanceof Motorlan_AI_Search_Service ? $search_service : new Motorlan_AI_Search_Service();
        }

        /**
         * Generate an assistant reply.
         *
         * @param string $message
         * @param array  $context
         * @return array
         */
        public function respond( $message, $context = array() ) {
            $message = trim( sanitize_textarea_field( (string) $message ) );
            $search  = $this->search_service->search( $message, is_array( $context ) ? $context : array(), 5 );

            $suggested_prompts = array(
                'Busca una alternativa por referencia exacta',
                'Filtra por potencia y velocidad',
                'Muéstrame opciones de una marca concreta',
            );

            return array(
                'sessionId'         => ! empty( $context['sessionId'] ) ? sanitize_text_field( (string) $context['sessionId'] ) : wp_generate_uuid4(),
                'answer'            => $this->build_agent_answer( $message, $search ),
                'mode'              => 'php',
                'sources'           => $this->build_sources_from_results( $search['results'] ?? array() ),
                'suggestedPrompts'  => $suggested_prompts,
                'search'            => $search,
            );
        }

        /**
         * Build the final user-facing answer.
         *
         * @param string $message
         * @param array  $search
         * @return string
         */
        protected function build_agent_answer( $message, $search ) {
            $results = $search['results'] ?? array();

            if ( empty( $results ) ) {
                return sprintf(
                    'No encontré coincidencias útiles para "%s". Dame una referencia, marca, potencia o rango de velocidad y busco de nuevo.',
                    $message
                );
            }

            $best = $results[0];
            $parts = array();

            if ( ! empty( $best['title'] ) ) {
                $parts[] = 'La mejor coincidencia es ' . $best['title'];
            }

            if ( ! empty( $best['excerpt'] ) ) {
                $parts[] = $best['excerpt'];
            }

            if ( ! empty( $search['answer'] ) ) {
                $parts[] = $search['answer'];
            }

            return implode( '. ', array_filter( $parts ) );
        }

        /**
         * Convert result rows into compact sources.
         *
         * @param array $results
         * @return array
         */
        protected function build_sources_from_results( $results ) {
            $sources = array();

            foreach ( array_slice( (array) $results, 0, 3 ) as $item ) {
                $sources[] = array(
                    'id'    => (string) ( $item['id'] ?? '' ),
                    'title' => (string) ( $item['title'] ?? '' ),
                    'url'   => (string) ( $item['link'] ?? $item['url'] ?? '' ),
                );
            }

            return $sources;
        }
    }
}

