<?php
/*

Plugin Name: Wordpress Monetaweb Setefi
Plugin URI: http://filippoburatti.net/wordpress-setefi/
Description: Extends Wordpress with Setefi Monetaweb gateway. (+ paypal standalone addon)
Version: 2.5
Author: F. Buratti
Author URI: http://filippoburatti.net/
Textdomain: wordpress-setefi
Domain Path: /languages

Copyright 2017  Buratti Filippo (info@filippoburatti.net)
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This software is NOT to be distributed, but can be INCLUDED in WP themes: Premium or Contracted.
This software is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

*/

defined( 'ABSPATH' ) or die( 'Plugin file cannot be accessed directly.' );


// ACTIVATION & DEACTIVATION

function wp_setefi_install() {
	wp_setefi_custom_post_type();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wp_setefi_install' );

function wp_setefi_deactivation() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wp_setefi_deactivation' );


// LOCALIZATION

function wp_setefi_plugin_load() {

	load_plugin_textdomain('wordpress-setefi', false,  dirname( plugin_basename( __FILE__ ) ) . '/languages');


	define('WP_SETEFI_URL', plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__)));
    define('WP_SETEFI_PATH', plugin_dir_path(__FILE__));


	$options = get_option( 'wp_setefi_settings' );

	if (isset($options['wp_setefi_debug']) && $options['wp_setefi_debug']==1) {
		define('WP_SETEFI_DEBUG', true);
		define('WP_SETEFI_DEBUG_LOG_PATH', WP_SETEFI_PATH.'/log/log.txt');
		add_action('admin_notices', 'wp_setefi_admin_notice');
	}

	if(!defined('WP_SETEFI_DEBUG')) {
        define('WP_SETEFI_DEBUG', false);
    }

	if (isset($options['wp_setefi_js_css']) && $options['wp_setefi_js_css']==1) {
		add_action( 'wp_enqueue_scripts', 'wp_setefi_print_scripts' );
	}

}

add_action( 'plugins_loaded', 'wp_setefi_plugin_load' );


function wp_setefi_admin_notice() {

        if (WP_SETEFI_DEBUG) {  //debug is enabled. Check to make sure log file is writable
            $real_file = WP_SETEFI_DEBUG_LOG_PATH;
            if (!is_writeable($real_file)) {
                echo '<div class="updated"><p>' . __('Wordpress Setefi Debug log file is not writable. Please check to make sure that it has the correct file permission (ideally 644). Otherwise the plugin will not be able to write to the log file. The log file (log.txt) can be found in the root directory of the plugin - ', 'wordpress-setefi') . '<code>' . WP_SETEFI_URL . '</code></p></div>';
            }
        }
    }

// Add the clear log button handler
add_action( 'admin_post_wp-setefi-log-purge',  'purge_wp_setefi_log' );

function purge_wp_setefi_log() {
	if ( isset( $_GET['_wpnonce'] ) ) {

		if ( wp_verify_nonce( $_GET['_wpnonce'], 'wp-setefi-log-purge' ) ) {

			$real_file = WP_SETEFI_DEBUG_LOG_PATH;

			file_put_contents($real_file, "");

			wp_redirect( wp_get_referer() );
			die();
		}



	}
}


function wp_setefi_debug_log($msg, $result) {

	if (!WP_SETEFI_DEBUG) {
        return;
    }
    $date_time = date('F j, Y g:i a');//the_date('F j, Y g:i a', '', '', FALSE);
    $text = '[' . $date_time . '] - ' . ( $msg.' : ') . print_r($result,true) . "\n";

    // Write to log.txt file
    $fp = fopen(WP_SETEFI_DEBUG_LOG_PATH, 'a');
    fwrite($fp, $text);
    fclose($fp);  // close file
}

function wp_setefi_print_scripts() {

	global $post;
	if( !has_shortcode( $post->post_content, 'wp_setefi_payment_form') && !is_singular('payment') ) {
		return;
	}

	wp_register_script(
		'jquery-validate',
		WP_SETEFI_URL . "/assets/js/jquery.validate.min.js",
		//'http://cdn.jsdelivr.net/jquery.validation/1.13.1/jquery.validate.min.js',
		array('jquery'),
		null,
		true
	);

	wp_enqueue_script( 'jquery-validate');

	wp_register_script(
		'wp-setefi',
		WP_SETEFI_URL . "/assets/js/wp-setefi.js",
		array('jquery', 'jquery-validate'),
		null,
		true
	);

	wp_enqueue_script( 'wp-setefi');

	wp_register_style(
			'wp-setefi',
			WP_SETEFI_URL . "/assets/css/wp-setefi.css",
			array(),
			null
		);

	wp_enqueue_style( 'wp-setefi' );

}

// add_filter( 'pre_do_shortcode_tag', function( $a, $tag, $attr, $m ) {
//   if( 'wp_setefi_payment_form' === $tag ) {
// 		wp_register_script(
// 			'jquery-validate',
// 			WP_SETEFI_URL . "/assets/js/jquery.validate.min.js",
// 			//'http://cdn.jsdelivr.net/jquery.validation/1.13.1/jquery.validate.min.js',
// 			array('jquery'),
// 			null,
// 			true
// 		);
//
// 		wp_enqueue_script( 'jquery-validate');
//
// 		wp_register_script(
// 			'wp-setefi',
// 			WP_SETEFI_URL . "/assets/js/wp-setefi.js",
// 			array('jquery', 'jquery-validate'),
// 			null,
// 			true
// 		);
//
// 		wp_enqueue_script( 'wp-setefi');
//
// 		wp_register_style(
// 				'wp-setefi',
// 				WP_SETEFI_URL . "/assets/css/wp-setefi.css",
// 				array(),
// 				null
// 			);
//
// 		wp_enqueue_style( 'wp-setefi' );
//   }
//   return $a;
// }, 10, 4 );

// ADMIN SETTINGS

add_action( 'admin_menu', 'wp_setefi_add_admin_menu' );
add_action( 'admin_init', 'wp_setefi_settings_init' );

function wp_setefi_add_admin_menu() {

	add_submenu_page(
		'options-general.php', 			// location
		'Wordpress Monetaweb Setefi', 	// title
		'Wordpress Setefi', 			// menu
		'manage_options', 				//capabilities
		'wp_setefi', 					// slug
		'wp_setefi_options_page' 		// function
	);

}

function wp_setefi_settings_init() {

	register_setting( 'wp_setefi_options-group', 'wp_setefi_settings' );


	add_settings_section(
		'wp_setefi_main_section',
		__( 'Settings', 'wordpress-setefi' ),
		'wp_setefi_settings_section_callback',
		'wp_setefi_options-group'
	);

	add_settings_field(
		'wp_setefi_terminal_id',
		__( 'Terminal ID', 'wordpress-setefi' ),
		'wp_setefi_terminal_id_render',
		'wp_setefi_options-group',
		'wp_setefi_main_section'
	);

	add_settings_field(
		'wp_setefi_terminal_password',
		__( 'Terminal password', 'wordpress-setefi' ),
		'wp_setefi_terminal_password_render',
		'wp_setefi_options-group',
		'wp_setefi_main_section'
	);

	add_settings_field(
		'wp_setefi_paypal_email',
		__( 'Paypal email', 'wordpress-setefi' ),
		'wp_setefi_paypal_email_render',
		'wp_setefi_options-group',
		'wp_setefi_main_section'
	);

	add_settings_field(
		'wp_setefi_sandbox',
		__( 'Enable sandbox', 'wordpress-setefi' ),
		'wp_setefi_sandbox_render',
		'wp_setefi_options-group',
		'wp_setefi_main_section'
	);

	add_settings_field(
		'wp_setefi_debug',
		__( 'Enable debug', 'wordpress-setefi' ),
		'wp_setefi_debug_render',
		'wp_setefi_options-group',
		'wp_setefi_main_section'
	);

	add_settings_field(
		'wp_setefi_language',
		__( 'Language', 'wordpress-setefi' ),
		'wp_setefi_language_render',
		'wp_setefi_options-group',
		'wp_setefi_main_section'
	);

	add_settings_field(
		'wp_setefi_privacy_disclamer',
		__( 'Privacy disclamer', 'wordpress-setefi' ),
		'wp_setefi_privacy_disclamer_render',
		'wp_setefi_options-group',
		'wp_setefi_main_section'
	);

	add_settings_field(
		'wp_setefi_js_css',
		__( 'Load frontend JS/CSS', 'wordpress-setefi' ),
		'wp_setefi_js_css_render',
		'wp_setefi_options-group',
		'wp_setefi_main_section'
	);


}


function wp_setefi_terminal_id_render(  ) {

	$options = get_option( 'wp_setefi_settings' );
	$value = isset($options['wp_setefi_terminal_id']) ? $options['wp_setefi_terminal_id'] : '';
	?>
	<input type='text' name='wp_setefi_settings[wp_setefi_terminal_id]' value='<?php echo $value; ?>'>
	<?php

}


