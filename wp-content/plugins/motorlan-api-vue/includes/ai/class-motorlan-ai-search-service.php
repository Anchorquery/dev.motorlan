<?php
/**
 * Search service for AI-powered catalog discovery.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Motorlan_AI_Search_Service' ) ) {
    class Motorlan_AI_Search_Service {
        /**
         * Search publications using the existing catalog data.
         *
         * @param string $query
         * @param array  $filters
         * @param int    $limit
         * @return array
         */
        public function search( $query, $filters = array(), $limit = 8 ) {
            $query = trim( sanitize_text_field( (string) $query ) );

            if ( '' === $query ) {
                return array(
                    'query'   => $query,
                    'answer'  => 'Escribe una referencia, marca, potencia o tipo de motor para buscar.',
                    'mode'    => 'php',
                    'total'   => 0,
                    'results' => array(),
                );
            }

            // Los filtros llegan del cliente (endpoint público): no pueden cambiar estado,
            // paginación ni orden (el builder ya bloquea precio y estados para no admin).
            $filters = is_array( $filters ) ? $filters : array();
            unset( $filters['status'], $filters['post_status'], $filters['stock_min'], $filters['per_page'], $filters['page'], $filters['orderby'], $filters['order'], $filters['author'] );

            $args = motorlan_build_publicaciones_query_args(
                array_merge(
                    $filters,
                    array(
                        'per_page' => max( 1, min( 24, (int) $limit ) ),
                        'page'     => 1,
                        'status'   => 'publish',
                        'search'   => $query,
                    )
                )
            );

            $args['posts_per_page'] = max( 1, (int) $limit * 2 );
            $args['no_found_rows']   = true;
            $args['orderby']         = 'date';
            $args['order']           = 'DESC';

            $wp_query = new WP_Query( $args );
            $items    = array();

            if ( $wp_query->have_posts() ) {
                while ( $wp_query->have_posts() ) {
                    $wp_query->the_post();
                    $post_id = get_the_ID();
                    $data    = motorlan_get_publicacion_data( $post_id );

                    if ( empty( $data ) ) {
                        continue;
                    }

                    $items[] = $this->score_publication( $data, $query );
                }
                wp_reset_postdata();
            }

            usort(
                $items,
                static function( $a, $b ) {
                    $score_a = isset( $a['score'] ) ? (float) $a['score'] : 0.0;
                    $score_b = isset( $b['score'] ) ? (float) $b['score'] : 0.0;

                    if ( $score_a === $score_b ) {
                        return strcmp( (string) ( $b['title'] ?? '' ), (string) ( $a['title'] ?? '' ) );
                    }

                    return $score_a < $score_b ? 1 : -1;
                }
            );

            $items = array_slice( $items, 0, $limit );
            $total = count( $items );

            return array(
                'query'   => $query,
                'answer'  => $this->build_answer( $query, $items ),
                'mode'    => 'php',
                'total'   => $total,
                'results' => $items,
            );
        }

        /**
         * Score a publication using a simple relevance heuristic.
         *
         * @param array  $data
         * @param string $query
         * @return array
         */
        protected function score_publication( $data, $query ) {
            $haystack = array(
                strtolower( (string) ( $data['title'] ?? '' ) ),
                strtolower( (string) ( $data['slug'] ?? '' ) ),
                strtolower( (string) ( $data['acf']['tipo_o_referencia'] ?? '' ) ),
                strtolower( (string) ( $data['marca_name'] ?? '' ) ),
            );

            $taxonomy_names = array();
            if ( ! empty( $data['tipo'] ) && is_array( $data['tipo'] ) ) {
                foreach ( $data['tipo'] as $term ) {
                    if ( is_array( $term ) && ! empty( $term['name'] ) ) {
                        $taxonomy_names[] = strtolower( (string) $term['name'] );
                    }
                }
            }

            $text = implode( ' ', array_merge( $haystack, $taxonomy_names ) );
            $terms = preg_split( '/\s+/', strtolower( trim( $query ) ) ) ?: array();
            $score = 0.0;

            foreach ( $terms as $term ) {
                if ( '' === $term ) {
                    continue;
                }

                if ( false !== strpos( $text, $term ) ) {
                    $score += 2.0;
                }

                if ( false !== strpos( strtolower( (string) ( $data['title'] ?? '' ) ), $term ) ) {
                    $score += 3.0;
                }

                if ( false !== strpos( strtolower( (string) ( $data['acf']['tipo_o_referencia'] ?? '' ) ), $term ) ) {
                    $score += 2.5;
                }
            }

            if ( false !== stripos( (string) ( $data['title'] ?? '' ), $query ) ) {
                $score += 4.0;
            }

            return array_merge(
                $data,
                array(
                    'score'   => $score,
                    'source'  => 'catalog',
                    'excerpt' => $this->build_excerpt( $data ),
                )
            );
        }

        /**
         * Build a short excerpt from the publication data.
         *
         * @param array $data
         * @return string
         */
        protected function build_excerpt( $data ) {
            $parts = array();

            if ( ! empty( $data['marca_name'] ) ) {
                $parts[] = $data['marca_name'];
            }

            if ( ! empty( $data['acf']['tipo_o_referencia'] ) ) {
                $parts[] = $data['acf']['tipo_o_referencia'];
            }

            if ( ! empty( $data['acf']['potencia'] ) ) {
                $parts[] = $data['acf']['potencia'] . ' kW';
            }

            if ( ! empty( $data['acf']['velocidad'] ) ) {
                $parts[] = $data['acf']['velocidad'] . ' rpm';
            }

            if ( empty( $parts ) ) {
                $parts[] = __( 'Coincidencia del catálogo', 'motorlan-api-vue' );
            }

            return implode( ' · ', $parts );
        }

        /**
         * Build the assistant answer.
         *
         * @param string $query
         * @param array  $items
         * @return string
         */
        protected function build_answer( $query, $items ) {
            if ( empty( $items ) ) {
                return sprintf(
                    'No encontré coincidencias claras para "%s". Puedo afinar por marca, referencia, potencia, velocidad o tipo.',
                    $query
                );
            }

            $titles = array();
            foreach ( array_slice( $items, 0, 3 ) as $item ) {
                if ( ! empty( $item['title'] ) ) {
                    $titles[] = $item['title'];
                }
            }

            $lead = sprintf( 'Encontré %d coincidencias para "%s".', count( $items ), $query );

            if ( ! empty( $titles ) ) {
                $lead .= ' Las más relevantes son: ' . implode( ', ', $titles ) . '.';
            }

            return $lead;
        }
    }
}

