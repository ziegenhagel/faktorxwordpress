<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
require_once plugin_dir_path(__FILE__) . '../includes/helpers.php';

if (!wp_next_scheduled('fxwp_backup_task')) {
//    wp_schedule_event(time(), 'hourly', 'fxwp_backup_task');
    wp_schedule_event(time(), FXWP_BACKUP_INTERVAL, 'fxwp_backup_task');
}

add_action('fxwp_backup_task', function () {
    if (fxwp_check_deactivated_features('fxwp_deact_backups')) {
        return;
    }

    // Check if the last backup was not completed.
    $completed = get_option('fxwp_backup_expected_completion', 1); // Default to 1 to assume previous success if not set

    if ($completed == 0) {
        // Last backup was interrupted.
        $message = "The previous backup attempt was not completed successfully.";
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail(FXWP_ERROR_EMAIL, 'Backup not completed on ' . get_site_url(), $message, $headers);
    }

    // Reset the expected completion status
    update_option('fxwp_backup_expected_completion', 0);

    // Attempt to set the maximum execution time to 180 seconds.
    // Note: This might not work on all server configurations.
    set_time_limit(180);
    //fix max_execution_time if .user.ini exists
    fxwp_fix_execution_time();
    fxwp_create_backup();
    fxwp_delete_expired_backups();
    update_option('fxwp_backup_expected_completion', 1); // Mark as completed successfully
});


function fxwp_create_backup()
{
    error_log('Creating backup');

    // Define the WordPress root directory
    $rootDir = ABSPATH;

    // Define the backup directory
    $backupDir = $rootDir . 'wp-content/fxwp-backups/';

    // Check if the backup directory exists, if not, create it
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    // Define the name of the backup file
    $backupFile = $backupDir . 'backup_' . date('Y-m-d_H-i-s') . '.zip';

    // Dump the Database
    $dumpFile = $backupFile . '.sql';

    // take wp-configs DB credentials
    $output = array();
    $returnValue = null;
    exec("mysqldump --user='" . DB_USER . "' --password='" . DB_PASSWORD . "' --host='" . DB_HOST . "' '" . DB_NAME . "' > $dumpFile", $output, $returnValue);

    // if mysqldump failed
    if ($returnValue !== 0) {
	    // fall back to PHP
	    $mysqli = new mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );

	    if ( $mysqli->connect_error ) {
		    die( 'Connect Error (' . $mysqli->connect_errno . ') '
		         . $mysqli->connect_error );
	    }

	    $tables = array();
	    $result = $mysqli->query( 'SHOW TABLES' );
	    while ( $row = $result->fetch_array( MYSQLI_NUM ) ) {
		    $tables[] = $row[0];
	    }

	    $sql = 'SET FOREIGN_KEY_CHECKS=0;' . "\n";
	    foreach ( $tables as $table ) {
		    $result    = $mysqli->query( 'SELECT * FROM ' . $table );
		    $numFields = $result->field_count;
		    $numRows   = $result->num_rows;
		    $i         = 0;

		    $sql  .= 'DROP TABLE IF EXISTS ' . $table . ';';
		    $row2 = $mysqli->query( 'SHOW CREATE TABLE ' . $table )->fetch_row();
		    $sql  .= "\n\n" . $row2[1] . ";\n\n";

		    for ( $j = 0; $j < $numFields; $j ++ ) {
			    while ( $row = $result->fetch_row() ) {
				    if ( $i % $numRows == 0 ) {
					    $sql .= 'INSERT INTO ' . $table . ' VALUES(';
				    } else {
					    $sql .= '(';
				    }

				    for ( $k = 0; $k < $numFields; $k ++ ) {
					    if ( isset( $row[ $k ] ) ) {
						    $sql .= '"' . $mysqli->real_escape_string( $row[ $k ] ) . '"';
					    } else {
						    $sql .= '""';
					    }
					    if ( $k < $numFields - 1 ) {
						    $sql .= ',';
					    }
				    }

				    if ( ( ( $i + 1 ) % $numRows ) == 0 ) {
					    $sql .= ");";
				    } else {
					    $sql .= "),";
				    }
				    $i ++;
			    }
		    }
	    }
	    $sql .= "\n\n\n";


	    $sql .= 'SET FOREIGN_KEY_CHECKS=1;';

	    file_put_contents( $dumpFile, $sql );
    }

    // Create a new zip archive
    $zip = new ZipArchive();
    if ($zip->open($backupFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
		error_log("Failed to create backup file $backupFile");
        exit("Failed to create backup file $backupFile");
    }

    // Create recursive directory iterator
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootDir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $name => $file) {
//        // Skip directories (they would be added automatically) and skip wp-config.php and skip everything under wp-content/fxwp-backups
//        if (!$file->isDir() && strpos($name, '/wp-content/uploads/') === false && strpos($name, '/wp-config.php') === false && strpos($name, '/wp-content/fxwp-backups/') === false) {

        // Some patterns to be excluded from the backup
        $exclude_patterns = array('backup*', '*backups', 'backwpup*', 'snapshots', 'wp-clone', 'upgrade', 'cache');
        $exclude = false;
        foreach ($exclude_patterns as $dir) {
            if (fnmatch('*' . $dir . '*', $name)) {
                $exclude = true;
                break;
            }
        }
        if (!$file->isDir() && !$exclude) {
            // Get real and relative path for current file
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($rootDir));

            // Add current file to archive
            $zip->addFile($filePath, $relativePath);
        }
    }

    // Zip archive will be created only after closing object
    $zip->close();

}