function wp_setefi_terminal_password_render(  ) {

	$options = get_option( 'wp_setefi_settings' );
	$value = isset($options['wp_setefi_terminal_password']) ? $options['wp_setefi_terminal_password'] : '';
	?>
	<input type='text' name='wp_setefi_settings[wp_setefi_terminal_password]' value='<?php echo $value; ?>'>
	<?php

}

function wp_setefi_paypal_email_render(  ) {

	$options = get_option( 'wp_setefi_settings' );
	$value = isset($options['wp_setefi_paypal_email']) ? $options['wp_setefi_paypal_email'] : '';
	?>
	<input type='text' name='wp_setefi_settings[wp_setefi_paypal_email]' value='<?php echo $value; ?>'>
	<?php

}

function wp_setefi_sandbox_render(  ) {

	$options = get_option( 'wp_setefi_settings' );
	$value = isset($options['wp_setefi_sandbox']) ? $options['wp_setefi_sandbox'] : 0;
	?>
	<input type='checkbox' name='wp_setefi_settings[wp_setefi_sandbox]' <?php checked( $value, 1 ); ?> value='1'>
	<?php

}

function wp_setefi_debug_render(  ) {

	$options = get_option( 'wp_setefi_settings' );
	$value = isset($options['wp_setefi_debug']) ? $options['wp_setefi_debug'] : 0;
	?>
	<input type='checkbox' name='wp_setefi_settings[wp_setefi_debug]' <?php checked( $value, 1 ); ?> value='1'>
	<?php

}


function wp_setefi_language_render(  ) {

	$options = get_option( 'wp_setefi_settings' );
	$value = isset($options['wp_setefi_language']) ? $options['wp_setefi_language'] : '';
	?>
	<select name='wp_setefi_settings[wp_setefi_language]'>
		<option value='ITA' <?php selected( $value, 'ITA' ); ?>><?php echo __( 'Italian', 'wordpress-setefi' ) ?></option>
		<option value='USA' <?php selected( $value, 'USA' ); ?>><?php echo __( 'English', 'wordpress-setefi' ) ?></option>
    <option value='FRA' <?php selected( $value, 'FRA' ); ?>><?php echo __( 'French', 'wordpress-setefi' ) ?></option>
    <option value='DEU' <?php selected( $value, 'DEU' ); ?>><?php echo __( 'German', 'wordpress-setefi' ) ?></option>
		<option value='SPA' <?php selected( $value, 'SPA' ); ?>><?php echo __( 'Spanish', 'wordpress-setefi' ) ?></option>
		<option value='RUS' <?php selected( $value, 'RUS' ); ?>><?php echo __( 'Russian', 'wordpress-setefi' ) ?></option>
		<option value='POR' <?php selected( $value, 'POR' ); ?>><?php echo __( 'Portuguese', 'wordpress-setefi' ) ?></option>
	</select>

<?php

}

function wp_setefi_js_css_render(  ) {

	$options = get_option( 'wp_setefi_settings' );
	$value = isset($options['wp_setefi_js_css']) ? $options['wp_setefi_js_css'] : 0;
	?>
	<input type='checkbox' name='wp_setefi_settings[wp_setefi_js_css]' <?php checked( $value, 1 ); ?> value='1'>
	<?php

}

function wp_setefi_privacy_disclamer_render () {

	$options = get_option( 'wp_setefi_settings' );
	$value = isset($options['wp_setefi_privacy_disclamer']) ? $options['wp_setefi_privacy_disclamer'] : '';
	?>


    <select name="wp_setefi_settings[wp_setefi_privacy_disclamer]">
    <option selected="selected" value=""><?php echo esc_attr( __( 'Select page' ) ); ?></option>
    <?php
        $selected_page = $value;
        $pages = get_pages();
        foreach ( $pages as $page ) {
			$option = '<option value="' . $page->ID . '" ' . selected( $selected_page, $page->ID ) . '>';
            $option .= $page->post_title;
            $option .= '</option>';
            echo $option;
        }
    ?>
</select>
    <!--<input type="text" name="wp_setefi_settings[wp_setefi_privacy_disclamer]" value="<?php //echo $value; ?>" placeholder="esempio: http://miosito.com/privacy" class="regular-text">-->
	<?php

}


function wp_setefi_settings_section_callback(  ) {

	echo __( 'Enter your settings below:', 'wordpress-setefi' );

}

function wp_setefi_options_page(  ) {

	?>
    <div class="wrap">
    	<h2>Wordpress Monetaweb Setefi</h2>
		<form action='options.php' method='post'>

		<?php
		settings_fields( 'wp_setefi_options-group' );
		do_settings_sections( 'wp_setefi_options-group' );
		submit_button();
		?>

		</form>

        <h3><?php echo __( 'Quick start', 'wordpress-setefi' ); ?></h3>
        <p>Shortocode:</p>
        <pre>// simple
[wp_setefi_payment_form]

// preset fields with params
[wp_setefi_payment_form amount="100" first_name="Giuseppe" last_name="Rossi" email="buyer@website.com"]

// multiple preset amounts
[wp_setefi_payment_form amount_options='20, 50, 100']

// User must login to make a payment
[wp_setefi_payment_form amount="150" use_login=1]</pre>
    </div>
    <p><a href="http://filippoburatti.net/wordpress-monetaweb-setefi/" target="_blank"><?php echo __( 'Usage & documentation', 'wordpress-setefi' ); ?></a></p>

   <?php  if (WP_SETEFI_DEBUG) {?>
    <hr>

        <h3>Wordpress Setefi Debug Log</h3>

                <?php


                $real_file = WP_SETEFI_DEBUG_LOG_PATH;

				//$clearlog_link = admin_url( "options-general.php?page=wp_setefi&amp;clearlog=1" );

				$clearlog_link = wp_nonce_url( admin_url( 'admin-post.php?action=wp-setefi-log-purge' ),'wp-setefi-log-purge' );

                $content = file_get_contents($real_file);
                $content = esc_textarea($content);
                ?>
                <div id="template"><p><a class="button" href="<?php echo $clearlog_link; ?>">Clear log</a></p><textarea cols="70" rows="25" name="wp_setefi_log" id="wp_setefi_log"><?php echo $content; ?></textarea></div>
	<?php

}


}

//  REGISTER CUSTOM POST TYPE -> PAYMENTS

function wp_setefi_custom_post_type() {

	$labels = array(
		'name'                => __( 'Payments', 'wordpress-setefi' ),
		'singular_name'       => __( 'Payment', 'wordpress-setefi' ),
		"add_new_item" 		  => __( 'Add new payment', 'wordpress-setefi' ),
		"edit_item" 		  => __( 'Edit payment', 'wordpress-setefi' ),
		"view_item" 		  => __( 'View payment', 'wordpress-setefi' ),
		"search_items" 		  => __( 'Search payments', 'wordpress-setefi' ),
	);
	$rewrite = array(
		'slug'                => 'payment',
		'with_front'          => true,
		'pages'               => false,
		'feeds'               => false
	);
	$args = array(
		'label'               => __( 'Payment', 'wordpress-setefi' ),
		'labels'              => $labels,
		'supports'            => array( 'title', 'custom-fields'),
		'hierarchical'        => false,
		'public'              => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'menu_position'       => 5,
		'show_in_admin_bar'   => false,
		'show_in_nav_menus'   => false,
		'can_export'          => true,
		'has_archive'         => false,
		'exclude_from_search' => false,
		'publicly_queryable'  => true,
		'rewrite'             => $rewrite,
		'capability_type'     => 'post'
	);
	register_post_type( 'payment', $args );

}
add_action( 'init', 'wp_setefi_custom_post_type' );


/* BACK END - Payment metabox & custom fields */

function wp_setefi_add_payment_metabox() {
		add_meta_box(
			'payment-fields',
			__( 'Payment data', 'wordpress-setefi' ),
			'wp_setefi_display_metabox',
			'payment',
			'advanced',
			'core'
		);
	}
add_action( 'add_meta_boxes', 'wp_setefi_add_payment_metabox' );

