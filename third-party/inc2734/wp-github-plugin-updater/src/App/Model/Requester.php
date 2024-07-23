<?php

/**
 * @package wp-github-plugin-updater
 * @author inc2734
 * @license GPL-2.0+
 */
namespace GWiz_GF_Code_Chest\Dependencies\Inc2734\WP_GitHub_Plugin_Updater\App\Model;

class Requester
{
    /**
     * Performs an HTTP request using the GET method and returns its response.
     *
     * @param string $url URL to retrieve.
     * @return array|WP_Error
     */
    public static function request($url)
    {
        global $wp_version;
        return \wp_remote_get($url, ['user-agent' => 'WordPress/' . $wp_version, 'timeout' => 30, 'headers' => ['Accept-Encoding' => '']]);
    }
}
