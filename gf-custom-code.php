<?php
/**
 * Plugin Name: Gravity Forms Custom Code
 * Description: Pair the magic of Custom Code's robust models with Gravity Forms' flexibility.
 * Plugin URI: https://gravitywiz.com/gf-custom-code/
 * Version: 1.0.0
 * Author: Gravity Wiz
 * Author URI: https://gravitywiz.com/
 * License: GPL2
 * Text Domain: gf-custom-code
 * Domain Path: /languages
 *
 * @package gf-custom-code
 * @copyright Copyright (c) 2022, Gravity Wiz, LLC
 * @author Gravity Wiz <support@gravitywiz.com>
 * @license GPLv2
 * @link https://github.com/gravitywiz/gf-custom-code
 */

define( 'GWIZ_GF_CUSTOM_CODE_VERSION', '1.0.0' );

defined( 'ABSPATH' ) || die();

add_action( 'gform_loaded', function() {
	if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
		return;
	}

	require plugin_dir_path( __FILE__ ) . 'class-gwiz-gf-custom-code.php';

	GFAddOn::register( 'GWiz_GF_Custom_Code' );
}, 0 ); // Load before Gravity Flow

/**
 * Returns an instance of the GWiz_GF_Custom_Code class
 *
 * @see    GWiz_GF_Custom_Code::get_instance()
 *
 * @return GWiz_GF_Custom_Code
 */
function gwiz_gf_custom_code() {
	return GWiz_GF_Custom_Code::get_instance();
}
