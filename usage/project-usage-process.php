#!/usr/bin/php
<?php
// $Id$


/**
 * @file
 * Processes the project_usage statistics.
 *
 * @author Andrew Morton (http://drupal.org/user/34869)
 * @author Derek Wright (http://drupal.org/user/46549)
 *
 */

// ------------------------------------------------------------
// Required customization
// ------------------------------------------------------------

// The root of your Drupal installation, so we can properly bootstrap
// Drupal. This should be the full path to the directory that holds
// your index.php file, the "includes" subdirectory, etc.
define('DRUPAL_ROOT', '');

// The name of your site. Required so that when we bootstrap Drupal in
// this script, we find the right settings.php file in your sites folder.
define('SITE_NAME', '');


// ------------------------------------------------------------
// Initialization
// (Real work begins here, nothing else to customize)
// ------------------------------------------------------------

// Check if all required variables are defined
$vars = array(
  'DRUPAL_ROOT' => DRUPAL_ROOT,
  'SITE_NAME' => SITE_NAME,
);
$fatal_err = FALSE;
foreach ($vars as $name => $val) {
  if (empty($val)) {
    print "ERROR: \"$name\" constant not defined, aborting\n";
    $fatal_err = TRUE;
  }
}
if ($fatal_err) {
  exit(1);
}

$script_name = $argv[0];

// Setup variables for Drupal bootstrap
$_SERVER['HTTP_HOST'] = SITE_NAME;
$_SERVER['REQUEST_URI'] = '/' . $script_name;
$_SERVER['SCRIPT_NAME'] = '/' . $script_name;
$_SERVER['PHP_SELF'] = '/' . $script_name;
$_SERVER['SCRIPT_FILENAME'] = $_SERVER['PWD'] .'/'. $script_name;
$_SERVER['PATH_TRANSLATED'] = $_SERVER['SCRIPT_FILENAME'];

if (!chdir(DRUPAL_ROOT)) {
  print "ERROR: Can't chdir(DRUPAL_ROOT), aborting.\n";
  exit(1);
}
// Make sure our umask is sane for generating directories and files.
umask(022);

require_once 'includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

if (!module_exists('project_usage')) {
  wd_err(t("ERROR: Project usage module does not exist, aborting.\n"));
  exit(1);
}


// ------------------------------------------------------------
// Call the daily and weekly processing tasks as needed.
// ------------------------------------------------------------

$now = time();

// Figure out if it's been 24 hours since our last daily processing.
if (variable_get('project_usage_last_daily', 0) <= ($now - PROJECT_USAGE_DAY)) {
  project_usage_process_daily();
  variable_set('project_usage_last_daily', $now);
}

// Figure out if it's been a week since we did weekly stats. If the weekly
// data has never been processed go back only as far as there is daily data.
$default = $now - variable_get('project_usage_life_daily', 4 * PROJECT_USAGE_WEEK);
$last_weekly = variable_get('project_usage_last_weekly', $default);
if ($last_weekly <= ($now - PROJECT_USAGE_WEEK)) {
  project_usage_process_weekly($last_weekly);
  variable_set('project_usage_last_weekly', $now);

  // Reset the list of active weeks.
  project_usage_get_active_weeks(TRUE);
}

// Wipe the cache of all expired usage pages.
cache_clear_all(NULL, 'cache_project_usage');



/**
 * Process all the raw data up to the previous day.
 *
 * The primary key on the {project_usage_raw} table will prevent duplicate
 * records provided we process them once the day is complete. If we pull them
 * out too soon and the site checks in again they will be counted twice.
 */
