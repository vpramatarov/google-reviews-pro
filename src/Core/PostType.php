<?php

declare(strict_types=1);

namespace GRP\Core;

final readonly class PostType
{
    public function __construct()
    {
        add_action('init', [$this, 'register']);

        // --- ADMIN COLUMNS ---
        add_filter('manage_grp_review_posts_columns', [$this, 'set_custom_columns']);
        add_action('manage_grp_review_posts_custom_column', [$this, 'render_custom_columns'], 10, 2);

        // --- META BOX (HIDE REVIEW) ---
        add_action('add_meta_boxes', [$this, 'add_moderation_meta_box']);
        add_action('save_post', [$this, 'save_moderation_meta']);
    }

    public function register(): void
    {
        register_post_type('grp_review', [
            'labels' => [
                'name' => __('Google Reviews', 'google-reviews-pro'),
                'singular_name' => __('Review', 'google-reviews-pro'),
                'all_items' => __('All Reviews', 'google-reviews-pro'),
                'edit_item' => __('Edit Review', 'google-reviews-pro'),
                'view_item' => __('View Review', 'google-reviews-pro'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'menu_icon' => 'dashicons-star-filled',
            'menu_position' => 56
        ]);
    }

    /**
     * @param string[] $columns
     * @return string[]
     */
    public function set_custom_columns(array $columns): array
    {
        $new_columns = [];
        $new_columns['cb'] = $columns['cb'];
        $new_columns['grp_avatar'] = __('Photo', 'google-reviews-pro'); // Featured Image
        $new_columns['title'] = __('Reviewer Name', 'google-reviews-pro');
        $new_columns['location'] = __('Location', 'google-reviews-pro');
        $new_columns['grp_rating'] = __('Rating', 'google-reviews-pro');
        $new_columns['grp_visible'] = __('Visibility', 'google-reviews-pro');
        $new_columns['date'] = $columns['date'];

        return $new_columns;
    }

    public function render_custom_columns(string $column, int $post_id): void
    {
        if ($column === 'grp_avatar') {
            if (has_post_thumbnail($post_id)) {
                $styles = 'width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid #ccc;';
                echo get_the_post_thumbnail($post_id, [40, 40], ['style' => $styles]);
            } else {
                echo '<span class="dashicons dashicons-admin-users" style="font-size: 30px; color: #ccc; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"></span>';
            }
        }

        if ($column === 'grp_rating') {
            $rating = get_post_meta($post_id, '_grp_rating', true);
            echo $rating ? '<strong>' . esc_html($rating) . '</strong> <span style="color:#fbbc04;">★</span>' : '—';
        }

        if ($column === 'location') {
            $location = get_post_meta($post_id, '_grp_assigned_place_id', true);
            $locations = get_option('grp_locations_db', []);
            if (empty($location) || !isset($locations[$location])) {
                echo '-';
            }

            printf ('<strong>%s</strong><hr>%s', esc_html($locations[$location]['name']), esc_html($locations[$location]['address']));
        }

        if ($column === 'grp_visible') {
            $is_hidden = get_post_meta($post_id, '_grp_is_hidden', true);
            if ($is_hidden) {
                echo '<span class="dashicons dashicons-hidden" style="color:#d63638;"></span> <span style="color:#d63638; font-weight:bold;">' . __('Hidden', 'google-reviews-pro') . '</span>';
            } else {
                echo '<span class="dashicons dashicons-visibility" style="color:#00a32a;"></span> ' . __('Visible', 'google-reviews-pro');
            }
        }
    }

    public function add_moderation_meta_box(): void
    {
        add_meta_box(
            'grp_moderation_box',
            __('Moderation', 'google-reviews-pro'),
            [$this, 'render_moderation_box'],
            'grp_review',
            'side',
            'high'
        );
    }

    public function render_moderation_box(\WP_Post $post): void
    {
        $is_hidden = get_post_meta($post->ID, '_grp_is_hidden', true);
        wp_nonce_field('grp_moderation_save', 'grp_moderation_nonce');
        ?>
        <p>
            <label>
                <input type="checkbox" name="grp_is_hidden" value="1" <?php checked($is_hidden, 1); ?>>
                <?php _e('Hide this review from frontend', 'google-reviews-pro'); ?>
            </label>
        </p>
        <?php
    }

    public function save_moderation_meta(int $post_id): void
    {
        if (!isset($_POST['grp_moderation_nonce']) || !wp_verify_nonce($_POST['grp_moderation_nonce'], 'grp_moderation_save')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['grp_is_hidden'])) {
            update_post_meta($post_id, '_grp_is_hidden', 1);
        } else {
            delete_post_meta($post_id, '_grp_is_hidden');
        }
    }
}
