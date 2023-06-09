<?php
/**
 * Lädt alle PHP-Dateien in einem bestimmten Verzeichnis
 *
 * @param string $directory Das Verzeichnis, in dem die Dateien geladen werden sollen
 */

require_once plugin_dir_path(__FILE__) . 'config.php';
require_once plugin_dir_path(__FILE__) . 'helpers.php';

function fxwp_include_all_php($directory)
{
    // should load and plan all files that start with /cron , /widgets, or /admin files and are php
    $files = glob($directory . '/{admin/sites,cron,widgets,mods}/*.php', GLOB_BRACE);
    foreach ($files as $file) {
        require_once $file;
    }

}

// Lade alle PHP-Dateien im admin-Verzeichnis
fxwp_include_all_php(plugin_dir_path(__FILE__) . '../');
