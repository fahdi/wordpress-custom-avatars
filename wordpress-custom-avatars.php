<?php
/*
Plugin Name: Custom Avatars
Plugin URI: http://www.ielectrify.com/resources/bloggingtips/custom-wordpress-avatars/
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
    $uri = 'http://www.gravatar.com/avatar.php?gravatar_id=' . md5($email) . '?&default=identicon&r=any&size=80';
    $headers = wp_get_http_headers($uri);

    // Check the headers
    if (!is_array($headers)) {
        return false;
    }

    return isset($headers["content-disposition"]);
}

/**
 * Get a random image from the /images/ directory.
 *
 * @return string Random image filename.
 */
function getImagesFromDir() {
    $imagesDir = dirname(__FILE__) . '/images/';
    if ($handle = opendir($imagesDir)) {
        $dirArray = [];
        while (false !== ($file = readdir($handle))) {
            if ($file != "." && $file != "..") {
                $dirArray[] = $file;
            }
        }
        closedir($handle);
        return $dirArray[array_rand($dirArray)];
    }
    return null;
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
        $attr = parse_attributes($args);

        // Logic to display custom avatar
        // For example, display a random image from the images directory
        $custom_avatar = getImagesFromDir();
        if ($custom_avatar) {
            echo '<img src="' . plugins_url('images/' . $custom_avatar, __FILE__) . '" alt="Custom Avatar" width="' . $attr . '" />';
        } else {
            // Fallback if no custom images are available
            echo '<img src="' . plugins_url('images/default-avatar.png', __FILE__) . '" alt="Default Avatar" width="' . $attr . '" />';
        }
    }
}
?>
