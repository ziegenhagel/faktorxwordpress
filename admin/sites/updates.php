<?php
require_once plugin_dir_path(__FILE__) . '../../includes/helpers.php';

//deactivate updates if they are still activated
if (fxwp_check_deactivated_features('fxwp_deact_autoupdates')) {
    fxwp_disable_automatic_updates();
}
function fxwp_enable_automatic_updates()
{
    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    if (fxwp_check_deactivated_features('fxwp_deact_autoupdates')) {
        fxwp_disable_automatic_updates();
        return;
    }

    update_option('fxwp_automatic_updates', true);
    remove_filter( 'automatic_updater_disabled', '__return_true' );
    add_filter('auto_update_core', '__return_true');
    add_filter('auto_update_plugin', '__return_true');
    add_filter('auto_update_theme', '__return_true');


    // Get all plugins
    $plugins = array_keys(get_plugins());
    // Enable auto updates for all plugins
    update_option('auto_update_plugins', $plugins);

    // Get all themes
    $themes = array_keys(wp_get_themes());
    // Enable auto updates for all themes
    update_option('auto_update_themes', $themes);
}

// add action after installed plugin or theme
add_action('upgrader_process_complete', 'fxwp_enable_automatic_updates', 10, 2);

function fxwp_disable_automatic_updates()
{
    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    update_option('fxwp_automatic_updates', false);
    add_filter( 'automatic_updater_disabled', '__return_true' );
    remove_filter('auto_update_core', '__return_true');
    remove_filter('auto_update_plugin', '__return_true');
    remove_filter('auto_update_theme', '__return_true');

    // Get all plugins
    $plugins = array_keys(get_plugins());
    // Disable auto updates for all plugins
    update_option('auto_update_plugins', array());

    // Get all themes
    $themes = array_keys(wp_get_themes());
    // Disable auto updates for all themes
    update_option('auto_update_themes', array());

}

function fxwp_updates_page()
{

    // Check if a backup action was submitted
    if (isset($_POST['fxwp_update_settings_nonce']) && wp_verify_nonce($_POST['fxwp_update_settings_nonce'], 'fxwp_update_settings')) {
        // Run the appropriate function based on the submitted action
        switch ($_POST['fxwp_automatic_updates']) {
            case '1':
                // Replace this with your actual backup creation method
                fxwp_enable_automatic_updates();
                break;
            case '0':
                // Replace this with your actual backup restoration method
                // You would also need to pass the backup file name or other identifier as a parameter
                fxwp_disable_automatic_updates();
                break;
        }
    }

    ?>
    <div class="wrap">
        <h1>Aktualisierungen</h1>
        <?php fxwp_show_deactivated_feature_warning('fxwp_deact_autoupdates'); ?>
        <h2>Automatische Aktualisierungen</h2>
        <form method="post" action="">
            <?php wp_nonce_field('fxwp_update_settings', 'fxwp_update_settings_nonce'); ?>
            <label>
                <select name="fxwp_automatic_updates">
                    <option value="1" <?php selected(get_option('fxwp_automatic_updates', true), true); ?>>Aktiviert
                    </option>
                    <option value="0" <?php selected(get_option('fxwp_automatic_updates', true), false); ?>>Deaktiviert
                    </option>
                </select>
            </label>
            <p class="description">Wenn aktiviert, werden alle Plugins und die WordPress-Kernsoftware automatisch
                aktualisiert.</p>

            <h2>Manuelle Aktualisierungen</h2>
            <p>Um manuell zu aktualisieren, klicken Sie auf den "Jetzt aktualisieren" Button neben dem jeweiligen
                Element, das Sie aktualisieren möchten.</p>
            <?php
            if ( ! function_exists( 'get_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            // Holen Sie sich die Liste der installierten Plugins
            $plugins = get_plugins();

            if (!empty($plugins)) {
                echo '<h3>Plugins</h3>';
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr><th>Plugin</th><th>Aktion</th></tr></thead>';
                echo '<tbody>';
                foreach ($plugins as $plugin_file => $plugin_data) {
                    $update_url = wp_nonce_url(admin_url('update.php?action=upgrade-plugin&plugin=' . urlencode($plugin_file)), 'upgrade-plugin_' . $plugin_file);
                    echo '<tr>';
                    echo '<td>' . $plugin_data['Name'] . '</td>';
                    echo '<td><a href="' . $update_url . '" class="button button-primary">Jetzt aktualisieren</a></td>';
                    echo '</tr>';
                }
                echo '</tbody>';
                echo '</table>';
            }

            echo '<h3>WordPress-Kernsoftware</h3>';
            $core_update_url = wp_nonce_url(admin_url('update-core.php'), 'upgrade-core');
            echo '<a href="' . $core_update_url . '" class="button button-primary">WordPress jetzt aktualisieren</a>';
            ?>

            <p><strong>Hinweis:</strong> Es wird empfohlen, vor der Durchführung von Aktualisierungen ein Backup zu
                erstellen.</p>

            <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary"
                                     value="Änderungen speichern"></p>
        </form>
    </div>
    <?php
}
