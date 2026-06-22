<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_footer', 'iterochat_print_widget' );
function iterochat_print_widget() {
	if ( is_admin() ) {
		return;
	}
	if ( ! iterochat_is_connected() ) {
		return;
	}
	$conn = iterochat_get_connection();
	if ( empty( $conn['enabled'] ) || empty( $conn['widget_key'] ) ) {
		return;
	}
	printf(
		'<script src="%s/widget.js" data-widget-key="%s" async></script>' . "\n",
		esc_url( iterochat_widget_url() ),
		esc_attr( $conn['widget_key'] )
	);
}
