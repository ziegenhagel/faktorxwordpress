<?php
function fxwp_login_as_widget()
{
    if (!current_user_can('administrator')) {
        return;
    }

    echo '<p>' . esc_html__('Als Benutzer:in einloggen', 'fxwp') . '</p>';
    echo '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="post">';
    echo '<input type="hidden" name="action" value="fxwp_login_as">';
    wp_nonce_field('fxwp_login_as_action', 'fxwp_login_as_nonce');
    echo '<select name="user_id">';
    foreach (get_users() as $user) {
        echo '<option value="' . esc_attr($user->ID) . '">' . esc_html($user->display_name) . '</option>';
    }
    echo '</select>';
    echo '<input type="submit" value="' . esc_attr__('Login', 'fxwp') . '" class="button button-primary">';
    echo '</form>';
}

// add dashboard widget
function fxwp_register_login_as_widget()
{
    wp_add_dashboard_widget(
        'fxwp_login_as_widget', // Widget slug.
        'Als Benutzer:in einloggen', // Title.
        'fxwp_login_as_widget' // Display function.
    );
}

add_action('wp_dashboard_setup', 'fxwp_register_login_as_widget');


// define the admin-post action
function fxwp_login_as_action()
{
    // verify the nonce
    if (!isset($_POST['fxwp_login_as_nonce']) || !wp_verify_nonce($_POST['fxwp_login_as_nonce'], 'fxwp_login_as_action')) {
        wp_die('Security check fail');
    }

    // login as the selected user
    $user_id = intval($_POST['user_id']);
    wp_set_auth_cookie($user_id);
    wp_redirect(admin_url());
    exit;
}

add_action('admin_post_fxwp_login_as', 'fxwp_login_as_action');