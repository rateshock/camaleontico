<?php 
if( !defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN') ) 
	exit;

delete_option( 'wp_setefi_settings' );
flush_rewrite_rules();