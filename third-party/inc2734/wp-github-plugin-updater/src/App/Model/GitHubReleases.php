<?php

/**
 * @package wp-github-plugin-updater
 * @author inc2734
 * @license GPL-2.0+
 */
namespace GWiz_GF_Code_Chest\Dependencies\Inc2734\WP_GitHub_Plugin_Updater\App\Model;

use WP_Error;
use GWiz_GF_Code_Chest\Dependencies\Inc2734\WP_GitHub_Plugin_Updater\App\Model\Requester;
class GitHubReleases
{
    /**
     * Plugin basename.
     *
     * @var string
     */
    protected $plugin_name;
    /**
     * GitHub user name.
     *
     * @var string
     */
    protected $user_name;
    /**
     * GitHub repository name.
     *
     * @var string
     */
    protected $repository;
    /**
     * Transient name.
     *
     * @var string
     */
    protected $transient_name;
    /**
     * Constructor.
     *
     * @param string $plugin_name Plugin basename.
     * @param string $user_name GitHub user name.
     * @param string $repository GitHub repository name.
     */
    public function __construct($plugin_name, $user_name, $repository)
    {
        $this->plugin_name = $plugin_name;
        $this->user_name = $user_name;
        $this->repository = $repository;
        $this->transient_name = \sprintf('wp_github_plugin_updater_%1$s', $this->plugin_name);
    }
    /**
     * Get release data.
     *
     * @return array
     */
    public function get()
    {
        $transient = \get_transient($this->transient_name);
        if (\false !== $transient) {
            return $transient;
        }
        $response = $this->_request();
        $response = $this->_retrieve($response);
        \set_transient($this->transient_name, $response, 60 * 5);
        return $response;
    }
    /**
     * Delete transient.
     */
    public function delete_transient()
    {
        \delete_transient($this->transient_name);
    }
    /**
     * Retrieve only the body from the raw response.
     *
     * @param array|WP_Error $response HTTP response.
     * @return array|null|WP_Error
     */
    protected function _retrieve($response)
    {
        global $pagenow;
        if (\is_wp_error($response)) {
            return $response;
        }
        $response_code = \wp_remote_retrieve_response_code($response);
        if ('' === $response_code) {
            return null;
        }
        $body = \json_decode(\wp_remote_retrieve_body($response));
        if (200 === (int) $response_code) {
            $body->package = $body->tag_name ? $this->_get_zip_url($body) : \false;
            return $body;
        }
        $message = null !== $body && \property_exists($body, 'message') ? $body->message : \__('Failed to get update response.', 'inc2734-wp-github-plugin-updater');
        $error_message = \sprintf(
            /* Translators: 1: Plugin name, 2: Error message  */
            \__('[%1$s] %2$s', 'inc2734-wp-github-plugin-updater'),
            $this->plugin_name,
            $message
        );
        if (\defined('WP_DEBUG') && \WP_DEBUG) {
            \error_log('Inc2734_WP_GitHub_Plugin_Updater error. [' . $response_code . '] ' . $error_message);
        }
        if (!\in_array($pagenow, ['update-core.php', 'plugins.php'], \true)) {
            return null;
        }
        return new WP_Error($response_code, $error_message);
    }
    /**
     * Request to GitHub contributors API.
     *
     * @return array|WP_Error
     */
    protected function _request()
    {
        $url = \sprintf('https://api.github.com/repos/%1$s/%2$s/releases/latest', $this->user_name, $this->repository);
        // phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores
        $url = \apply_filters(\sprintf('inc2734_github_plugin_updater_request_url_%1$s/%2$s', $this->user_name, $this->repository), $url, $this->user_name, $this->repository);
        // phpcs:enable
        return Requester::request($url);
    }
    /**
     * Return zip url.
     *
     * @param object $response Response.
     * @return string
     */
    protected function _get_zip_url($response)
    {
        $url = \false;
        if (!empty($response->assets) && \is_array($response->assets)) {
            if (!empty($response->assets[0]) && \is_object($response->assets[0])) {
                if (!empty($response->assets[0]->browser_download_url)) {
                    $url = $response->assets[0]->browser_download_url;
                }
            }
        }
        $tag_name = isset($response->tag_name) ? $response->tag_name : null;
        if (!$url && $tag_name) {
            $url = \sprintf('https://github.com/%1$s/%2$s/releases/download/%3$s/%2$s.zip', $this->user_name, $this->repository, $tag_name);
            $http_status_code = $this->_get_http_status_code($url);
            if (!\in_array($http_status_code, [200, 302], \true)) {
                $url = \sprintf('https://github.com/%1$s/%2$s/archive/%3$s.zip', $this->user_name, $this->repository, $tag_name);
            }
        }
        // phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores
        $url = \apply_filters(\sprintf('inc2734_github_plugin_updater_zip_url_%1$s/%2$s', $this->user_name, $this->repository), $url, $this->user_name, $this->repository, $tag_name);
        // phpcs:enable
        if (!$url) {
            \error_log('Inc2734_WP_GitHub_Plugin_Updater error. zip url not found.');
            return \false;
        }
        $http_status_code = $this->_get_http_status_code($url);
        if (!\in_array($http_status_code, [200, 302], \true)) {
            \error_log('Inc2734_WP_GitHub_Plugin_Updater error. zip url not found. ' . $http_status_code . ' ' . $url);
            return \false;
        }
        return $url;
    }
    /**
     * Return http status code from $url.
     *
     * @param string $url Target url.
     * @return int
     */
    protected function _get_http_status_code($url)
    {
        $response = Requester::request($url);
        return \wp_remote_retrieve_response_code($response);
    }
}
