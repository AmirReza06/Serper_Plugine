<?php
/**
 * Plugin Name: Serper Location Importer
 */

class SerperLocationPlugin {

    private $api_key = 'b8a887af3d7049d920a945110f7c06cf7cfa1da0';

    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('admin_post_fetch_locations', [$this, 'handle_form']);
        add_shortcode('location_search', [$this, 'render_search_form']);
    }

    public function register_post_type() {
        register_post_type('location', [
            'labels' => [
                'name' => 'مکان‌ها',
                'singular_name' => 'مکان',
            ],
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor'],
        ]);

        register_post_meta('location', 'address', ['show_in_rest' => true, 'single' => true, 'type' => 'string']);
        register_post_meta('location', 'cid', ['show_in_rest' => true, 'single' => true, 'type' => 'string']);
    }

    public function render_search_form() {
        ob_start();
        ?>
        <form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="fetch_locations">
            <?php wp_nonce_field('serper_search_nonce', 'serper_nonce'); ?>
            <input type="text" name="search_title" placeholder="مثلا: بهترین رستوران‌های مشهد" required>
            <button type="submit">جستجو</button>
        </form>
        <?php
        return ob_get_clean();
    }

    public function handle_form() {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز');
        }

        if (!isset($_POST['serper_nonce']) || !wp_verify_nonce($_POST['serper_nonce'], 'serper_search_nonce')) {
            wp_die('درخواست نامعتبر');
        }

        $query = sanitize_text_field($_POST['search_title']);
        $results = $this->fetch_locations_from_serper($query);

        if ($results && !empty($results['places'])) {
            foreach ($results['places'] as $place) {
                $post_id = wp_insert_post([
                    'post_title'  => $place['title'],
                    'post_type'   => 'location',
                    'post_status' => 'publish',
                ]);

                update_post_meta($post_id, 'address', json_encode([
                    'lat' => $place['latitude'],
                    'lng' => $place['longitude']
                ]));

                update_post_meta($post_id, 'cid', $place['cid']);
            }
            wp_redirect(add_query_arg('success', '1', wp_get_referer()));
            exit;
        } else {
            wp_redirect(add_query_arg('error', '1', wp_get_referer()));
            exit;
        }
    }

    private function fetch_locations_from_serper($search_query) {
        $response = wp_remote_post('https://google.serper.dev/maps', [
            'headers' => [
                'X-API-KEY' => $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'q' => $search_query,
                'gl' => 'ir',
                'hl' => 'fa',
            ]),
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }
}

new SerperLocationPlugin();
