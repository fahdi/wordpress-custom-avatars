<?php
/*
Plugin Name: Custom Avatars
Plugin URI: https://wordpress.org/plugins/wordpress-custom-avatars-plugin/
Description: Give your WordPress blog custom avatars for users if they're not already using Gravatar. Created by <a href="http://www.ielectrify.com">iElectrify</a> and <a href="http://www.fahdmurtaza.com">Fahd Murtaza</a>
Author: Sherice Jacob & Fahd Murtaza
Author URI: http://www.ielectrify.com
Version: 1.2
*/

/**
 * Add settings link on plugin page
 */
function custom_avatars_settings_link($links) {
    $settings_link = '<a href="options-general.php?page=custom-avatars-settings">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter("plugin_action_links_" . plugin_basename(__FILE__), 'custom_avatars_settings_link');

/**
 * Add settings menu
 */
function custom_avatars_add_admin_menu() {
    add_options_page('Custom Avatars Settings', 'Custom Avatars', 'manage_options', 'custom-avatars-settings', 'custom_avatars_options_page');
}
add_action('admin_menu', 'custom_avatars_add_admin_menu');

/**
 * Register settings
 */
function custom_avatars_settings_init() {
    register_setting('customAvatars', 'custom_avatars_settings');

    add_settings_section(
        'custom_avatars_customAvatars_section',
        __('Custom Avatars Settings', 'customAvatars'),
        'custom_avatars_settings_section_callback',
        'customAvatars'
    );

    add_settings_field(
        'custom_avatars_default_avatar',
        __('Default Avatar', 'customAvatars'),
        'custom_avatars_default_avatar_render',
        'customAvatars',
        'custom_avatars_customAvatars_section'
    );

    add_settings_field(
        'custom_avatars_avatar_size',
        __('Avatar Size', 'customAvatars'),
        'custom_avatars_avatar_size_render',
        'customAvatars',
        'custom_avatars_customAvatars_section'
    );
}
add_action('admin_init', 'custom_avatars_settings_init');

/**
 * Render default avatar field
 */
function custom_avatars_default_avatar_render() {
    $options = get_option('custom_avatars_settings');
    ?>
    <input type='text' name='custom_avatars_settings[custom_avatars_default_avatar]' value='<?php echo $options['custom_avatars_default_avatar']; ?>'>
    <p>Enter the URL of the default avatar image.</p>
    <?php
}

/**
 * Render avatar size field
 */
function custom_avatars_avatar_size_render() {
    $options = get_option('custom_avatars_settings');
    ?>
    <input type='number' name='custom_avatars_settings[custom_avatars_avatar_size]' value='<?php echo $options['custom_avatars_avatar_size']; ?>' min="16" max="512">
    <p>Enter the size of the avatars in pixels.</p>
    <?php
}

/**
 * Settings section callback
 */
function custom_avatars_settings_section_callback() {
    echo __('Configure the settings for the Custom Avatars plugin.', 'customAvatars');
}

/**
 * Options page
 */
function custom_avatars_options_page() {
    ?>
    <form action='options.php' method='post'>
        <h2>Custom Avatars Settings</h2>
        <?php
        settings_fields('customAvatars');
        do_settings_sections('customAvatars');
        submit_button();
        ?>
    </form>
    <?php
}

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
    $options = get_option('custom_avatars_settings');

    // Ensure the global $comment object is available and valid
    if (!isset($comment) || !is_object($comment) || !isset($comment->comment_author_email)) {
        return;
    }

    // Validate if the comment author has a Gravatar
    if (!validate_gravatar($comment->comment_author_email)) {
        // Parse attributes to get necessary details
        $width = parse_attributes($args) ?: ($options['custom_avatars_avatar_size'] ?: 48); // Default width to 48 if not provided

        // Get random custom avatar
        $custom_avatar_url = get_random_image();
        if ($custom_avatar_url) {
            echo '<img src="' . esc_url($custom_avatar_url) . '" alt="Custom Avatar" width="' . esc_attr($width) . '" />';
        } else {
            // Fallback to user-defined default avatar image or a built-in default
            $default_avatar = $options['custom_avatars_default_avatar'] ?: plugin_dir_url(__FILE__) . 'images/default-avatar.png';
            echo '<img src="' . esc_url($default_avatar) . '" alt="Default Avatar" width="' . esc_attr($width) . '" />';
        }
    }
}
