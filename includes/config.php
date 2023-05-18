<?php
if (defined('FXWP_VERSION'))
    return;
// Plugin functionality
define('FXWP_VERSION', '0.2');
define('FXWP_API_URL', 'https://p2.faktorxmensch.com/api/fxwp');

// Server Settings
define('FXWP_STORAGE_LIMIT', 20 * 1024 * 1024 * 1024); // 20GB

// GVS Intervalle
// wir wollen die letzten 24 stunden, dann von jedem tag der letzten woche, dann von jedem monat der letzten 2 monate
define('FXWP_BACKUP_INTERVAL', 'hourly'); // vs daily

define('FXWP_BACKUP_DAYS_SON', 3); // keep hourly backups for the last X hours
define('FXWP_BACKUP_DAYS_FATHER', 12); // keep daily backups for the last X days
define('FXWP_BACKUP_DAYS_GRANDFATHER', 3 * 30); // keep monthly backups for the last X days


define('FXWP_THEME_REPO_URI','https://github.com/ziegenhagel/faktorxwordpress-theme.git');
