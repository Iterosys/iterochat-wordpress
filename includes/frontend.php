<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'iterochat_enqueue_widget' );
function iterochat_enqueue_widget() {
	if ( ! iterochat_is_connected() ) {
		return;
	}
	$conn = iterochat_get_connection();
	if ( empty( $conn['enabled'] ) || empty( $conn['widget_key'] ) ) {
		return;
	}
	// Load the hosted widget in the footer, cache-busted by the plugin version.
	wp_enqueue_script(
		'iterochat-widget',
		iterochat_widget_url() . '/widget.js',
		array(),
		ITEROCHAT_VERSION,
		true
	);
}

// The widget reads its key from a data-widget-key attribute on its own script
// tag, so add that attribute (and async) to the enqueued tag.
add_filter( 'script_loader_tag', 'iterochat_widget_script_tag', 10, 2 );
function iterochat_widget_script_tag( $tag, $handle ) {
	if ( 'iterochat-widget' !== $handle ) {
		return $tag;
	}
	$conn = iterochat_get_connection();
	$key  = isset( $conn['widget_key'] ) ? $conn['widget_key'] : '';
	$attr = sprintf( ' data-widget-key="%s" async', esc_attr( $key ) );
	return str_replace( ' src=', $attr . ' src=', $tag );
}
