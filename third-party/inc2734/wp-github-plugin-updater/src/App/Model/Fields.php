<?php

/**
 * @package wp-github-plugin-updater
 * @author inc2734
 * @license GPL-2.0+
 */
namespace GWiz_GF_Code_Chest\Dependencies\Inc2734\WP_GitHub_Plugin_Updater\App\Model;

/**
 * @see https://github.com/WordPress/WordPress/blob/master/wp-admin/includes/plugin-install.php#L67-L95
 */
class Fields
{
    /**
     * Whether to return the plugin short description.
     *
     * @var string|boolean
     */
    public $short_description = \false;
    /**
     * Whether to return the plugin full description.
     *
     * @var string|boolean
     */
    public $description = \false;
    /**
     * Whether to return the plugin readme sections: description, installation,
     * FAQ, screenshots, other notes, and changelog.
     *
     * @var array|boolean
     *  - string description Description.
     *  - string installation Installation.
     *  - string faq FAQ.
     *  - string screenshots Screenshots.
     *  - string changelog Changelog.
     *  - string reviews Reviews.
     *  - string other_notes Other notes.
     */
    public $sections = \false;
    /**
     * Whether to return the 'Compatible up to' value.
     *
     * @var int|boolean
     */
    public $tested = \false;
    /**
     * Whether to return the required WordPress version.
     *
     * @var int|boolean
     */
    public $requires = \false;
    /**
     * Whether to return the required PHP version.
     *
     * @var int|boolean
     */
    public $requires_php = \false;
    /**
     * Whether to return the rating in percent and total number of ratings.
     *
     * @var int|boolean
     */
    public $rating = \false;
    /**
     * Whether to return the number of rating for each star (1-5).
     *
     * @var array|boolean
     */
    public $ratings = \false;
    /**
     * Whether to return the download count.
     *
     * @var int|boolean
     */
    public $downloaded = \false;
    /**
     * Whether to return the download link for the package.
     *
     * @var string|boolean
     */
    public $download_link = \false;
    /**
     * Whether to return the date of the last update.
     *
     * @var string|boolean
     */
    public $last_updated = \false;
    /**
     * Whether to return the date when the plugin was added to the wordpress.org repository.
     *
     * @var string|boolean
     */
    public $added = \false;
    /**
     * Whether to return the assigned tags.
     *
     * @var array|boolean
     */
    public $tags = \false;
    /**
     * Whether to return the WordPress compatibility list.
     *
     * @var array|boolean
     */
    public $compatibility = \false;
    /**
     * Whether to return the plugin homepage link.
     *
     * @var string|boolean
     */
    public $homepage = \false;
    /**
     * Whether to return the list of all available versions.
     *
     * @var array|boolean
     */
    public $versions = \false;
    /**
     * Whether to return the donation link.
     *
     * @var string|boolean
     */
    public $donate_link = \false;
    /**
     * Whether to return the plugin reviews.
     *
     * @var string|boolean
     */
    public $reviews = \false;
    /**
     * Whether to return the banner images links.
     *
     * @var array|boolean
     */
    public $banners = \false;
    /**
     * Whether to return the icon links.
     *
     * @var array|boolean
     */
    public $icons = \false;
    /**
     * Whether to return the number of active installations.
     *
     * @var int|boolean
     */
    public $active_installs = \false;
    /**
     * Whether to return the assigned group.
     *
     * @var string|boolean
     */
    public $group = \false;
    /**
     * Whether to return the list of contributors.
     *
     * @var array|boolean
     */
    public $contributors = \false;
    /**
     * Extend
     *
     * @var string
     */
    public $description_url = '';
    /**
     * Extend
     *
     * @var string
     */
    public $installation_url = '';
    /**
     * Extend
     *
     * @var string
     */
    public $faq_url = '';
    /**
     * Extend
     *
     * @var string
     */
    public $screenshots_url = '';
    /**
     * Extend
     *
     * @var string
     */
    public $changelog_url = '';
    /**
     * Extend
     *
     * @var string
     */
    public $reviews_url = '';
    /**
     * Extend
     *
     * @var string
     */
    public $other_notes_url = '';
    /**
     * Constructor.
     *
     * @param array $fields Array of fields.
     */
    public function __construct(array $fields)
    {
        foreach ($fields as $field => $value) {
            if (\property_exists($this, $field)) {
                $this->{$field} = $value;
            }
        }
    }
    /**
     * Return specific property.
     *
     * @param string $field Field.
     * @return mixed
     */
    public function get($field)
    {
        if (\property_exists($this, $field)) {
            return $this->{$field};
        }
        return \false;
    }
}
