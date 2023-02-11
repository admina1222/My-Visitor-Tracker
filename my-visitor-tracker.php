<?php
/*
Plugin Name: My Visitor Tracker
Plugin URI: https://www.xunika.uk/
Description: A simple plugin to track visitor IP addresses and locations.
Version: 1.1.0
Author: xunika.uk&ChatGPT
Author URI: https://www.xunika.uk/,https://chat.openai.com/
*/

// Register the custom database table
function myvisitortracker_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'myvisitortracker';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        ip_address varchar(100) NOT NULL,
        visit_time datetime NOT NULL,
        location varchar(255) DEFAULT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'myvisitortracker_install' );

// Track visitor IP address and location
function myvisitortracker_track_visitor() {
    if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
        $ip_address = $_SERVER['HTTP_CLIENT_IP'];
    } elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip_address = $_SERVER['REMOTE_ADDR'];
    }

    $location = myvisitortracker_get_location( $ip_address );

    global $wpdb;
    $table_name = $wpdb->prefix . 'myvisitortracker';
    $wpdb->insert(
        $table_name,
        array(
            'ip_address' => $ip_address,
            'visit_time' => current_time( 'mysql' ),
            'location' => $location
        ),
        array(
            '%s',
            '%s',
            '%s'
        )
    );
}
add_action( 'wp_footer', 'myvisitortracker_track_visitor' );

// Retrieve visitor location from IP address
function myvisitortracker_get_location( $ip_address ) {
    $url = 'http://ip-api.com/json/' . $ip_address;
    $response = wp_remote_get( $url );
    if ( is_wp_error( $response ) ) {
        return null;
    }
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body );
    if ( $data && $data->status == 'success' ) {
        return $data->country . ', ' . $data->city;
    } else {
        return null;
    }
}

// Add the visitor tracking page to the admin menu
function myvisitortracker_register_menu() {
    add_menu_page(
        'My Visitors',
        'My Visitors',
        'manage_options',
        'myvisitortracker',
        'myvisitortracker_admin_page',
        'dashicons-groups',
        30
    );
}
add_action( 'admin_menu', 'myvisitortracker_register_menu' );

// Output the visitor tracking page content
function myvisitortracker_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( isset( $_POST['myvisitortracker_delete'] ) && isset( $_POST['visitor_ids'] ) ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'myvisitortracker';
        $ids = array_map( 'intval', $_POST['visitor_ids'] );
        $ids = implode( ',', $ids );
        $wpdb->query( "DELETE FROM $table_name WHERE id IN ($ids)" );
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'myvisitortracker';
    $visitors = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY visit_time DESC" );
    ?>
    <div class="wrap">
        <h1>My Visitors</h1>
        <?php if ( ! empty( $visitors ) ) : ?>
            <form method="post">
                <?php wp_nonce_field( 'myvisitortracker_delete', 'myvisitortracker_delete_nonce' ); ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all"></th>
                            <th>IP Address</th>
                            <th>Visit Time</th>
                            <th>Location</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $visitors as $visitor ) : ?>
                            <tr>
                                <td><input type="checkbox" name="visitor_ids[]" value="<?php echo esc_attr( $visitor->id ); ?>"></td>
                                <td><?php echo esc_html( $visitor->ip_address ); ?></td>
                                <td><?php echo esc_html( date( 'Y-m-d H:i:s', strtotime( $visitor->visit_time ) ) ); ?></td>
                                <td><?php echo esc_html( $visitor->location ); ?></td>
                                <td><a href="#" class="delete-visitor" data-visitor-id="<?php echo esc_attr( $visitor->id ); ?>">Delete</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="submit" name="myvisitortracker_delete">Delete Selected</button>
            </form>
        <?php else : ?>
            <p>No visitors yet.</p>
        <?php endif; ?>
    </div>
    <script>
    jQuery(document).ready(function($) {
        $('#select-all').change(function() {
            $('input[name="visitor_ids[]"]').prop('checked', this.checked);
        });
        $('.delete-visitor').click(function() {
            var visitorId = $(this).data('visitor-id');
            if (confirm('Are you sure you want to delete this visitor?')) {
                $('<form>', {
                    'method': 'post',
                    'action': '',
                    'html': '<input type="hidden" name="visitor_ids[]" value="' + visitorId + '">',
                }).submit();
            }
        });
    });
    </script>
    <?php
}

