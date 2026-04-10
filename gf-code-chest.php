<?php
/**
 * Plugin Name: Gravity Forms Code Chest
 * Description: Implement your form-specific styles and scripts for simple, portable customizations. Ahoy!
 * Plugin URI: https://gravitywiz.com/gravity-forms-code-chest/
 * Version: 1.0.9
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


define( 'GWIZ_GF_CODE_CHEST_VERSION', '1.0.9' );

defined( 'ABSPATH' ) || die();

require plugin_dir_path( __FILE__ ) . 'vendor/autoload_packages.php';

\Spellbook\Bootstrap::register( __FILE__ );

add_action( 'gform_loaded', function() {
	if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
		return;
	}

	GFAddOn::register( 'GWiz_GF_Code_Chest' );
}, 0 ); // Load before Gravity Flow

