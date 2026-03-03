<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Motorlan_Offers_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( array(
            'singular' => 'oferta',
            'plural'   => 'ofertas',
            'ajax'     => false,
        ) );
    }

    public function get_columns() {
        return array(
            'cb'           => '<input type="checkbox" />',
            'status'       => 'Estado',
            'motor'        => 'Publicación / Motor',
            'comprador'    => 'Comprador (Quien oferta)',
            'vendedor'     => 'Vendedor (Dueño)',
            'offer_amount' => 'Monto Ofertado',
            'offer_date'   => 'Fecha',
            // 'actions'      => 'Acciones',
        );
    }

    public function get_sortable_columns() {
        return array(
            'offer_amount' => array( 'offer_amount', false ),
            'offer_date'   => array( 'offer_date', false ),
            'status'       => array( 'status', false ),
        );
    }

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'offer_amount':
                return '$ ' . number_format( $item['offer_amount'], 2 );
            case 'offer_date':
                return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item['offer_date'] ) );
            case 'actions':
                // Placeholder actions
                return '';
            default:
                return print_r( $item, true );
        }
    }

    protected function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="offer[]" value="%s" />',
            $item['id']
        );
    }

    protected function column_motor( $item ) {
        $post_id = $item['publication_id'];
        $title   = get_the_title( $post_id );
        
        $formatted_name = function_exists('motorlan_format_motor_name') 
            ? motorlan_format_motor_name($post_id) 
            : $title;

        // Fallback if formatter returns empty for some reason (rare)
        if ( ! $formatted_name ) {
            $formatted_name = $title . ' <span class="description">(Sin datos de formato)</span>';
        }

        $edit_link = get_edit_post_link( $post_id );
        
        // Build actions row
        $actions = array(
            'view' => sprintf('<a href="%s" target="_blank">Ver Publicación</a>', get_permalink($post_id)),
            'edit' => sprintf('<a href="%s">Editar Publicación</a>', $edit_link),
        );

        return sprintf( 
            '<strong><a class="row-title" href="%s">%s</a></strong>%s',
            $edit_link,
            esc_html( $formatted_name ),
            $this->row_actions( $actions )
        );
    }

    protected function column_comprador( $item ) {
        $user_id = $item['user_id'];
        $user    = get_userdata( $user_id );
        if ( ! $user ) {
            return 'Usuario Eliminado (' . $user_id . ')';
        }
        $edit_link = extract( get_edit_user_link( $user_id ) );
        
        // Avatar
        $avatar = get_avatar( $user_id, 32 );
        return sprintf( 
            '<div style="display:flex; align-items:center; gap:8px;">%s <a href="%s"><strong>%s</strong></a> <br><span style="color:#666">%s</span></div>',
            $avatar,
            get_edit_user_link( $user_id ),
            $user->display_name,
            $user->user_email
        );
    }

    protected function column_vendedor( $item ) {
        // Need to get author of publication
        $post_id = $item['publication_id'];
        $post    = get_post( $post_id );
        if ( ! $post ) return '-';
        
        $author_id = $post->post_author;
        $user      = get_userdata( $author_id );
        if ( ! $user ) { 
             return 'Usuario Eliminado';
        }

        return sprintf( 
            '<a href="%s">%s</a>',
            get_edit_user_link( $author_id ),
            $user->display_name
        );
    }

    protected function column_status( $item ) {
        $status = $item['status'];
        
        // Map status to labels and colors
        $labels = array(
            'pending' => 'Pendiente',
            'accepted_pending_confirmation' => 'Esperando Confirmación',
            'confirmed' => 'Confirmada',
            'rejected'  => 'Rechazada',
            'expired'   => 'Expirada',
            'accepted'  => 'Aceptada (Old)',
        );

        $colors = array(
            'pending' => '#f0ad4e',
            'accepted_pending_confirmation' => '#5bc0de',
            'confirmed' => '#5cb85c',
            'rejected'  => '#d9534f',
            'expired'   => '#777',
            'accepted'  => '#5cb85c',
        );

        $label = isset($labels[$status]) ? $labels[$status] : $status;
        $color = isset($colors[$status]) ? $colors[$status] : '#ccc';

        return sprintf(
            '<span class="badge" style="background-color:%s; color:white; padding:4px 8px; border-radius:4px; font-weight:bold; font-size:11px;">%s</span>',
            esc_attr($color),
            esc_html($label)
        );
    }

    public function prepare_items() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'motorlan_offers';
        $per_page   = 10;
        
        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns(); // define sortable
        
        $this->_column_headers = array( $columns, $hidden, $sortable );
        
        // Processes bulk action
        // $this->process_bulk_action();

        // Pagination
        $current_page = $this->get_pagenum();
        $offset       = ( $current_page - 1 ) * $per_page;

        // Sorting
        $orderby = ( ! empty( $_REQUEST['orderby'] ) ) ? sanitize_text_field( $_REQUEST['orderby'] ) : 'offer_date';
        $order   = ( ! empty( $_REQUEST['order'] ) ) ? sanitize_text_field( $_REQUEST['order'] ) : 'DESC';

        // Valid columns to sort by
        $valid_sorts = array('offer_amount', 'offer_date', 'status');
        if ( ! in_array( $orderby, $valid_sorts ) ) {
            $orderby = 'offer_date';
        }

        // Fetch Total
        $total_items = $wpdb->get_var( "SELECT COUNT(id) FROM $table_name" );
        
        // Fetch Items
        $data = $wpdb->get_results( 
            $wpdb->prepare( 
                "SELECT * FROM $table_name ORDER BY $orderby $order LIMIT %d OFFSET %d", 
                $per_page, 
                $offset 
            ), 
            ARRAY_A 
        );

        $this->items = $data;

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ) );
    }
}
