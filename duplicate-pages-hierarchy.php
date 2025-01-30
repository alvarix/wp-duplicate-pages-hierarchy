<?php
/**
 * Plugin Name: Duplicate Pages & Subpages
 * Description: Adds a "Duplicate Page & Subpages" action to duplicate a page and all its subpages, preserving meta data (Divi) and hierarchy.
 * Version: 1.0
 * Author: Alvar Sirlin
 * Author URI: https://alvarsirlin.dev
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Duplicates a page and all its subpages while preserving hierarchy and metadata.
 */
function dph_duplicate_page_and_subpages($original_parent_id) {
    $new_parent_id = dph_duplicate_page($original_parent_id, 0);
    if ($new_parent_id) {
        dph_duplicate_subpages($original_parent_id, $new_parent_id);
    }
}

/**
 * Duplicates a single page with all metadata, including Divi settings.
 */
function dph_duplicate_page($post_id, $new_parent_id) {
    $post = get_post($post_id);
    if (!$post) {
        return false;
    }

    // Create the duplicated page
    $new_page_id = wp_insert_post([
        'post_title'   => $post->post_title . ' (Copy)',
        'post_content' => $post->post_content,
        'post_status'  => 'draft',
        'post_type'    => 'page',
        'post_parent'  => $new_parent_id,
        'post_author'  => get_current_user_id(),
        'menu_order'   => $post->menu_order
    ]);

    if (!$new_page_id) {
        return false;
    }

    // Copy all post meta (including Divi settings)
    $meta_data = get_post_meta($post_id);
    foreach ($meta_data as $meta_key => $meta_value) {
        update_post_meta($new_page_id, $meta_key, maybe_unserialize($meta_value[0]));
    }

    return $new_page_id;
}

/**
 * Recursively duplicates subpages while maintaining hierarchy.
 */
function dph_duplicate_subpages($original_parent_id, $new_parent_id) {
    $subpages = get_posts([
        'post_type'   => 'page',
        'post_parent' => $original_parent_id,
        'numberposts' => -1
    ]);

    foreach ($subpages as $subpage) {
        $new_subpage_id = dph_duplicate_page($subpage->ID, $new_parent_id);
        if ($new_subpage_id) {
            dph_duplicate_subpages($subpage->ID, $new_subpage_id);
        }
    }
}

/**
 * Adds a "Duplicate Page & Subpages" link in the Pages list.
 */
function dph_add_duplicate_link($actions, $post) {
    if ($post->post_type === 'page') {
        $url = wp_nonce_url(admin_url('admin-post.php?action=dph_duplicate_page&post_id=' . $post->ID), 'dph_duplicate_nonce');
        $actions['duplicate_page'] = '<a href="' . esc_url($url) . '" title="Duplicate this page and its subpages">Duplicate Page & Subpages</a>';
    }
    return $actions;
}
add_filter('page_row_actions', 'dph_add_duplicate_link', 10, 2);

/**
 * Handles the duplication request when the "Duplicate Page & Subpages" link is clicked.
 */
function dph_duplicate_page_handler() {
    if (!isset($_GET['post_id']) || !wp_verify_nonce($_GET['_wpnonce'], 'dph_duplicate_nonce')) {
        wp_die('Invalid request.');
    }

    $post_id = intval($_GET['post_id']);
    if ($post_id > 0) {
        dph_duplicate_page_and_subpages($post_id);
        wp_redirect(admin_url('edit.php?post_type=page&duplicated=success'));
        exit;
    }
}
add_action('admin_post_dph_duplicate_page', 'dph_duplicate_page_handler');

/**
 * Displays an admin notice after duplication.
 */
function dph_admin_notice() {
    if (isset($_GET['duplicated']) && $_GET['duplicated'] === 'success') {
        echo '<div class="notice notice-success is-dismissible"><p>Page and subpages duplicated successfully.</p></div>';
    }
}
add_action('admin_notices', 'dph_admin_notice');