function wp_setefi_display_metabox( $post ) {

	wp_nonce_field( 'wp_setefi_save_meta_box_data', 'wp_setefi_meta_box_nonce' );

	$first_name 			= get_post_meta( $post->ID, '_wp_setefi_payment_first_name', true );
	$last_name 				= get_post_meta( $post->ID, '_wp_setefi_payment_last_name', true );

	//$tax_code				= get_post_meta( $post->ID, '_wp_setefi_payment_tax_code', true );
	//$company_name 		= get_post_meta( $post->ID, '_wp_setefi_payment_company_name', true );
	//$vat_number 			= get_post_meta( $post->ID, '_wp_setefi_payment_vat_number', true );
	//$fiscal_information 	= get_post_meta( $post->ID, '_wp_setefi_payment_fiscal_information', true );

	$email  				= get_post_meta( $post->ID, '_wp_setefi_payment_email', true );
	//$phone_number  		= get_post_meta( $post->ID, '_wp_setefi_payment_phone_number', true );

	//$address				= get_post_meta( $post->ID, '_wp_setefi_payment_address', true );
	//$zip_code				= get_post_meta( $post->ID, '_wp_setefi_payment_zip_code', true );
	//$city					= get_post_meta( $post->ID, '_wp_setefi_payment_city', true );

	$result 				= get_post_meta( $post->ID, '_wp_setefi_payment_result', true );
	$amount 				= get_post_meta( $post->ID, '_wp_setefi_payment_amount', true );
	$paymentID 				= get_post_meta( $post->ID, '_wp_setefi_payment_id', true );
	$response_code 			= get_post_meta( $post->ID, '_wp_setefi_payment_response_code', true );

	$payment_method		= get_post_meta( $post->ID, '_wp_setefi_payment_method', true );


	$securitytoken			= get_post_meta( $post->ID, '_wp_setefi_payment_securitytoken', true );


	echo '<p>';
	echo '<label for="wp_setefi_payment_first_name">';
	_e( 'First name', 'wordpress-setefi' );
	echo '</label> ';
	echo '<input type="text" id="wp_setefi_payment_first_name" name="wp_setefi_payment_first_name" value="' . esc_attr( $first_name ) . '" class="widefat" />';
	echo '</p>';

	echo '<p>';
	echo '<label for="wp_setefi_payment_last_name">';
	_e( 'Last name', 'wordpress-setefi' );
	echo '</label> ';
	echo '<input type="text" id="wp_setefi_payment_last_name" name="wp_setefi_payment_last_name" value="' . esc_attr( $last_name ) . '" class="widefat" />';
	echo '</p>';

	/*echo '<p>';
	echo '<label for="wp_setefi_payment_tax_code">';
	_e( 'Personal tax code', 'wordpress-setefi' );
	echo '</label> ';
	echo '<input type="text" id="wp_setefi_payment_tax_code" name="wp_setefi_payment_tax_code" value="' . esc_attr( $tax_code ) . '" class="widefat" />';
	echo '</p>';

	echo '<p>';
	echo '<label for="wp_setefi_payment_company_name">';
	_e( 'Company name', 'wordpress-setefi' );
	echo '</label> ';
	echo '<input type="text" id="wp_setefi_payment_company_name" name="wp_setefi_payment_company_name" value="' . esc_attr( $company_name ) . '" class="widefat" />';
	echo '</p>';

	echo '<p>';
	echo '<label for="wp_setefi_payment_vat_number">';
	_e( 'Vat number', 'wordpress-setefi' );
	echo '</label> ';
	echo '<input type="text" id="wp_setefi_payment_vat_number" name="wp_setefi_payment_vat_number" value="' . esc_attr( $vat_number ) . '" class="widefat" />';
	echo '</p>';

	echo '<p>';
	echo '<label for="wp_setefi_payment_fiscal_information">';
	_e( 'Fiscal information', 'wordpress-setefi' );
	echo '</label> ';
	echo '<input type="text" id="wp_setefi_payment_fiscal_information" name="wp_setefi_payment_fiscal_information" value="' . esc_attr( $fiscal_information ) . '" class="widefat" />';
	echo '</p>';*/

	echo '<p>';
	echo '<label for="wp_setefi_payment_email">';
	_e( 'Email', 'wordpress-setefi' );
	echo '</label> ';
	echo '<input type="text" id="wp_setefi_payment_email" name="wp_setefi_payment_email" value="' . esc_attr( $email ) . '" class="widefat" />';
	echo '</p>';

	/*echo '<p>';
	echo '<label for="wp_setefi_payment_phone_number">';
	_e( 'Phone number', 'wordpress-setefi' );
	echo '</label> ';
	echo '<input type="text" id="wp_setefi_payment_phone_number" name="wp_setefi_payment_phone_number" value="' . esc_attr( $phone_number ) . '" class="widefat" />';
	echo '</p>';

	echo '<p>';
	echo '<label for="wp_setefi_payment_address">';
	_e( 'Address', 'wordpress-setefi' );
	echo '</label> ';
	echo '<input type="text" id="wp_setefi_payment_address" name="wp_setefi_payment_address" value="' . esc_attr( $address ) . '" class="widefat" />';
	echo '</p>';

	echo '<p>';
	echo '<label for="wp_setefi_payment_city">';
	_e( 'City', 'wordpress-setefi' );
	echo '</label> ';
	echo '<input type="text" id="wp_setefi_payment_city" name="wp_setefi_payment_city" value="' . esc_attr( $city ) . '" class="widefat" />';
	echo '</p>';

	echo '<p>';
	echo '<label for="wp_setefi_payment_zip_code">';
	_e( 'Zip code', 'wordpress-setefi' );
	echo '</label> ';
	echo '<input type="text" id="wp_setefi_payment_zip_code" name="wp_setefi_payment_zip_code" value="' . esc_attr( $zip_code ) . '" class="widefat" />';
	echo '</p>';
	*/

	echo '<p>';
	echo '<label for="wp_setefi_payment_method">';
	_e( 'Payment Method', 'wordpress-setefi' );
	echo '</label> ';
	echo '<select id="wp_setefi_payment_method" name="wp_setefi_payment_method" class="widefat">';
	echo '<option value="">'.__( 'None', 'wordpress-setefi' ).'</option>';
	echo '<option value="monetaweb" '.selected( $payment_method, 'monetaweb', false ).'>'.__( 'Monetaweb Setefi', 'wordpress-setefi' ).'</option>';
	echo '<option value="paypal" '.selected( $payment_method, 'paypal', false ).'>'.__( 'Paypal', 'wordpress-setefi' ).'</option>';
	echo '</select>';
	echo '</p>';

	echo '<p>';
	echo '<label for="wp_setefi_payment_amount">';
	_e( 'Amount', 'wordpress-setefi' );
	echo '</label> ';
	echo '<input type="text" id="wp_setefi_payment_amount" name="wp_setefi_payment_amount" value="' . esc_attr( $amount ) . '" class="widefat" />';
	echo '</p>';

	echo '<p>';
	echo '<label for="wp_setefi_payment_result">';
	_e( 'Result', 'wordpress-setefi' );
	echo '</label> ';
	echo '<input type="text" id="wp_setefi_payment_result" name="wp_setefi_payment_result" value="' . esc_attr( $result ) . '" class="widefat" readonly="readonly"> />';
	echo '</p>';

	echo '<p>';
	echo '<label for="wp_setefi_payment_id">';
	_e( 'Payment ID', 'wordpress-setefi' );
	echo '</label> ';
	echo '<input type="text" id="wp_setefi_payment_id" name="wp_setefi_payment_id" value="' . esc_attr( $paymentID ) . '" class="widefat" readonly="readonly"> />';
	echo '</p>';

	echo '<p>';
	echo '<label for="wp_setefi_payment_response_code">';
	_e( 'Response code', 'wordpress-setefi' );
	echo '</label> ';
	echo '<input type="text" id="wp_setefi_payment_response_code" name="wp_setefi_payment_response_code" value="' . esc_attr( $response_code ) . '" class="widefat" readonly="readonly"> />';
	echo '</p>';


	echo '<p>';
	echo '<label for="wp_setefi_payment_securitytoken">';
	_e( 'Security token', 'wordpress-setefi' );
	echo '</label> ';
	echo '<input type="text" id="wp_setefi_payment_securitytoken" name="wp_setefi_payment_securitytoken" value="' . esc_attr( $securitytoken ) . '" class="widefat" readonly />';
	echo '</p>';

}