function fxwp_fix_execution_time()
{
    $max_execution_time = ini_get('max_execution_time');
    if ($max_execution_time < 180) {
        ini_set('max_execution_time', 180);
    }
    $userIniPath = ABSPATH . '.user.ini'; // ABSPATH is the WordPress root directory

    // Check if .user.ini exists or not
    if (file_exists($userIniPath)) {
        $currentSettings = file_get_contents($userIniPath);
        // Check if max_execution_time is already set
        if (strpos($currentSettings, 'max_execution_time') === false) {
            // Append max_execution_time setting if not found
            file_put_contents($userIniPath, "\nmax_execution_time=180", FILE_APPEND);
        } // else if it exists and is less than 180, set it to 180
        else {
            $currentSettings = preg_replace('/max_execution_time\s*=\s*\d+/', 'max_execution_time=180', $currentSettings);
            file_put_contents($userIniPath, $currentSettings);
        }
    }
}

function fxwp_get_backup_timestamp($filename)
{
    // get the filename without the path
    $filename = basename($filename);
    $backup2 = str_replace('backup_', '', $filename);
    $backup2 = str_replace('.zip', '', $backup2);
    $parts = explode('_', $backup2);

    $date = $parts[0];
    $time = $parts[1];
    //                        $date = str_replace('-', '.', $date);
    $time = str_replace('-', ':', $time);

    $date = $date . ' ' . $time;
    $ts = strtotime($date);
    return $ts;
}

