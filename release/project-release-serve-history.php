<?php

/**
 * @file
 * Thin wrapper to serve XML release history files to update.module.
 *
 * This script requires a local .htaccess file with the following:
 *
 * DirectoryIndex project-release-serve-history.php
 * <IfModule mod_rewrite.c>
 *   RewriteEngine on
 *   RewriteRule ^(.*)$ project-release-serve-history.php?q=$1 [L,QSA]
 * </IfModule>
 *
 * @author Derek Wright (http://drupal.org/user/46549)
 */

/**
 * Required configuration: directory tree for the XML history files.
 */
define('HISTORY_ROOT', 'PATH/TO/DRUPAL/files/release-history');


/**
 * Find and serve the proper history file.
 */

// Set page headers for the XML response.
header('Content-Type: text/xml; charset=utf-8');

// Make sure we have the path arguments we need.
$path = $_GET['q'];
$args = explode('/', $path);
if (empty($args[0])) {
  error('You must specify a project name to display the release history of.');
}
else {
  $project_name = $args[0];
}
if (empty($args[1])) {
  error('You must specify an API compatibility version as the final argument to the path.');
}
else {
  $api_version = $args[1];
}

// Sanitize the user-supplied input for use in filenames.
$whitelist_regexp = '@[^a-zA-Z0-9_.-]@';
$safe_project_name = preg_replace($whitelist_regexp, '#', $project_name);
$safe_api_vers = preg_replace($whitelist_regexp, '#', $api_version);

// Figure out the filename for the release history we want to serve.
$project_dir = HISTORY_ROOT . '/' . $safe_project_name;
$filename = $safe_project_name . '-' . $safe_api_vers . '.xml';
$full_path = $project_dir . '/' . $filename;

if (!is_file($full_path)) {
  if (!is_dir($project_dir)) {
    error(strtr('No release history was found for the requested project (@project).', array('@project' => _check_plain($project_name))));
  }
  error(strtr('No release history available for @project @version.', array('@project' => _check_plain($project_name), '@version' => _check_plain($api_version))));
  exit(1);
}

// Set the Last-Modified to the timestamp of the file.  Otherwise, disable all
// caching since a) we continue to have problems with squid on d.o and b)
// we're going to need this as soon as we start collecting stats.
$stat = stat($full_path);
$mtime = $stat[9];
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header("Expires: Sun, 19 Nov 1978 05:00:00 GMT");
header("Cache-Control: store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", FALSE);

// Serve the contents.
$file = file_get_contents($full_path);
// Old release xml files are missing the encoding. Prepend one if necessary.
if (substr($file, 0, 5) != '<?xml') {
  echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
}
echo $file;

/**
 * Copy of core's check_plain() function.
 */
function _check_plain($text) {
  return htmlspecialchars($text, ENT_QUOTES);
}

/**
 * Generate an error and exit.
 */
function error($text) {
  echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
  echo '<error>' . $text . "</error>\n";
  exit(1);
}
