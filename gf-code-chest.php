<?php
/**
 * Plugin Name: Gravity Forms Code Chest
 * Description: Implement your form-specific styles and scripts for simple, portable customizations. Ahoy!
 * Plugin URI: https://gravitywiz.com/gravity-forms-code-chest/
 * Version: 1.0.7
 * Author: Gravity Wiz
 * Author URI: https://gravitywiz.com/
 * License: GPL2
 * Text Domain: gf-code-chest
 * Domain Path: /languages
 *
 * @package gf-code-chest
 * @copyright Copyright (c) 2022, Gravity Wiz, LLC
 * @author Gravity Wiz <support@gravitywiz.com>
 * @license GPLv2
 * @link https://gravitywiz.com/gravity-forms-code-chest/
 */


define( 'GWIZ_GF_CODE_CHEST_VERSION', '1.0.7' );

defined( 'ABSPATH' ) || die();

require plugin_dir_path( __FILE__ ) . 'vendor/autoload_packages.php';

add_action( 'gform_loaded', function() {
	if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
		return;
	}

	require plugin_dir_path( __FILE__ ) . 'class-gwiz-gf-code-chest.php';

	GFAddOn::register( 'GWiz_GF_Code_Chest' );
}, 0 ); // Load before Gravity Flow

/**
 * Returns an instance of the GWiz_GF_Code_Chest class
 *
 * @see    GWiz_GF_Code_Chest::get_instance()
 *
 * @return GWiz_GF_Code_Chest
 */
function gwiz_gf_code_chest() {
	return GWiz_GF_Code_Chest::get_instance();
}
