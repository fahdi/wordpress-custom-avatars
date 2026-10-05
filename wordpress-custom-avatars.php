<?php
/*
Plugin Name: Custom Avatars
Plugin URI: https://wordpress.org/plugins/wordpress-custom-avatars-plugin/
Description: Give your WordPress blog custom avatars for users if they're not already using Gravatar. Created by <a href="http://www.ielectrify.com">iElectrify</a> and <a href="http://www.fahdmurtaza.com">Fahd Murtaza</a>
Author: Sherice Jacob & Fahd Murtaza
Author URI: http://www.ielectrify.com
Version: 1.3
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
 * Options merged with defaults so callers never hit undefined keys.
 *
 * @return array{custom_avatars_default_avatar:string,custom_avatars_avatar_size:int}
 */
function custom_avatars_get_options() {
    $options = get_option('custom_avatars_settings');
    if (!is_array($options)) {
        $options = array();
    }
    return wp_parse_args($options, array(
        'custom_avatars_default_avatar' => '',
        'custom_avatars_avatar_size'    => 48,
    ));
}

/**
 * Sanitize settings on save.
 *
 * @param mixed $input
 * @return array
 */
function custom_avatars_sanitize_settings($input) {
    $input = is_array($input) ? $input : array();
    $size  = isset($input['custom_avatars_avatar_size']) ? absint($input['custom_avatars_avatar_size']) : 48;
    return array(
        'custom_avatars_default_avatar' => isset($input['custom_avatars_default_avatar']) ? esc_url_raw(trim((string) $input['custom_avatars_default_avatar'])) : '',
        'custom_avatars_avatar_size'    => min(512, max(16, $size)),
    );
}

/**
 * Register settings
 */
function custom_avatars_settings_init() {
    register_setting('customAvatars', 'custom_avatars_settings', array(
        'type'              => 'array',
        'sanitize_callback' => 'custom_avatars_sanitize_settings',
        'default'           => array(),
    ));

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
    $options = custom_avatars_get_options();
    ?>
    <input type='url' class='regular-text' name='custom_avatars_settings[custom_avatars_default_avatar]' value='<?php echo esc_attr($options['custom_avatars_default_avatar']); ?>'>
    <p>Enter the URL of the default avatar image.</p>
    <?php
}

/**
 * Render avatar size field
 */
function custom_avatars_avatar_size_render() {
    $options = custom_avatars_get_options();
    ?>
    <input type='number' name='custom_avatars_settings[custom_avatars_avatar_size]' value='<?php echo esc_attr($options['custom_avatars_avatar_size']); ?>' min="16" max="512">
    <p>Enter the size of the avatars in pixels.</p>
    <?php
}

/**
 * Settings section callback
 */
function custom_avatars_settings_section_callback() {
    echo esc_html__('Configure the settings for the Custom Avatars plugin.', 'customAvatars');
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
 * Validate if an email has a Gravatar. Result is cached for a day per address
 * so a comment thread does not fire one HTTP request per avatar.
 *
 * @param string $email Email address to check.
 * @return bool True if a valid Gravatar exists, false otherwise.
 */
function validate_gravatar($email) {
    $hash = md5(strtolower(trim((string) $email)));
    $key  = 'custom_avatars_grav_' . $hash;

    $cached = get_transient($key);
    if ($cached !== false) {
        return $cached === 'yes';
    }

    $response = wp_remote_head('https://www.gravatar.com/avatar/' . $hash . '?d=404', array('timeout' => 3));
    $has      = !is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 200;

    set_transient($key, $has ? 'yes' : 'no', DAY_IN_SECONDS);

    return $has;
}

/**
 * Get a random image URL from the plugin's /images/ directory.
 *
 * Only real image files are returned (no .DS_Store, index.php, ...). The
 * listing is cached per request.
 *
 * @return string|null Random image URL or null if none found.
 */
function get_random_image() {
    static $images = null;

    if ($images === null) {
        $images_dir = plugin_dir_path(__FILE__) . 'images/';
        $files      = is_dir($images_dir) ? scandir($images_dir) : array();
        $images     = array();
        foreach ((array) $files as $file) {
            if (preg_match('/\.(png|jpe?g|gif|webp)$/i', $file) && is_file($images_dir . $file)) {
                $images[] = $file;
            }
        }
    }

    if (empty($images)) {
        return null;
    }

    return plugin_dir_url(__FILE__) . 'images/' . $images[array_rand($images)];
}

/**
 * Pick the custom avatar URL for a user/email that has no Gravatar.
 *
 * @return string
 */
function custom_avatars_fallback_url() {
    $options = custom_avatars_get_options();
    $random  = get_random_image();
    if ($random) {
        return $random;
    }
    if (!empty($options['custom_avatars_default_avatar'])) {
        return $options['custom_avatars_default_avatar'];
    }
    return plugin_dir_url(__FILE__) . 'images/default-avatar.png';
}

/**
 * Resolve an email address from whatever get_avatar() was handed.
 *
 * @param mixed $id_or_email
 * @return string
 */
function custom_avatars_email_from($id_or_email) {
    if ($id_or_email instanceof WP_Comment) {
        if (!empty($id_or_email->user_id)) {
            $user = get_user_by('id', (int) $id_or_email->user_id);
            if ($user) {
                return $user->user_email;
            }
        }
        return (string) $id_or_email->comment_author_email;
    }
    if ($id_or_email instanceof WP_User) {
        return $id_or_email->user_email;
    }
    if ($id_or_email instanceof WP_Post) {
        $user = get_user_by('id', (int) $id_or_email->post_author);
        return $user ? $user->user_email : '';
    }
    if (is_numeric($id_or_email)) {
        $user = get_user_by('id', (int) $id_or_email);
        return $user ? $user->user_email : '';
    }
    return is_string($id_or_email) ? $id_or_email : '';
}

/**
 * Replace the avatar URL with a custom image when the address has no Gravatar.
 *
 * Hooked to pre_get_avatar_data so every get_avatar()/get_avatar_url() caller
 * (comments, admin bar, author boxes, REST) gets the same result.
 *
 * @param array $args
 * @param mixed $id_or_email
 * @return array
 */
function custom_avatars_pre_get_avatar_data($args, $id_or_email) {
    $email = custom_avatars_email_from($id_or_email);
    if (!is_email($email)) {
        return $args;
    }

    if (validate_gravatar($email)) {
        return $args;
    }

    $options      = custom_avatars_get_options();
    $args['url']  = custom_avatars_fallback_url();
    $args['size'] = !empty($args['size']) ? (int) $args['size'] : (int) $options['custom_avatars_avatar_size'];
    $args['found_avatar'] = true;

    return $args;
}
add_filter('pre_get_avatar_data', 'custom_avatars_pre_get_avatar_data', 10, 2);
