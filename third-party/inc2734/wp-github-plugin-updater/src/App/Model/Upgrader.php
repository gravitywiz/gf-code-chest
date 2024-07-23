<?php

/**
 * @package wp-github-plugin-updater
 * @author inc2734
 * @license GPL-2.0+
 */
namespace GWiz_GF_Code_Chest\Dependencies\Inc2734\WP_GitHub_Plugin_Updater\App\Model;

use WP_Error;
class Upgrader
{
    /**
     * The plugin name.
     *
     * @var string
     */
    protected $plugin_name;
    /**
     * Constructor.
     *
     * @param string $plugin_name Plugin basename.
     */
    public function __construct($plugin_name)
    {
        $this->plugin_name = $plugin_name;
    }
    /**
     * Filters the install response before the installation has started.
     *
     * @param bool|WP_Error $bool Response.
     * @param array         $hook_extra Extra arguments passed to hooked filters.
     * @return bool|WP_Error.
     */
    public function pre_install($bool, $hook_extra)
    {
        if (!isset($hook_extra['plugin']) || $this->plugin_name !== $hook_extra['plugin']) {
            return $bool;
        }
        global $wp_filesystem;
        $plugin_dir = \trailingslashit(\WP_PLUGIN_DIR) . $this->plugin_name;
        if (!$wp_filesystem->is_writable($plugin_dir)) {
            return new WP_Error();
        }
        return $bool;
    }
}
