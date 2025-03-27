<?php

// ثبت پست تایپ
add_action('init', function() {
    register_post_type('location', array(
        'labels' => array(
            'name' => 'مکان‌ها',
            'singular_name' => 'مکان',
        ),
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor'),
    ));
});

// اتصال به API سرپر
function fetch_locations_from_serper($search_query) {
    $api_key = 'b8a887af3d7049d920a945110f7c06cf7cfa1da0'; // کلید API سرپر رو اینجا بذار
    $url = 'https://google.serper.dev/maps';

    $data = array(
        'q' => $search_query,
        'gl' => 'ir',
        'hl' => 'fa'
    );

    $args = array(
        'method' => 'POST',
        'headers' => array(
            'X-API-KEY' => $api_key,
            'Content-Type' => 'application/json',
        ),
        'body' => json_encode($data),
    );

    $response = wp_remote_request($url, $args);
    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    return json_decode($body, true);
}

// پردازش و ساخت پست
add_action('init', function() {
    if (isset($_POST['search_title'])) {
        $search_query = sanitize_text_field($_POST['search_title']);
        $results = fetch_locations_from_serper($search_query);

        if ($results && !empty($results['places'])) {
            foreach ($results['places'] as $place) {
                $post_id = wp_insert_post(array(
                    'post_title'   => $place['title'],
                    'post_type'    => 'location',
                    'post_status'  => 'publish',
                ));

                $addresses = array(
                    'lat' => $place['latitude'],
                    'lng' => $place['longitude']
                );
                $addresses = json_encode($addresses);

                update_post_meta($post_id, 'address', $addresses);
                update_post_meta($post_id, 'cid', $place['cid']);
            }
            echo "پست‌ها با موفقیت ساخته شدند!";
        } else {
//             echo "مکانی پیدا نشد یا خطایی رخ داد.";
        }
    }
});

// شورت‌کد فرم
add_shortcode('location_search', function() {
    ob_start();
    ?>
    <form method="POST" action="">
        <input type="text" name="search_title" placeholder="مثلا: بهترین رستوران‌های مشهد" required>
        <button type="submit">جستجو</button>
    </form>
    <?php
    return ob_get_clean();
});