function project_usage_process_daily() {
  // Timestamp for begining of the previous day.
  $timestamp = project_usage_daily_timestamp(NULL, 1);

  watchdog('project_usage', t('Starting to process daily usage data for @date.', array('@date' => format_date($timestamp))));

  // Assign API version term IDs.
  $terms = array();
  foreach (project_release_get_api_taxonomy() as $term) {
    $terms[$term->tid] = $term->name;
  }
  $query = db_query("SELECT DISTINCT api_version FROM {project_usage_raw} WHERE tid = 0");
  while ($row = db_fetch_object($query)) {
    $tid = array_search($row->api_version, $terms);
    db_query("UPDATE {project_usage_raw} SET tid = %d WHERE api_version = '%s'", $tid, $row->api_version);
  }
  watchdog('project_usage', t('Assigned API version term IDs.'));

  // Asign project and release node IDs.
  $query = db_query("SELECT DISTINCT project_uri, project_version FROM {project_usage_raw} WHERE pid = 0 OR nid = 0");
  while ($row = db_fetch_object($query)) {
    $pid = db_result(db_query("SELECT pp.nid AS pid FROM {project_projects} pp WHERE pp.uri = '%s'", $row->project_uri));
    if ($pid) {
      $nid = db_result(db_query("SELECT prn.nid FROM {project_release_nodes} prn WHERE prn.pid = %d AND prn.version = '%s'", $pid, $row->project_version));
      db_query("UPDATE {project_usage_raw} SET pid = %d, nid = %d WHERE project_uri = '%s' AND project_version = '%s'", $pid, $nid, $row->project_uri, $row->project_version);
    }
  }
  watchdog('project_usage', t('Assigned project and release node IDs.'));

  // Move usage records with project node IDs into the daily table and remove
  // the rest.
  db_query("INSERT INTO {project_usage_day} (timestamp, site_key, pid, nid, tid, ip_addr) SELECT timestamp, site_key, pid, nid, tid, ip_addr FROM {project_usage_raw} WHERE timestamp < %d AND pid <> 0", $timestamp);
  db_query("DELETE FROM {project_usage_raw} WHERE timestamp < %d", $timestamp);
  watchdog('project_usage', t('Moved usage from raw to daily.'));

  // Remove old daily records.
  $seconds = variable_get('project_usage_life_daily', 4 * PROJECT_USAGE_WEEK);
  db_query("DELETE FROM {project_usage_day} WHERE timestamp < %d", time() - $seconds);
  watchdog('project_usage', t('Removed old daily rows.'));

  watchdog('project_usage', t('Completed daily usage data processing.'));
}

/**
 * Compute the weekly summaries for the week starting at the given timestamp.
 *
 * @param $timestamp
 *   UNIX timestamp indicating the last time weekly stats were processed.
 */
function project_usage_process_weekly($timestamp) {
  watchdog('project_usage', t('Starting to process weekly usage data.'));

  // Get all the weeks since we last ran.
  $weeks = project_usage_get_weeks_since($timestamp);
  // Skip the last entry since it's the current, incomplete week.
  $count = count($weeks) - 1;
  for ($i = 0; $i < $count; $i++) {
    $start = $weeks[$i];
    $end = $weeks[$i + 1];
    $date = format_date($start);

    // Try to compute the usage tallies per project and per release. If there
    // is a problem--perhaps some rows existed from a previous, incomplete
    // run that are preventing inserts, throw a watchdog error.

    $sql = "INSERT INTO {project_usage_week_project} (nid, timestamp, tid, count) SELECT pid, %d, tid, COUNT(DISTINCT site_key) FROM {project_usage_day} WHERE timestamp >= %d AND timestamp < %d AND pid <> 0 GROUP BY pid, tid";
    if (!db_query($sql, array($start, $start, $end))) {
      watchdog('project_usage', t('Query failed inserting weekly project tallies for @date.', array('@date' => $date)), WATCHDOG_ERROR);
    }
    else {
      watchdog('project_usage', t('Computed weekly project tallies for @date.', array('@date' => $date)));
    }

    $sql = "INSERT INTO {project_usage_week_release} (nid, timestamp, count) SELECT nid, %d, COUNT(DISTINCT site_key) FROM {project_usage_day} WHERE timestamp >= %d AND timestamp < %d AND nid <> 0 GROUP BY nid";
    if (!db_query($sql, array($start, $start, $end))) {
      watchdog('project_usage', t('Query failed inserting weekly release tallies for @date.', array('@date' => $date)), WATCHDOG_ERROR);
    }
    else {
      watchdog('project_usage', t('Computed weekly release tallies for @date.', array('@date' => $date)));
    }
  }

  // Remove any tallies that have aged out.
  $now = time();
  $project_life = variable_get('project_usage_life_weekly_project', PROJECT_USAGE_YEAR);
  db_query("DELETE FROM {project_usage_week_project} WHERE timestamp < %d", $now - $project_life);
  watchdog('project_usage', t('Removed old weekly project rows.'));

  $release_life = variable_get('project_usage_life_weekly_release', 26 * PROJECT_USAGE_WEEK);
  db_query("DELETE FROM {project_usage_week_release} WHERE timestamp < %d", $now - $release_life);
  watchdog('project_usage', t('Removed old weekly release rows.'));

  watchdog('project_usage', t('Completed weekly usage data processing.'));
}
