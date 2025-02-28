<?php
/**
 * Plugin Name: Dead Dove
 * Description: Content warning plugin that blurs content until the user accepts a disclaimer.
 * Version: 1.1
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author: Jeremy Malcolm
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Enqueue CSS and JS
function deaddove_enqueue_assets() {
    wp_enqueue_style('deaddove-style', plugin_dir_url(__FILE__) . 'css/deaddove-style.css');
    wp_enqueue_script('deaddove-script', plugin_dir_url(__FILE__) . 'js/deaddove-script.js', ['jquery'], null, true);
    wp_enqueue_script('deaddove-buddyboss-script', plugin_dir_url(__FILE__) . 'js/deaddove-buddyboss.js', ['jquery'], null, true);
    wp_enqueue_script('deaddove-buddypress-script', plugin_dir_url(__FILE__) . 'js/deaddove-buddypress.js', ['jquery'], null, true);
}
add_action('wp_enqueue_scripts', 'deaddove_enqueue_assets');

// Register the custom taxonomy
function deaddove_register_taxonomy() {
    register_taxonomy('content_warning', 'post', [
        'label' => 'Content Warnings',
        'public' => true,
        'show_in_rest' => true,
        'show_ui' => true,
        'rewrite' => ['slug' => 'content-warning'],
        'hierarchical' => false,
    ]);
}
add_action('init', 'deaddove_register_taxonomy');

// Enqueue the JavaScript file for the frontend behavior if needed
function deaddove_enqueue_modal_script() {
    if (!is_single()) {
        return; // Stop if not viewing a single post
    }

    global $post;

    // Check if the post has a content warning term
    $warning_terms = get_option('deaddove_warning_terms', []);
    $post_terms = wp_get_post_terms($post->ID, 'content_warning', ['fields' => 'slugs']);
    $has_warning_term = array_intersect($post_terms, $warning_terms);

    // Check if the post contains the content warning shortcode
    $has_shortcode = has_shortcode($post->post_content, 'content_warning');

    // Check if a content warning block is on the page
    $has_block = has_block('cw/content-warning', $post);

    // Enqueue the script if any trigger is found
    if ($has_warning_term || $has_shortcode || $has_block) {
        wp_enqueue_script(
            'deaddove-modal-script',
            plugin_dir_url(__FILE__) . 'js/deaddove-modal.js',
            ['jquery'],
            null,
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'deaddove_enqueue_modal_script');

// Apply content warnings based on post tags
function deaddove_filter_content($content) {
    if (!is_single() && !is_category() && !is_tax('bp_media_category')) return $content;

    $post_terms = wp_get_post_terms(get_the_ID(), 'content_warning', ['fields' => 'slugs']);
    $admin_terms = get_option('deaddove_warning_terms', []);
    $user_terms = get_user_meta(get_current_user_id(), 'deaddove_warning_terms', true) ?: $admin_terms;
    $warning_terms = array_intersect($admin_terms, $user_terms, $post_terms);

    if (empty($warning_terms)) return $content;

    $warnings = [];
    foreach ($warning_terms as $term) {
        $term_obj = get_term_by('slug', $term, 'content_warning');
        if ($term_obj) {
            $warnings[] = $term_obj->description ?: 'This content requires your agreement to view.';
        }
    }

    $warning_text = implode('<br><br>', $warnings);

    if (is_single()) {
        return '<div class="deaddove-modal-wrapper">
                    <div class="deaddove-modal" style="display:none;">
                        <div class="deaddove-modal-content">
                            <p>' . $warning_text . '</p>
                            <div class="modal-buttons">
                                <button class="deaddove-show-content-btn">Show this content</button>
                                <button class="deaddove-hide-content-btn">Keep it hidden</button>
                            </div>
                            <small><a href="#deaddove-warning-settings">Modify your content warning settings</a></small>
                        </div>
                    </div>
                    <div class="deaddove-blurred-content deaddove-blur">' . $content . '</div>
                </div>';
    } else {
        return '<div class="deaddove-blurred-content deaddove-blur">' . $content . '</div>';
    }
}
add_filter('the_content', 'deaddove_filter_content');

// Enqueue block editor assets
function deaddove_enqueue_block_editor_assets() {
    wp_enqueue_script(
        'deaddove-block-script',
        plugin_dir_url(__FILE__) . 'js/deaddove-block.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components'],  // Ensure dependencies are correct
        null,
        true
    );
}
add_action('enqueue_block_editor_assets', 'deaddove_enqueue_block_editor_assets');

// Register the block
function deaddove_register_content_warning_block() {
    register_block_type('cw/content-warning', [
        'editor_script' => 'deaddove-block-script',
        'render_callback' => 'deaddove_render_content_warning_block',
        'attributes' => [
            'terms' => [
                'type' => 'array', // Changed to 'array' to allow multiple tags
                'default' => [],
            ],
        ],
    ]);
}
add_action('init', 'deaddove_register_content_warning_block');

// Render callback for the block
function deaddove_render_content_warning_block($attributes, $content) {
    $term_ids = $attributes['terms'] ?? [];

    // Retrieve user term preferences or default ones.
    $admin_warning_terms = get_option('deaddove_warning_terms', []);
    $user_terms = get_user_meta(get_current_user_id(), 'deaddove_user_warning_terms', true) ?: $admin_warning_terms;

    $warning_texts = [];
    foreach ($term_ids as $term_id) {
        $term = get_term($term_id, 'content_warning');
        if ($term && in_array($term->slug, $user_terms)) {
            $warning_text = $term->description ?: 'This content requires your agreement to view.';
            $warning_texts[] = $warning_text;
        }
    }

    // If there are no warnings, show content directly.
    if (empty($warning_texts)) {
        return '<div class="deaddove-block-content">' . $content . '</div>';
    }

    // Create the warning modal with all warnings displayed.
    $all_warnings = implode('<br><br>', $warning_texts);

    return '
        <div class="deaddove-modal-wrapper">
            <div class="deaddove-modal" style="display: none;">
                <div class="deaddove-modal-content">
                    <p>' . $all_warnings . '</p>
                    <div class="modal-buttons">
                        <button class="deaddove-show-content-btn">Show this content</button>
                        <button class="deaddove-hide-content-btn">Keep it hidden</button>
                    </div>
                    <small><a href="#deaddove-warning-settings" class="deaddove-settings-link">Modify your content warning settings</a></small>
                </div>
            </div>
            <div class="deaddove-blurred-content deaddove-blur">
                ' . $content . ' <!-- Render nested blocks here -->
            </div>
        </div>';
}

// Register the block with a render callback.
add_action('init', function () {
    register_block_type('cw/content-warning', [
        'render_callback' => 'deaddove_render_content_warning_block',
    ]);
});

// Shortcode for custom content warnings
function deaddove_content_warning_shortcode($atts, $content = null) {
    $atts = shortcode_atts(['tags' => ''], $atts);
    $tags = array_map('trim', explode(',', $atts['tags']));

    $admin_warning_terms = get_option('deaddove_warning_terms', []);
    $user_terms = get_user_meta(get_current_user_id(), 'deaddove_user_warning_terms', true) ?: $admin_warning_terms;

    $warning_texts = [];
    foreach ($tags as $term_slug) {
        $term = get_term_by('slug', $term_slug, 'content_warning');
        if ($term && in_array($term_slug, $user_terms)) {
            $warning_text = $term->description ?: 'This content requires your agreement to view.';
            $warning_texts[] = $warning_text;
        }
    }

    if (empty($warning_texts)) {
        return do_shortcode($content);
    }

    $all_warnings = implode('<br><br>', $warning_texts);

    return '
        <div class="deaddove-modal-wrapper">
            <div class="deaddove-modal" style="display:none;">
                <div class="deaddove-modal-content">
                    <p>' . $all_warnings . '</p>
                    <div class="modal-buttons">
                        <button class="deaddove-show-content-btn">Show this content</button>
                        <button class="deaddove-hide-content-btn">Keep it hidden</button>
                    </div>
                    <small><a href="#deaddove-warning-settings" class="deaddove-settings-link">Modify your content warning settings</a></small>
                </div>
            </div>
            <div class="deaddove-blurred-content deaddove-blur">
                ' . do_shortcode($content) . '
            </div>
        </div>';
}
add_shortcode('content_warning', 'deaddove_content_warning_shortcode');

// Admin settings page
function deaddove_settings_page() {
    add_options_page(
        'Dead Dove Settings', 
        'Content Warning', 
        'manage_options', 
        'content-warning-settings', 
        'deaddove_settings_page_html'
    );
}
add_action('admin_menu', 'deaddove_settings_page');

// Settings page HTML
function deaddove_settings_page_html() {
    if (isset($_POST['deaddove_save_settings'])) {
        if (isset($_POST['deaddove_nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['deaddove_nonce']));
            if (!wp_verify_nonce($nonce, 'deaddove_save_settings_nonce')) {
                wp_die('Nonce verification failed.');
            }
        } else {
            wp_die('Nonce not found.');
        }

        // Save the selected terms
        $selected_terms = isset($_POST['deaddove_terms']) 
            ? array_map('sanitize_text_field', wp_unslash($_POST['deaddove_terms'])) 
            : [];
        update_option('deaddove_warning_terms', $selected_terms);

        // Reload the updated terms to reflect changes immediately
        $selected_terms = get_option('deaddove_warning_terms', []);
        echo '<div class="updated"><p>Settings saved!</p></div>';
    } else {
        // Load selected terms for the first time when the form is displayed
        $selected_terms = get_option('deaddove_warning_terms', []);
    }

    $all_terms = get_terms([
        'taxonomy' => 'content_warning',
        'hide_empty' => false, // Include unused terms
    ]);
    ?>
    <div class="wrap">
    <h1>Dead Dove Settings</h1>
    <form method="post" action="">
        <?php wp_nonce_field('deaddove_save_settings_nonce', 'deaddove_nonce'); ?>
        <label for="deaddove_terms">Select terms that require content warnings:</label>
        <div>
            <?php foreach ($all_terms as $term) : ?>
                <label>
                    <input type="checkbox" name="deaddove_terms[]" value="<?php echo esc_attr($term->slug); ?>"
                        <?php echo in_array($term->slug, $selected_terms) ? 'checked' : ''; ?>>
                    <?php echo esc_html($term->name); ?>
                </label><br>
            <?php endforeach; ?>
        </div>
        <p>Each term's description will be used as the warning text.</p>
        <input type="submit" name="deaddove_save_settings" value="Save Settings">
    </form>
    </div>
    <?php
}

// User profile settings section
function deaddove_user_profile_settings($user) {
    $admin_terms = get_option('deaddove_warning_terms', []);
    $user_terms = get_user_meta($user->ID, 'deaddove_user_warning_terms', true);
    $user_terms = $user_terms !== '' ? $user_terms : $admin_terms;  // Default to admin terms if user terms are empty
    $all_terms = get_terms([
        'taxonomy' => 'content_warning',
        'hide_empty' => false,  // Include unused terms
    ]);
    ?>
    <h3 id="deaddove-warning-settings">Dead Dove Settings</h3>
    <table class="form-table">
        <tr>
            <th><label for="deaddove_user_terms">Select terms for which a content warning should be shown:</label></th>
            <td>
                <div>
                    <?php foreach ($all_terms as $term) : ?>
                        <label>
                            <input type="checkbox" name="deaddove_user_terms[]" value="<?php echo esc_attr($term->slug); ?>"
                                <?php echo in_array($term->slug, $user_terms) ? 'checked' : ''; ?>>
                            <?php echo esc_html($term->name); ?>
                        </label><br>
                    <?php endforeach; ?>
                </div>
                <p class="description">These settings override the default warnings.</p>
            </td>
        </tr>
    </table>
    <?php wp_nonce_field('deaddove_user_profile_nonce', 'deaddove_user_nonce'); ?>
    <?php
}
add_action('show_user_profile', 'deaddove_user_profile_settings');
add_action('edit_user_profile', 'deaddove_user_profile_settings');

// Save user settings
function deaddove_save_user_profile_settings($user_id) {
    // Verify nonce
    if (!isset($_POST['deaddove_user_nonce']) || 
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['deaddove_user_nonce'])), 'deaddove_user_profile_nonce')) {
        return;  // Exit if nonce verification fails.
    }

    // Save selected terms or delete if empty
    if (isset($_POST['deaddove_user_terms'])) {
        $selected_terms = array_map('sanitize_text_field', wp_unslash($_POST['deaddove_user_terms']));
        update_user_meta($user_id, 'deaddove_user_warning_terms', $selected_terms);
    } else {
        delete_user_meta($user_id, 'deaddove_user_warning_terms');  // Clear settings if no terms are selected
    }
}
add_action('personal_options_update', 'deaddove_save_user_profile_settings');
add_action('edit_user_profile_update', 'deaddove_save_user_profile_settings');

// Save content warning terms for BuddyBoss media uploads
function deaddove_save_buddyboss_media_terms($media_id) {
    if (isset($_POST['deaddove_warning_terms'])) {
        $terms = array_map('sanitize_text_field', wp_unslash($_POST['deaddove_warning_terms']));
        wp_set_object_terms($media_id, $terms, 'content_warning');
    }
}
add_action('bp_attachments_media_after_save', 'deaddove_save_buddyboss_media_terms');

// Save content warning terms for BuddyPress Users Blog posts
function deaddove_save_buddypress_blog_terms($post_id) {
    if (isset($_POST['deaddove_warning_terms'])) {
        $terms = array_map('sanitize_text_field', wp_unslash($_POST['deaddove_warning_terms']));
        wp_set_object_terms($post_id, $terms, 'content_warning');
    }
}
add_action('buddypress_users_blog_after_save', 'deaddove_save_buddypress_blog_terms');

// Filter media content based on content warning terms
function deaddove_filter_media_content($content, $media_id) {
    $terms = wp_get_object_terms($media_id, 'content_warning', ['fields' => 'slugs']);
    if (empty($terms)) return $content;

    $warnings = [];
    foreach ($terms as $term_slug) {
        $term = get_term_by('slug', $term_slug, 'content_warning');
        if ($term) {
            $warnings[] = $term->description ?: 'This content requires your agreement to view.';
        }
    }

    $warning_text = implode('<br><br>', $warnings);

    return '<div class="deaddove-modal-wrapper">
                <div class="deaddove-modal" style="display:none;">
                    <div class="deaddove-modal-content">
                        <p>' . $warning_text . '</p>
                        <div class="modal-buttons">
                            <button class="deaddove-show-content-btn">Show this content</button>
                            <button class="deaddove-hide-content-btn">Keep it hidden</button>
                        </div>
                        <small><a href="#deaddove-warning-settings">Modify your content warning settings</a></small>
                    </div>
                </div>
                <div class="deaddove-blurred-content deaddove-blur">' . $content . '</div>
            </div>';
}
add_filter('bp_attachments_media_content', 'deaddove_filter_media_content', 10, 2);