function wp_setefi_save_meta_box_data( $post_id ) {

	if ( ! isset( $_POST['wp_setefi_meta_box_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( $_POST['wp_setefi_meta_box_nonce'], 'wp_setefi_save_meta_box_data' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}



	$first_name 			= isset( $_POST['wp_setefi_payment_first_name'] ) ? sanitize_text_field( $_POST['wp_setefi_payment_first_name'] ) : '';
	$last_name 				= isset( $_POST['wp_setefi_payment_last_name'] ) ? sanitize_text_field( $_POST['wp_setefi_payment_last_name'] ) : '';

	//$tax_code 			= isset( $_POST['wp_setefi_payment_tax_code'] ) ? sanitize_text_field( $_POST['wp_setefi_payment_tax_code'] ) : '';
	//$company_name 		= isset( $_POST['wp_setefi_payment_company_name'] ) ? sanitize_text_field( $_POST['wp_setefi_payment_company_name'] ) : '';
	//$vat_number 			= isset( $_POST['wp_setefi_payment_vat_number'] ) ? sanitize_text_field( $_POST['wp_setefi_payment_vat_number'] ) : '';
	//$fiscal_information 	= isset( $_POST['wp_setefi_payment_fiscal_information']) ? sanitize_text_field( $_POST['wp_setefi_payment_fiscal_information'] ) : '';

	$email  				= isset( $_POST['wp_setefi_payment_email'] ) ? sanitize_email( $_POST['wp_setefi_payment_email'] ) : '';
	//$phone_number 		= isset( $_POST['wp_setefi_payment_phone_number'] ) ? sanitize_text_field( $_POST['wp_setefi_payment_phone_number'] ) : '';

	//$address 				= isset( $_POST['wp_setefi_payment_address'] ) ? sanitize_text_field( $_POST['wp_setefi_payment_address'] ) : '';
	//$city 					= isset( $_POST['wp_setefi_payment_city'] ) ? sanitize_text_field( $_POST['wp_setefi_payment_city'] ) : '';
	//$zip_code 				= isset( $_POST['wp_setefi_payment_zip_code'] ) ? sanitize_text_field( $_POST['wp_setefi_payment_zip_code'] ) : '';


	$payment_method		= isset( $_POST['wp_setefi_payment_method'] ) ? sanitize_text_field( $_POST['wp_setefi_payment_method'] ) : '';

	$result 				= isset( $_POST['wp_setefi_payment_result'] ) ? sanitize_text_field( $_POST['wp_setefi_payment_result'] ) : '';
	$amount 				= isset( $_POST['wp_setefi_payment_amount'] ) ? sanitize_text_field( $_POST['wp_setefi_payment_amount'] ) : '';
	$paymentID 				= isset( $_POST['wp_setefi_payment_id'] ) ? sanitize_text_field( $_POST['wp_setefi_payment_id'] ) : '';
	$response_code 			= isset( $_POST['wp_setefi_payment_response_code'] ) ? sanitize_text_field( $_POST['wp_setefi_payment_response_code'] ) : '';
	$securitytoken 			= isset( $_POST['wp_setefi_payment_securitytoken'] ) ? sanitize_text_field( $_POST['wp_setefi_payment_securitytoken'] ) : '';

	$custom = array(
			"_wp_setefi_payment_id"						=> $paymentID,
			"_wp_setefi_payment_response_code"			=> $response_code,
			"_wp_setefi_payment_securitytoken" 			=> $securitytoken,
			"_wp_setefi_payment_result"					=> $result,
			"_wp_setefi_payment_method"			=> $payment_method,
			"_wp_setefi_payment_amount"					=> number_format(preg_replace('/[^\d.]/', '.', $amount), 2, '.', ''),
			"_wp_setefi_payment_first_name"				=> $first_name,
			"_wp_setefi_payment_last_name"				=> $last_name,
			"_wp_setefi_payment_email"					=> $email,
			//"_wp_setefi_payment_address"				=> $address,
			//"_wp_setefi_payment_city"					=> $city,
			//"_wp_setefi_payment_zip_code"				=> $zip_code,
			//"_wp_setefi_payment_tax_code"				=> $tax_code,
			//"_wp_setefi_payment_fiscal_information"	=> $fiscal_information,
			//"_wp_setefi_payment_company_name"			=> $company_name,
			//"_wp_setefi_payment_vat_number"			=> $vat_number,
			//"_wp_setefi_payment_phone_number"			=> $phone_number,
		);

		foreach ($custom as $key=>$val) {
			update_post_meta($post_id, $key, $val);
		}


}
add_action( 'save_post', 'wp_setefi_save_meta_box_data' );

add_filter( 'manage_payment_posts_columns', 'my_edit_payment_columns' ) ;

function my_edit_payment_columns( $columns ) {

	$columns = array(
		'cb' => '<input type="checkbox" />',
		'title' => __( 'Description', 'wordpress-setefi' ),
		'full_name' => __( 'Name', 'wordpress-setefi' ),
		'payment_method' => __( 'Payment Method', 'wordpress-setefi' ),
		'amount' => __( 'Amount', 'wordpress-setefi' ),
		'result' => __( 'Result', 'wordpress-setefi' ),
		'date' => __( 'Date', 'wordpress-setefi' )
	);

	return $columns;
}

add_action( 'manage_payment_posts_custom_column', 'my_manage_payment_columns', 10, 2 );

function my_manage_payment_columns( $column, $post_id ) {
	global $post;

	switch( $column ) {

		case 'full_name' :

			$full_name = get_post_meta( $post->ID, '_wp_setefi_payment_first_name', true )." ".get_post_meta( $post->ID, '_wp_setefi_payment_last_name', true );

			if ( empty( $full_name ) )
				echo "&mdash;";
			else
				echo $full_name;

			break;

		case 'payment_method' :

				$payment_method = get_post_meta( $post->ID, '_wp_setefi_payment_method', true );

				if ( empty( $payment_method ) )
					echo "&mdash;";
				else
					echo $payment_method;

				break;

		case 'amount' :

			$amount = get_post_meta( $post->ID, '_wp_setefi_payment_amount', true );

			if ( empty( $amount ) )
				echo "&mdash;";

			else
				echo $amount;

			break;

		case 'result' :

			$result = get_post_meta( $post->ID, '_wp_setefi_payment_result', true );

			if ( empty( $result ) )
				echo "&mdash;";

			else
				echo $result;

			break;

		default :
			break;
	}
}


// FRONT END

function wp_setefi_payment_form_display($atts='') {

	if (is_admin()) { return; }

	global $post;

	$options 				= get_option( 'wp_setefi_settings' );

	if ($post->post_type == 'payment') {

		$payment_description = $post->post_title;

		$first_name 		= get_post_meta( $post->ID, '_wp_setefi_payment_first_name', true );
		$last_name 			= get_post_meta( $post->ID, '_wp_setefi_payment_last_name', true );
		$email  			= get_post_meta( $post->ID, '_wp_setefi_payment_email', true );

		//$phone_number  	= get_post_meta( $post->ID, '_wp_setefi_payment_phone_number', true );
		//$tax_code			= get_post_meta( $post->ID, '_wp_setefi_payment_tax_code', true );
		//$company_name 	= get_post_meta( $post->ID, '_wp_setefi_payment_company_name', true );
		//$vat_number 		= get_post_meta( $post->ID, '_wp_setefi_payment_vat_number', true );
		//$fiscal_information = get_post_meta( $post->ID, '_wp_setefi_payment_fiscal_information', true );

		// $address			= get_post_meta( $post->ID, '_wp_setefi_payment_address', true );
		// $zip_code			= get_post_meta( $post->ID, '_wp_setefi_payment_zip_code', true );
		// $city				= get_post_meta( $post->ID, '_wp_setefi_payment_city', true );

		$amount 			= get_post_meta( $post->ID, '_wp_setefi_payment_amount', true );

		$post_id 			= $post->ID;

	}
	else {

		extract( shortcode_atts( array(
					'first_name' => '',
					'last_name' => '',
					'email' => '',
					'payment_description' => '',
					/*'tax_code' => '',
					'company_name' => '',
					'vat_number' => '',
					'fiscal_information' => '',*/
					// 'address' => '',
					// 'city' => '',
					// 'zip_code' => '',
					'amount' => '',
					'amount_options' => '',
					'use_login' => 0
				), $atts
			)
		);
        
        //var_dump ($atts);
        
       /* ====== ORIGINALE =====
		$payment_description 	= isset($_GET['payment_description']) ? sanitize_text_field($_GET['payment_description']) : 'gfhfghf';
		$first_name 					= isset($_GET['first_name']) ? sanitize_text_field($_GET['first_name']) : '';
		$last_name 						= isset($_GET['last_name']) ? sanitize_text_field($_GET['last_name']) : '';
		$email 								= isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
		$amount 							= isset($_GET['amount']) ? sanitize_text_field($_GET['amount']) : '';	
        */
        
        
        $payment_description 	= isset($_GET['payment_description']) ? sanitize_text_field($_GET['payment_description']) : $atts['payment_description'];
		$first_name 					= isset($_GET['first_name']) ? sanitize_text_field($_GET['first_name']) : $atts['first_name'];
		$last_name 						= isset($_GET['last_name']) ? sanitize_text_field($_GET['last_name']) : $atts['last_name'];
		$email 								= isset($_GET['email']) ? sanitize_email($_GET['email']) : $atts['email'];
		$amount 							= isset($_GET['amount']) ? sanitize_text_field($_GET['amount']) : $atts['amount'];
    }

	if ($use_login) {
		if(is_user_logged_in()) {
			global $current_user;
      		get_currentuserinfo();
			$email 			= $current_user->user_email;
			$first_name 	= $current_user->user_firstname;
			$last_name 		= $current_user->user_lastname;
		} else {

			return apply_filters('wp_setefi_payment_form_use_login_message', '<div class="well">'. __('You must login to make a payment.', 'wordpress-setefi') .'</div>');

		}

	}

	$payment_method='';

	ob_start();

?>

<?php do_action( 'wp_setefi_before_payment_form' ); ?>

<form action="<?php the_permalink($post->ID); ?>" method="post" class="<?php echo wp_setefi_join_class( apply_filters('wp_setefi_payment_form_class', array("wp-setefi-form") ) ); ?>" role="form" id="form-setefi">

    <?php //echo apply_filters('wp_setefi_payment_form_legend', "<legend>". __('Payment data', 'wordpress-setefi') . "</legend>"); ?>

	<?php wp_nonce_field('wp_setefi_payment_form_nonce_action','wp_setefi_payment_form_nonce_field'); ?>

    <?php
	$amount = number_format(preg_replace('/[^\d.]/', '.', (float)$amount), 2, '.', '');
	if (is_numeric($amount) && $amount!=0) { ?>
    	<p><strong><?php echo __( 'Amount', 'wordpress-setefi' ).": &euro; ".$amount; ?></strong></p>
    	<input type="hidden" name="amount" id="amount" value="<?php echo $amount; ?>" />
    <?php } else { ?>

		<?php
		if(isset($amount_options) && $amount_options!=''){

			$array_amounts = explode(",", $amount_options);

			echo "<div class=\"form-group-full-w\">";

			echo "<label>".__( 'Choose the amount', 'wordpress-setefi' )."</label>";

			foreach($array_amounts as $item_amount) {

				echo "<a class=\"btn btn-sm btn-default amount_option\" href=\"#\" data-amount=\"".trim($item_amount)."\">&euro;".trim($item_amount)."</a> ";

			}

			echo "<a class=\"btn btn-sm btn-default amount_option\" href=\"#\" data-amount=\"\">".__( 'Free choice', 'wordpress-setefi' )."</a>";

			echo "</div>";

		}
		?>

		<div class="form-group-full-w">
        <label for="amount"><?php echo __( 'Amount', 'wordpress-setefi' ); ?></label>
        <input type="text" name="amount" id="amount" class="<?php echo wp_setefi_join_class( apply_filters('wp_setefi_payment_form_field_class', array("required", "number", "form-control") ) ); ?>" />
    </div>
    <?php } // AMOUNT ?>

		<div class="form-group-full-w">
        <label for="payment_description"><?php echo __( 'Payment Description', 'wordpress-setefi' ); ?></label>
        <input type="text" name="payment_description" id="payment_description" value="<?php echo $payment_description; ?>" class="<?php echo wp_setefi_join_class( apply_filters('wp_setefi_payment_form_field_class', array("required", "form-control") ) ); ?>" <?php if ($payment_description!='') { echo "readonly='readonly'"; } ?> />
    </div>


    <div class="form-group">
        <label for="first_name"><?php echo __( 'First name', 'wordpress-setefi' ); ?></label>
        <input type="text" name="first_name" id="first_name" value="<?php echo $first_name; ?>" class="<?php echo wp_setefi_join_class( apply_filters('wp_setefi_payment_form_field_class', array("required", "form-control") ) ); ?>"  <?php if ($first_name!='') { echo "readonly"; } ?> />
    </div>

    <div class="form-group">
        <label for="last_name"><?php echo __( 'Last name', 'wordpress-setefi' ); ?></label>
        <input type="text" name="last_name" id="last_name" value="<?php echo $last_name; ?>" class="<?php echo wp_setefi_join_class( apply_filters('wp_setefi_payment_form_field_class', array("required", "form-control") ) ); ?>"  <?php if ($last_name!='') { echo "readonly"; } ?> />
    </div>

    <div class="form-group">
        <label for="email"><?php echo __( 'Email', 'wordpress-setefi' ); ?></label>
        <input type="email" name="email" id="email" value="<?php echo $email; ?>" class="<?php echo wp_setefi_join_class( apply_filters('wp_setefi_payment_form_field_class', array("required", "email", "form-control") ) ); ?>"  <?php if ($email!='') { echo "readonly"; } ?> />
    </div>

		<div class="form-group">
			 <label for="payment_method"><?php echo __( 'Payment Method', 'wordpress-setefi' ); ?></label>
			 <select name="payment_method" id="payment_method" class="<?php echo wp_setefi_join_class( apply_filters('wp_setefi_payment_form_field_class', array("required", "form-control") ) ); ?>">
				 <option value="monetaweb" <?php selected( $payment_method, 'monetaweb'); ?>><?php _e( 'Credit Card (Monetaweb)', 'wordpress-setefi' ); ?></option>
		 <!-- <option value="paypal" <?php //selected( $payment_method, 'paypal'); ?>><?php //_e( 'Paypal', 'wordpress-setefi' ); ?></option> -->
			 </select>
	 </div>

 	<?php

	$options = get_option( 'wp_setefi_settings' );

	$privacy_disclamer = $options['wp_setefi_privacy_disclamer'];

	if ($privacy_disclamer!='') {

	?>

    <div class="form-group-full-w">
        <label for="privacy" class="privacy">
            <input type="checkbox" name="privacy" id="privacy" value="1" class="required" />
            <?php echo __( 'I authorize the treatment of my personal data ', 'wordpress-setefi' )." (<a href=\"".get_permalink($privacy_disclamer)."\" target=\"_blank\">".__( 'Read more', 'wordpress-setefi' )."</a>)"; ?>
        </label>
    </div>

    <?php } ?>

    <div class="form-group-full-w">
        <input type="hidden" name="mrcnt_id" value="<?php echo $post->ID; ?>" />
        <?php echo apply_filters('wp_setefi_payment_form_button', '<input type="submit" value="'. __( 'Pay now', 'wordpress-setefi' ) .'" class="btn btn-custom btn-submit" />'); ?>
    </div>


</form>
<?php

	return ob_get_clean();

}

add_shortcode('wp_setefi_payment_form', 'wp_setefi_payment_form_display');

function wp_setefi_payment_form_handler(){

	if( is_admin() ) {
		return;
	}
	//
//	if ( true === DOING_CRON || true === DOING_AJAX ) {
//    	return;
//	}

	if ( ! isset( $_POST['wp_setefi_payment_form_nonce_field'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( $_POST['wp_setefi_payment_form_nonce_field'], 'wp_setefi_payment_form_nonce_action' ) ) {
		return;
	}


	global $form_error;

  $form_error = new WP_Error;

	$frm_payment_description = isset($_POST['payment_description']) ? sanitize_text_field($_POST['payment_description']) : apply_filters( 'wp_setefi_description', __('Payment ref.', 'wordpress-setefi').' '.current_time( 'mysql' ) );


	$frm_first_name 			= isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
	$frm_last_name 				= isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
	$frm_email 					= isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

	//$frm_tax_code	 			= isset($_POST['tax_code']) ? sanitize_text_field($_POST['tax_code']) : '';
	//$frm_company_name 		= isset($_POST['company_name']) ? sanitize_text_field($_POST['company_name']) : '';
	//$frm_vat_number 			= isset($_POST['vat_number']) ? sanitize_text_field($_POST['vat_number']) : '';
	//$frm_fiscal_information 	= isset($_POST['fiscal_information']) ? sanitize_text_field($_POST['fiscal_information']) : '';
	//$frm_phone_number 		= isset($_POST['phone_number']) ? sanitize_text_field($_POST['phone_number']) : '';

	//$frm_address 				= isset($_POST['address']) ? sanitize_text_field($_POST['address']) : '';
	//$frm_city 					= isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
	//$frm_zip_code 				= isset($_POST['zip_code']) ? sanitize_text_field($_POST['zip_code']) : '';

	$frm_payment_method 	= isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : '';

	$frm_amount 				= isset($_POST['amount']) ? sanitize_text_field($_POST['amount']) : '';
	$frm_post_id 				= isset($_POST['mrcnt_id']) ? $_POST['mrcnt_id'] : 0;


	if ( empty( $frm_first_name ) || empty( $frm_last_name ) || empty( $frm_email ) || empty( $frm_payment_description ) || empty( $frm_payment_method )) {
        $form_error->add( 'field', __( 'No field should be left empty.', 'wordpress-setefi' ) );
    }

	if ( ! is_email( $frm_email ) ) {
        $form_error->add( 'email', __( 'Please enter a valid e-mail address.', 'wordpress-setefi' ) );
    }


	if ( ! is_numeric( $frm_amount ) || empty( $frm_amount ) ) {
        $form_error->add( 'email',  __( 'Please enter a valid amount.', 'wordpress-setefi' ) );
    }

	if ( 1 > count( $form_error->get_error_messages() ) ) {

		$payment = array();

		$payment['post_title'] 	= $frm_payment_description;


		if ( get_post_type($frm_post_id)!='payment' ) {

			$payment['post_type'] 	= 'payment';
			$payment['post_status'] = 'publish';

			$last_post_id = wp_insert_post( $payment );

		} else {

			$payment['ID'] 	= $frm_post_id;

			$last_post_id = wp_update_post( $payment );

		}

		$custom_fields = array(
			"_wp_setefi_payment_first_name"		=> $frm_first_name,
			"_wp_setefi_payment_last_name"		=> $frm_last_name,
			"_wp_setefi_payment_email"			=> $frm_email,
			//"_wp_setefi_payment_tax_code"		=> $frm_tax_code,
			//"_wp_setefi_payment_company_name"	=> $frm_company_name,
			//"_wp_setefi_payment_vat_number"		=> $frm_vat_number,
			//"_wp_setefi_payment_fiscal_information"			=> $frm_fiscal_information,
			//"_wp_setefi_payment_phone_number"	=> $frm_phone_number,
			// "_wp_setefi_payment_address"		=> $frm_address,
			// "_wp_setefi_payment_city"			=> $frm_city,
			// "_wp_setefi_payment_zip_code"		=> $frm_zip_code,
			"_wp_setefi_payment_method"			=> $frm_payment_method,
			"_wp_setefi_payment_amount"			=> $frm_amount,
			"_yoast_wpseo_meta-robots-noindex"  => '1',
			"_yoast_wpseo_meta-robots-nofollow" => '1'
		);


		foreach ($custom_fields as $key=>$val) {
			update_post_meta($last_post_id, $key, $val);
		}


		$options 			= get_option( 'wp_setefi_settings' );

		$sandbox 			= isset($options['wp_setefi_sandbox']) ? 1 : 0;

		$language 			= isset($options['wp_setefi_language']) ? $options['wp_setefi_language'] : 'ITA';



		// aggiunta per mantenere la querystring nell'url di ritorno in caso di errore
		$return_params = array(
			"payment_description" => $frm_payment_description,
			"first_name"		=> $frm_first_name,
			"last_name"		=> $frm_last_name,
			"email"			=> $frm_email,
			"amount"			=> $frm_amount
		);

		$errorurl 			= get_permalink($frm_post_id)."?".http_build_query($return_params);

		if ($frm_payment_method=='monetaweb') {

			$terminal_id 		= isset($options['wp_setefi_terminal_id']) ? $options['wp_setefi_terminal_id'] : '';
			$terminal_password 	= isset($options['wp_setefi_terminal_password']) ? $options['wp_setefi_terminal_password'] : '';

			$action				= "4"; // richiesta di autorizzazione
			$currencycode		= "978"; // EURO

			if ($sandbox) {
				$gateway 		= "https://test.monetaonline.it/monetaweb/payment/2/xml";
			} else {
				$gateway 		= "https://www.monetaonline.it/monetaweb/payment/2/xml";
			}
            
            

			$responseurl		= add_query_arg( 'ipn-request', 'wp_setefi', home_url( '/' ) );

			$parameters = array(
				  'id' 						=> $terminal_id,
				  'password'	 			=> $terminal_password,
				  'operationType' 			=> 'initialize',
				  'amount' 					=> number_format(preg_replace('/[^\d.]/', '.', $frm_amount), 2, '.', ''),
				  'currencyCode' 			=> apply_filters( 'wp_setefi_currency_code', $currencycode ),
				  'language' 				=> apply_filters( 'wp_setefi_hosted_page_language', $language ),
				  'responseToMerchantUrl' 	=> apply_filters( 'wp_setefi_response_url', $responseurl ),
				  'recoveryUrl' 			=> apply_filters( 'wp_setefi_error_url', $errorurl ),
				  'merchantOrderId' 		=> apply_filters( 'wp_setefi_merchant_order_id', $last_post_id ),
				  'cardHolderName' 			=> $frm_first_name." ".$frm_last_name,
				  'cardHolderEmail' => $frm_email,
				  'description' => $payment['post_title'],
				  'customField' => ''
			 );

			  // GDPR compliance
			  $log_params = $parameters;
			  if (function_exists('wp_privacy_anonymize_data')) {
				  $log_params['cardHolderName'] 	= wp_privacy_anonymize_data( 'text', $log_params['cardHolderName'] );
				  $log_params['cardHolderEmail'] 	= wp_privacy_anonymize_data( 'email', $log_params['cardHolderEmail'] );
			  } else {
				  unset($log_params['cardHolderName']);
				  unset($log_params['cardHolderEmail']);
			  }

			  wp_setefi_debug_log(__('Wordpress Setefi - Debug Initialize Payment', 'wordpress-setefi'), $log_params);

			  $curlHandle = curl_init();
			  curl_setopt($curlHandle, CURLOPT_URL, $gateway);

			  curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
			  curl_setopt($curlHandle, CURLOPT_POST, true);
			  curl_setopt($curlHandle, CURLOPT_POSTFIELDS, http_build_query($parameters));
			  $xmlResponse = curl_exec($curlHandle);
			  curl_close($curlHandle);

			  wp_setefi_debug_log(__('Wordpress Setefi - Debug Initialize Response', 'wordpress-setefi'), $xmlResponse);

			  $response = new SimpleXMLElement($xmlResponse);

				$paymentId = isset($response->paymentid) ? $response->paymentid : 0;
				$paymentUrl = isset($response->hostedpageurl) ? $response->hostedpageurl : 0;
				$securitytoken 	= isset($response->securitytoken) ? (string)$response->securitytoken : 0;


				if ($paymentId && $paymentUrl) {

					update_post_meta( $last_post_id, '_wp_setefi_payment_securitytoken', $securitytoken );

					$setefiPaymentPageUrl = "$paymentUrl?PaymentID=$paymentId";
			  		header("Location: $setefiPaymentPageUrl");

					exit();

				} else {

					$form_error->add( 'gateway',  __('Payment error: Sorry, an error occurred! We can\'t process your payment right now, so please try again later.', 'wordpress-setefi' ) );

					add_action('wp_setefi_before_payment_form', 'wp_setefi_payment_form_error', 1);

				}
		}
		else if ($frm_payment_method=='paypal') {

			$parameters 					= array();
			$parameters['cmd'] 				= '_xclick';
			//$parameters['cmd'] 			= '_donations';

    	$parameters['notify_url'] 		= add_query_arg( 'ipn-request', 'wp_setefi_paypal', home_url( '/' ) );
			$parameters['cancel_return'] 	= $errorurl;
			$parameters['return']			= get_permalink($last_post_id);;

			$parameters['currency_code']	= 'EUR';
			$parameters['lc']				= substr(apply_filters( 'wp_setefi_hosted_page_language', $language ), 0, -1);

    	$parameters['business'] 		= isset($options['wp_setefi_paypal_email']) ? $options['wp_setefi_paypal_email'] : '';
			$parameters['email'] 			= $frm_email;

			$parameters['item_number']		= $last_post_id;
    	$parameters['item_name'] 		= apply_filters( 'wp_setefi_description', $frm_payment_description );
			$parameters['amount'] 			= number_format(preg_replace('/[^\d.]/', '.', $frm_amount), 2, '.', '');

			// GDPR compliance
			$log_params = $parameters;
			if (function_exists('wp_privacy_anonymize_data')) {
				$log_params['email'] 	= wp_privacy_anonymize_data( 'email', $log_params['email'] );
			} else {
				unset($log_params['email']);
			}
			wp_setefi_debug_log(__('Wordpress Setefi - Debug Initialize Paypal Payment', 'wordpress-setefi'), $log_params);

    		$query_string = http_build_query($parameters);


			if ($sandbox) {
				$gateway 		= "https://www.sandbox.paypal.com/cgi-bin/webscr";
			} else {
				$gateway 		= "https://www.paypal.com/cgi-bin/webscr";
			}


			wp_redirect($gateway."?".$query_string);

			exit();


		}

  }
	else {

		add_action('wp_setefi_before_payment_form', 'wp_setefi_payment_form_error', 1);

	}

}

add_action( 'init', 'wp_setefi_payment_form_handler' );


function wp_setefi_payment_form_error() {

	global $form_error;

	if ( is_wp_error( $form_error ) ) {

		$errors = '';

        foreach ( $form_error->get_error_messages() as $error ) {

			$errors .= apply_filters( 'wc_setefi_payment_error', "<p>".$error."</p>\n" );

        }

		echo sprintf( apply_filters( 'wc_setefi_payment_errors', "<div class=\"alert alert-danger\"><a class=\"close\" data-dismiss=\"alert\" href=\"#\" aria-hidden=\"true\">&times;</a>\n%s</div>\n"), $errors);


    }


}


// IPN REQUEST : ENDPOINT RESPONSE

function wp_setefi_query_vars($vars) {
	array_push( $vars, 'ipn-request' );
  	return $vars;
}

add_filter('query_vars', 'wp_setefi_query_vars');

function wp_setefi_parse_request($wp) {

    if (array_key_exists('ipn-request', $wp->query_vars) && $wp->query_vars['ipn-request'] == 'wp_setefi') {

			wp_setefi_debug_log(__('Wordpress Setefi - Debug IPN Response', 'wordpress-setefi'), $_POST);

	   	if(isset($_POST["paymentid"]) && isset($_POST["responsecode"])){

				//ob_start();

				$paymentid 			= $_POST['paymentid'];
				$result 			= $_POST['result'];
				$merchant_order_id 	= $_POST['merchantorderid'];
				$responsecode 		= $_POST['responsecode'];
				$securitytoken  = $_POST['securitytoken'];
				//$cardtype			= isset($_POST['cardtype']) ? $_POST['cardtype'] : '';
				//$customField 		= $_POST["customfield"];

				$stored_token  	= get_post_meta( $merchant_order_id, '_wp_setefi_payment_securitytoken', true );

				$custom = array(
					"_wp_setefi_payment_id"			=> $paymentid,
					"_wp_setefi_payment_response_code"		=> $responsecode,
					"_wp_setefi_payment_result"			=> $result
				);


				foreach ($custom as $key=>$val) {
					update_post_meta($merchant_order_id, $key, $val);
				}

				if ($responsecode=="000" && $securitytoken == $stored_token) {
					do_action( 'wp_setefi_ipn_response', $custom, $merchant_order_id );
				} else {
					if ($securitytoken != $stored_token) {
						wp_setefi_debug_log(__('Wordpress Setefi - Security Token  doesn\'t match', 'wordpress-setefi'), $merchant_order_id);
					}

				}

				// AUTOMATIC REDIRECT TO THANK'YOU PAGE (single payment)
				echo get_permalink($merchant_order_id);

			   	//ob_end_clean();
				die();


			} else {

				die();

			}

    }
		else if (array_key_exists('ipn-request', $wp->query_vars) && $wp->query_vars['ipn-request'] == 'wp_setefi_paypal') {
			wp_setefi_debug_log(__('Wordpress Setefi - Debug Paypal IPN Response', 'wordpress-setefi'), $_POST);

	        if (!empty($_POST)) {

				$options 			= get_option( 'wp_setefi_settings' );
				$sandbox 			= isset($options['wp_setefi_sandbox']) ? 1 : 0;

				if ($sandbox) {
					$gateway 		= "https://www.sandbox.paypal.com/cgi-bin/webscr";
				} else {
					$gateway 		= "https://www.paypal.com/cgi-bin/webscr";
				}

				$validate_ipn = array('cmd' => '_notify-validate');
	        	$validate_ipn += stripslashes_deep($_POST);

				$params = array(
					'body' => $validate_ipn,
					'sslverify' => false,
					'timeout' => 60,
					'httpversion' => '1.1',
					'compress' => false,
					'decompress' => false,
					'user-agent' => 'Wordpress Setefi'
				);

			 	$response = wp_remote_post($gateway, $params);


				if (!is_wp_error($response) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 && strstr($response['body'], 'VERIFIED')) {

					//header( 'HTTP/1.1 200 OK' );
					wp_setefi_debug_log(__('Wordpress Setefi - Debug Paypal IPN Response', 'wordpress-setefi'), 'Received valid response from PayPal');

					$result 			= isset($_POST['payment_status']) ? sanitize_text_field($_POST['payment_status']) : '';

					$payment_id 		= isset($_POST['txn_id']) ? sanitize_text_field($_POST['txn_id']) : '';

					$merchant_order_id 	= isset($_POST['item_number']) ? (int)$_POST['item_number'] : 0;

					$responsecode		= ($result == 'Completed') ? '000' : '';

					$custom = array(
						"_wp_setefi_payment_id"				=> $payment_id,
						"_wp_setefi_payment_response_code"	=> $responsecode,
						"_wp_setefi_payment_result"			=> $result
					);


					foreach ($custom as $key=>$val) {
						update_post_meta($merchant_order_id, $key, $val);
					}

					do_action( 'wp_setefi_ipn_response', $custom, $merchant_order_id );

				} else {

					wp_setefi_debug_log(__('Wordpress Setefi - Paypal IPN Response Failure', 'wordpress-setefi'), $response);

				}

	        } else {

				wp_setefi_debug_log(__('Wordpress Setefi - Paypal IPN Response Failure', 'wordpress-setefi'), 'true');

				die();

			}

		}

}

add_action('parse_request', 'wp_setefi_parse_request');

add_action('wp_setefi_ipn_response','wp_setefi_payment_notify', 10, 2);

function wp_setefi_payment_notify( $custom, $merchant_order_id) {

		wp_setefi_debug_log(__('Wordpress Setefi - Debug Payment Notify', 'wordpress-setefi'), array('payment result'=>$custom, 'merchant_order_id'=>$merchant_order_id));

		// payment data
		$first_name 		= get_post_meta( $merchant_order_id, '_wp_setefi_payment_first_name', true );
		$last_name 			= get_post_meta( $merchant_order_id, '_wp_setefi_payment_last_name', true );

		$company_name 		= get_post_meta( $merchant_order_id, '_wp_setefi_payment_company_name', true );
		//$vat_number 		= get_post_meta( $merchant_order_id, '_wp_setefi_payment_vat_number', true );

		$email  			= get_post_meta( $merchant_order_id, '_wp_setefi_payment_email', true );
		//$phone_number  		= get_post_meta( $merchant_order_id, '_wp_setefi_payment_phone_number', true );

		$amount 			= get_post_meta( $merchant_order_id, '_wp_setefi_payment_amount', true );
		$reason_for_payment = get_the_title( $merchant_order_id );


		$email_message  = __( 'First name', 'wordpress-setefi' ).": ".$first_name."\n";
		$email_message .= __( 'Last name', 'wordpress-setefi' ).": ".$last_name."\n";
		//$email_message .= __( 'Company name', 'wordpress-setefi' ).": ".$company_name."\n";
		//$email_message .= __( 'Vat number', 'wordpress-setefi' ).": ".$vat_number."\n";
		$email_message .= __( 'Email', 'the-bootstrap' ).": ".$email."\n";
		//$email_message .= __( 'Phone number', 'wordpress-setefi' ).": ".$phone_number."\n";
		$email_message .= __( 'Amount', 'wordpress-setefi' ).": ".$amount."\n";
		$email_message .= __( 'Description', 'wordpress-setefi' ).": ".$reason_for_payment."\n";


		$store_email = get_option( 'admin_email' );

		//$store_email = "info@filippoburatti.net";


		// email to merchant
		$store_email_subject = wp_specialchars_decode(get_bloginfo('name') . " - " . __('Payment received', 'wordpress-setefi') );

        $headers  = "From: " . $first_name . " " . $last_name . " <" . $email . ">\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\n";
        $headers .= "Content-Transfer-Encoding: 8bit\n";

        wp_mail( array($store_email), $store_email_subject, $email_message, $headers );


		// email to buyer
		$buyer_message = __( 'Thank you. Your payment has been received.', 'wordpress-setefi' )."\n\n";

		$buyer_email_subject = wp_specialchars_decode(get_bloginfo('name') . " - " . __('Successful payment!', 'wordpress-setefi') );

		$headers1  = "From: " . wp_specialchars_decode(get_bloginfo( 'name' )) . " <" . $store_email . ">\n";
        $headers1 .= "Content-Type: text/plain; charset=UTF-8\n";
        $headers1 .= "Content-Transfer-Encoding: 8bit\n";

        wp_mail( $email, $buyer_email_subject, $buyer_message.$email_message, $headers1 );

}



// Custom template for single payment (post type)
add_filter('single_template','wp_setefi_single_payment_template');

function wp_setefi_single_payment_template($single_template){
  	global $post;
  	$found = locate_template('single-payment.php');
  	if($post->post_type == 'payment' && $found == ''){
    	$single_template = dirname(__FILE__).'/templates/single-payment.php';
  	}
  	return $single_template;
}


// HELPERS

function wp_setefi_join_class( $class = '' ) {
	$classes = join( ' ', $class );

	return $classes;
}


/* Exclude payments From Yoast SEO Sitemap && NOINDEX */
function sitemap_exclude_post_type( $value, $post_type ) {
	if ( $post_type == 'payment' ) return true;
}
add_filter( 'wpseo_sitemap_exclude_post_type', 'sitemap_exclude_post_type', 10, 2 );

function noindex_payment($robotsstr) {
	if(is_singular('payment')) { $robotsstr="noindex,nofollow"; }
	return $robotsstr;
}
add_filter( 'wpseo_robots', 'noindex_payment', 10, 1 );

// ADMIN SEARCH
add_filter('posts_join', 'payment_search_join' );
function payment_search_join ($join){
    global $pagenow, $wpdb;

	$post_type 	= isset($_GET['post_type']) ? $_GET['post_type'] : '';
	$search 	= isset($_GET['s']) ? $_GET['s'] : '';

    if ( is_admin() && $pagenow=='edit.php' && $post_type=='payment' && $search!='') {
        $join .='LEFT JOIN '.$wpdb->postmeta. ' ON '. $wpdb->posts . '.ID = ' . $wpdb->postmeta . '.post_id ';
    }
    return $join;
}

add_filter( 'posts_where', 'payment_search_where' );
function payment_search_where( $where ){
    global $pagenow, $wpdb;

	$post_type 	= isset($_GET['post_type']) ? $_GET['post_type'] : '';
	$search 	= isset($_GET['s']) ? $_GET['s'] : '';

    if ( is_admin() && $pagenow=='edit.php' && $post_type=='payment' && $search!='') {
        $where = preg_replace(
       "/\(\s*".$wpdb->posts.".post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
       "(".$wpdb->posts.".post_title LIKE $1) OR (".$wpdb->postmeta.".meta_value LIKE $1)", $where );
    }
    return $where;
}

function payment_search_distinct( $where ){
    global $pagenow, $wpdb;

	$post_type 	= isset($_GET['post_type']) ? $_GET['post_type'] : '';
	$search 	= isset($_GET['s']) ? $_GET['s'] : '';

    if ( is_admin() && $pagenow=='edit.php' && $post_type=='payment' && $search!='') {
    return "DISTINCT";

    }
    return $where;
}
add_filter( 'posts_distinct', 'payment_search_distinct' );


// GDPR COMPLIANCE

// EXPORT DATA
function wordpress_setefi_exporter( $email_address, $page = 1 ) {

  	$limit = 50;
  	$page = (int) $page;

  	$export_items = array();

	$args = array(
	  	'post_type'  => 'payment',
	  	'meta_key'   => '_wp_setefi_payment_email',
	  	'meta_value' => $email_address,
		'orderby' => 'ID',
		'order'   => 'ASC',
		'paged' => $page,
		'posts_per_page' => $limit
  	);

	$payments = new WP_Query( $args );

	foreach ( (array) $payments->posts as $post ) {

		$first_name 		= get_post_meta( $post->ID, '_wp_setefi_payment_first_name', true );
		$last_name 			= get_post_meta( $post->ID, '_wp_setefi_payment_last_name', true );
		$email  			= get_post_meta( $post->ID, '_wp_setefi_payment_email', true );

		//$phone_number  	= get_post_meta( $post->ID, '_wp_setefi_payment_phone_number', true );
		//$tax_code			= get_post_meta( $post->ID, '_wp_setefi_payment_tax_code', true );
		//$company_name 	= get_post_meta( $post->ID, '_wp_setefi_payment_company_name', true );
		//$vat_number 		= get_post_meta( $post->ID, '_wp_setefi_payment_vat_number', true );
		//$fiscal_information = get_post_meta( $post->ID, '_wp_setefi_payment_fiscal_information', true );

		// $address			= get_post_meta( $post->ID, '_wp_setefi_payment_address', true );
		// $zip_code			= get_post_meta( $post->ID, '_wp_setefi_payment_zip_code', true );
		// $city				= get_post_meta( $post->ID, '_wp_setefi_payment_city', true );

		$amount 			= get_post_meta( $post->ID, '_wp_setefi_payment_amount', true );
		$payment_id 		= get_post_meta( $post->ID, '_wp_setefi_payment_id', true);
		$result 			= get_post_meta($post->ID, '_wp_setefi_payment_result', true);

		$data = array(
			array(
			  'name' => __( 'First name', 'wordpress-setefi' ),
			  'value' => $first_name
			),
			array(
			  'name' => __( 'Last name', 'wordpress-setefi' ),
			  'value' => $last_name
			),
			 array(
			  'name' => __( 'Email', 'wordpress-setefi' ),
			  'value' => $email
			),
			//  array(
			//   'name' => __( 'Address', 'wordpress-setefi' ),
			//   'value' => $address
			// ),
			// array(
			//   'name' => __( 'City', 'wordpress-setefi' ),
			//   'value' => $city
			// ),
			//  array(
			//   'name' => __( 'Zip code', 'wordpress-setefi' ),
			//   'value' => $zip_code
			// ),
			 array(
			  'name' => __( 'Amount', 'wordpress-setefi' ),
			  'value' => $amount
			),
			 array(
			  'name' => __( 'Result', 'wordpress-setefi' ),
			  'value' => $result
			),
			 array(
			  'name' => __( 'Payment ID', 'wordpress-setefi' ),
			  'value' => $payment_id
			),
			 array(
			  'name' => __( 'Date', 'wordpress-setefi' ),
			  'value' => get_the_date('', $post->ID)
			)
      	);

		$export_items[] = array(
			'group_id' => 'payments',
			'group_label' => __( 'Payments', 'wordpress-setefi' ),
			'item_id' => "payment-".$post->ID,
			'data' => $data,
		);

  	}


	$done = count( $payments ) < $limit;
	//$done = $payments->max_num_pages <= $page;

	return array(
	  'data' => $export_items,
	  'done' => $done,
	);
}

function register_wordpress_setefi_exporter( $exporters ) {
  $exporters['wordpress-setefi'] = array(
    'exporter_friendly_name' => 'Wordpress Monetaweb Setefi',
    'callback' => 'wordpress_setefi_exporter',
  );
  return $exporters;
}

add_filter('wp_privacy_personal_data_exporters', 'register_wordpress_setefi_exporter', 10);

// ERASE DATA (ANONIMIZE)

function wordpress_setefi_eraser( $email_address, $page = 1 ) {

  	$limit = 50;
  	$page = (int) $page;

	$items_removed  = false;
	$items_retained = false;
  	$messages = array();

	$args = array(
	  	'post_type'  => 'payment',
	  	'meta_key'   => '_wp_setefi_payment_email',
	  	'meta_value' => $email_address,
		'orderby' => 'ID',
		'order'   => 'ASC',
		'paged' => $page,
		'posts_per_page' => $limit
  	);

	$payments = new WP_Query( $args );

	foreach ( (array) $payments->posts as $post ) {

		//$phone_number  	= get_post_meta( $post->ID, '_wp_setefi_payment_phone_number', true );
		//$tax_code			= get_post_meta( $post->ID, '_wp_setefi_payment_tax_code', true );
		//$company_name 	= get_post_meta( $post->ID, '_wp_setefi_payment_company_name', true );
		//$vat_number 		= get_post_meta( $post->ID, '_wp_setefi_payment_vat_number', true );
		//$fiscal_information = get_post_meta( $post->ID, '_wp_setefi_payment_fiscal_information', true );

		$custom_fields = array(
			"_wp_setefi_payment_first_name"		=> wp_privacy_anonymize_data( 'text', get_post_meta( $post->ID, '_wp_setefi_payment_first_name', true ) ),
			"_wp_setefi_payment_last_name"		=> wp_privacy_anonymize_data( 'text', get_post_meta( $post->ID, '_wp_setefi_payment_last_name', true ) ),
			"_wp_setefi_payment_email"			=> wp_privacy_anonymize_data( 'email', get_post_meta( $post->ID, '_wp_setefi_payment_email', true ) )
			//"_wp_setefi_payment_tax_code"		=> $frm_tax_code,
			//"_wp_setefi_payment_company_name"	=> $frm_company_name,
			//"_wp_setefi_payment_vat_number"		=> $frm_vat_number,
			//"_wp_setefi_payment_fiscal_information"			=> $frm_fiscal_information,
			//"_wp_setefi_payment_phone_number"	=> $frm_phone_number,
			// "_wp_setefi_payment_address"		=> wp_privacy_anonymize_data( 'text', get_post_meta( $post->ID, '_wp_setefi_payment_address', true ) ),
			// "_wp_setefi_payment_city"			=> wp_privacy_anonymize_data( 'text', get_post_meta( $post->ID, '_wp_setefi_payment_city', true ) ),
			// "_wp_setefi_payment_zip_code"		=> wp_privacy_anonymize_data( 'text', get_post_meta( $post->ID, '_wp_setefi_payment_zip_code', true ) )
		);


		foreach ($custom_fields as $key=>$val) {
			update_post_meta($post->ID, $key, $val);
		}

		$items_removed = true;

  	}


	$done = count( $payments ) < $limit;
	//$done = $payments->max_num_pages <= $page;

	return array(
		'items_removed' => $items_removed,
    	'items_retained' => false, // always false in this example
    	'messages' => array(), // no messages in this example
    	'done' => $done,
  	);
}

function register_wordpress_setefi_eraser( $erasers ) {
  $erasers['wordpress-setefi'] = array(
    'eraser_friendly_name' => 'Wordpress Monetaweb Setefi',
    'callback'             => 'wordpress_setefi_eraser',
    );
  return $erasers;
}

add_filter('wp_privacy_personal_data_erasers', 'register_wordpress_setefi_eraser', 10);
?>