function fxwp_delete_expired_backups()
{
    $rootDir = ABSPATH;
    $backupDir = $rootDir . 'wp-content/fxwp-backups/';
    $files = glob($backupDir . 'backup_*.zip');

    // Sort the array so the oldest files are first but take its filebasename
    array_multisort(
        array_map('fxwp_get_backup_timestamp', $files), SORT_NUMERIC, SORT_ASC,
        $files
    );

    $now = time();
    $hourly = $daily = $monthly = $older = array();

    foreach ($files as $file) {
        $fileTime = fxwp_get_backup_timestamp($file);
        $hoursOld = floor(($now - $fileTime) / (60 * 60));
        $daysOld = floor(($now - $fileTime) / (60 * 60 * 24));

        if ($hoursOld < FXWP_BACKUP_DAYS_SON) {
            $hourly[] = $file;
        } elseif ($daysOld < FXWP_BACKUP_DAYS_FATHER) {
            $daily[] = $file;
        } elseif ($daysOld < FXWP_BACKUP_DAYS_GRANDFATHER) {
            $monthly[] = $file;
        } else {
            $older[] = $file;
        }
    }

    // Throw every second daily backup away
    $daily = array_filter($daily, function ($key) {
        return $key % 2 == 0;
    }, ARRAY_FILTER_USE_KEY);

    // Keep only the last hourly backup per hour
    $keptHourly = array();
    foreach ($hourly as $file) {
        $timestamp = fxwp_get_backup_timestamp($file);
        $hourKey = date('Y-m-d-H', $timestamp);
        $keptHourly[$hourKey] = $file;
    }

    // Keep only one daily backup per day for FXWP_BACKUP_DAYS_FATHER days
    $keptDaily = array();
    foreach ($daily as $file) {
        $timestamp = fxwp_get_backup_timestamp($file);
        $dayKey = date('Y-m-d', $timestamp);
        $keptDaily[$dayKey] = $file;
    }

    // Keep only one monthly backup per month for FXWP_BACKUP_DAYS_GRANDFATHER days
    $keptMonthly = array();
    foreach ($monthly as $file) {
        $timestamp = fxwp_get_backup_timestamp($file);
        $monthKey = date('Y-m', $timestamp);
        $keptMonthly[$monthKey] = $file;
    }

    // Merge all the backups we want to keep
    $keptBackups = array_merge($keptHourly, $keptDaily, $keptMonthly);

    // Delete the backups not in the keptBackups array
    foreach ($files as $file) {
        if (!in_array($file, $keptBackups)) {
            unlink($file);
            unlink($file.".sql");
        }
    }


    $unsuccessfulBackups = array();
	$all_files = glob($backupDir . '*');
//	If file is *.sql and no other file with the same name but without the .sql exists, delete it
	foreach ($all_files as $file) {
//        error_log("Found file: ".$file);
		if (strpos($file, '.sql') !== false) {
			$filebasename = str_replace('.sql', '', $file);
//            error_log("Checking file: ".$filebasename);
			if (!in_array($filebasename, $all_files)) {
                // Did not find the zip backup file
                error_log("Did not find the zip backup file: ".$filebasename);
                foreach (glob($filebasename . '*') as $rem_file) {
//                    error_log("Found file to delete: ".$rem_file);
                    $unsuccessfulBackups[] = $rem_file;
                    unlink($rem_file);
                    error_log("Deleted file: ".$rem_file);
                }
			}
		}
	}
    //if there are any unsuccessful backups, sent email
    if (count($unsuccessfulBackups) > 0) {
        $unsuccessfulBackups = implode(", ", $unsuccessfulBackups);
        $to = FXWP_ERROR_EMAIL;
        //Get site url
        $site_url = get_site_url();
        $subject = 'Unsuccessful backups on '. $site_url;
        $message = 'The following backups were not successful and have been deleted: '.$unsuccessfulBackups;
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($to, $subject, $message, $headers);
    }
}

function fxwp_restore_backup($backupFile)
{
    // Define the backup directory
    $backupFile = ABSPATH . 'wp-content/fxwp-backups/' . $backupFile;

    // Create a new zip archive
    $zip = new ZipArchive();
    if ($zip->open($backupFile) !== true) {
        exit("Failed to open backup file $backupFile");
    }

    // Extract the backup file
    $zip->extractTo(ABSPATH);
    $zip->close();

    // Restore the database
    $dumpFile = $backupFile . '.sql';

    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

    if ($mysqli->connect_error) {
        die('Connect Error (' . $mysqli->connect_errno . ') '
            . $mysqli->connect_error);
    }

    $mysqli->query('SET FOREIGN_KEY_CHECKS=0');
    $mysqli->query('SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO"');

    // Read the SQL dump file
    $sqlStatements = file_get_contents($dumpFile);

    // Execute the SQL statements
    if ($mysqli->multi_query($sqlStatements)) {
        do {
            // Fetch the result of each query
            $mysqli->store_result();
        } while ($mysqli->more_results() && $mysqli->next_result());
    }

    $mysqli->query('SET FOREIGN_KEY_CHECKS=1');
    $mysqli->query('SET SQL_MODE=""');

    $mysqli->close();

}

function fxwp_delete_backup()
{
    // Define the WordPress root directory
    $rootDir = ABSPATH;

    // Define the backup directory
    $backupDir = $rootDir . 'wp-content/fxwp-backups/';

    // Get the latest backup file
    $files = glob($backupDir . '*.zip');
    $latestBackup = $files[0];

    // Delete the backup file
    unlink($latestBackup);
    unlink($latestBackup . '.sql');
}


function fxwp_list_backups()
{
    // Define the WordPress root directory
    $rootDir = ABSPATH;

    // Define the backup directory
    $backupDir = $rootDir . 'wp-content/fxwp-backups/';

    // Get all backup files
    $files = glob($backupDir . '*.zip');

    // make an array of the files
    $files = array_map(function ($file) {
        return basename($file);
    }, $files);

    return $files;
}
