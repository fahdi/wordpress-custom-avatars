<?php
/*
Plugin Name: Custom Avatars
Plugin URI: https://wordpress.org/plugins/wordpress-custom-avatars-plugin/
Description: Give your WordPress blog custom avatars for users if they're not already using Gravatar. Created by <a href="http://www.ielectrify.com">iElectrify</a> and <a href="http://www.fahdmurtaza.com">Fahd Murtaza</a>
Author: Sherice Jacob & Fahd Murtaza
Author URI: http://www.ielectrify.com
Version: 1.1
*/

/**
 * Validate if an email has a Gravatar.
 *
 * @param string $email Email address to check.
 * @return bool True if a valid Gravatar exists, false otherwise.
 */
function validate_gravatar($email) {
    $uri = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($email))) . '?d=404';
    $response = wp_remote_head($uri);

    if (is_wp_error($response)) {
        return false;
    }

    $http_code = wp_remote_retrieve_response_code($response);
    return $http_code == 200;
}

/**
 * Get a random image from the /images/ directory.
 *
 * @return string|null Random image filename or null if none found.
 */
function get_random_image() {
    $images_dir = plugin_dir_path(__FILE__) . 'images/';
    $images_url = plugin_dir_url(__FILE__) . 'images/';
    $images = array_diff(scandir($images_dir), array('..', '.'));

    if (empty($images)) {
        return null;
    }

    $random_image = $images[array_rand($images)];
    return $images_url . $random_image;
}

/**
 * Parse attributes from a given input string.
 *
 * @param string $input XML string containing attributes.
 * @return string|null Value of the "width" attribute, or null if not found.
 */
function parse_attributes($input) {
    $attr = simplexml_load_string($input);
    foreach ($attr->attributes() as $a => $b) {
        if ($a == "width") {
            return (string) $b;
        }
    }
    return null;
}

/**
 * Display custom avatar for comment authors if Gravatar is not available.
 *
 * @param string $args Arguments containing HTML attributes.
 */
function wavatar_comment_author($args) {
    global $comment;

    // Ensure the global $comment object is available and valid
    if (!isset($comment) || !is_object($comment) || !isset($comment->comment_author_email)) {
        return;
    }

    // Validate if the comment author has a Gravatar
    if (!validate_gravatar($comment->comment_author_email)) {
        // Parse attributes to get necessary details
        $width = parse_attributes($args) ?: 48; // Default width to 48 if not provided

        // Get random custom avatar
        $custom_avatar_url = get_random_image();
        if ($custom_avatar_url) {
            echo '<img src="' . esc_url($custom_avatar_url) . '" alt="Custom Avatar" width="' . esc_attr($width) . '" />';
        } else {
            // Fallback to default avatar if no custom images are available
            echo '<img src="' . esc_url(plugin_dir_url(__FILE__) . 'images/default-avatar.png') . '" alt="Default Avatar" width="' . esc_attr($width) . '" />';
        }
    }
}
?>
