<?php
/**
 * Test aislado de Motorlan_Purchase_Chat_Controller::validate_timestamp (sin WordPress).
 *
 * Uso: php tests/purchase-chat-test.php
 */

require __DIR__ . '/bootstrap.php';

class WP_REST_Controller {}

require dirname( __DIR__ ) . '/includes/api/purchases/controllers/class-purchase-chat-controller.php';

$ref  = new ReflectionClass( 'Motorlan_Purchase_Chat_Controller' );
$ctrl = $ref->newInstanceWithoutConstructor();

t_assert( true === $ctrl->validate_timestamp( '' ), 'vacío es válido (sin filtro)' );
t_assert( true === $ctrl->validate_timestamp( null ), 'null es válido' );
t_assert( true === $ctrl->validate_timestamp( '2026-11-09 10:00:00' ), 'fecha válida' );
t_assert( false === $ctrl->validate_timestamp( 'no-es-fecha' ), 'texto no fecha' );
t_assert( false === $ctrl->validate_timestamp( [ '1' ] ), 'array no lanza TypeError y es inválido' );
t_assert( false === $ctrl->validate_timestamp( 123 ), 'entero no aceptado' );

t_done();
