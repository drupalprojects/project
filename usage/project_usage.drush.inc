<?php

/**
 * @file
 * Update the project_usage statistics incrementally from logs.
 *
 * @author Brandon Bergren (http://drupal.org/user/53081)
 *
 * Originally based on project-usage-process.php. Rewritten for drush by
 * Brandon Bergren.
 * @author Andrew Morton (http://drupal.org/user/34869)
 * @author Derek Wright (http://drupal.org/user/46549)
 */

/**
 * Default updates URL.
 */
define('USAGE_STATS_UPDATES_URL', 'http://updates.drupal.org/release-history/');

/**
 * Implements hook_drush_help().
 */
function project_usage_drush_help($section) {
  switch ($section) {
    case 'meta:project_usage:title':
      return dt('Project Usage Statistics commands');

    case 'meta:project_usage:summary':
      return dt('Commands for processing usage statistics');

    case 'project_usage:process-varnish-files':
      return dt('Process all files matching a glob as varnish logs.');

    case 'project_usage:process-varnish-file':
      return dt('Process a specific log file as a varnish log.');

    case 'project_usage:tally-usage-stats':
      return dt('Tally processed update statistics data.');

    case 'project_usage:import-usage-stats':
      return dt('Import pre-aggregated usage stats.');
  }
}

/**
 * Implements hook_drush_command().
 */
function project_usage_drush_command() {
  $items = array();

  $items['process-varnish-files'] = array(
    'description' => 'Process all files matching a glob as varnish logs.',
    'drupal dependencies' => array('project_usage'),
    'examples' => array(
      'drush process-varnish-files "/var/log/DROP/www*/transfer*log*" --updates-url "http://updates.drupal.org/release-history/"' => 'Process drupal.org update statistics.',
    ),
    'arguments' => array(
      'glob' => 'A shell glob used to determine the set of files to process.',
    ),
    'options' => array(
      'updates-url' => 'The base url of the update server, which is used to match varnish log entries.',
    ),
  );

  $items['process-varnish-file'] = array(
    'description' => 'Process a specific log file as a varnish log.',
    'drupal dependencies' => array('project_usage'),
    'examples' => array(
      'drush process-varnish-file /tmp/backfill.log --updates-url "http://updates.drupal.org/release-history/"' => 'Backfill a log file manually.',
    ),
    'arguments' => array(
      'filename' => 'A file to process.',
    ),
    'options' => array(
      'updates-url' => 'The base url of the update server, which is used to match varnish log entries.',
    ),
  );

  $items['tally-usage-stats'] = array(
    'description' => 'Tally processed update statistics data.',
    'drupal dependencies' => array('project_usage'),
    'examples' => array(
      'drush tally-usage-stats' => 'Tally processed update statistics data.',
    ),
  );

  $items['import-usage-stats'] = array(
    'description' => 'Import pre-aggregated usage stats.',
    'drupal dependencies' => array('project_usage'),
    'examples' => array(
      'drush import-usage-stats' => 'Import pre-aggregated usage stats.',
    ),
    'arguments' => array(
      'weektimestamp' => 'Process which weeks worth of data?',
    ),
    'options' => array(
      'count-file-path' => 'The path on the server of the count files.',
    ),
  );

  return $items;
}

/**
 * Drush command to tally processed data.
 */
function drush_project_usage_tally_usage_stats() {
  module_load_include('inc', 'project_usage', 'includes/date_api');
  // Delete old weeks from Mongo.
  project_usage_expire_statfiles();
  // Compute summary data.
  project_usage_tally();
}

/**
 * Drush command to process all varnish logfiles matching a glob.
 */
function drush_project_usage_process_varnish_files($glob = NULL) {
  module_load_include('inc', 'project_usage', 'includes/date_api');
  if (!$glob) {
    return drush_set_error('PROJECT_USAGE', dt('You must supply a glob!'));
  }
  // Determine the updates url to search.
  $updates_url = drush_get_option('updates-url');
  if (!$updates_url) {
    $updates_url = USAGE_STATS_UPDATES_URL;
  }

  // Safety harness off.
  ini_set('memory_limit', '-1');

  // Update Mongo's copy of the project metadata.
  project_usage_load_metadata();

  // Precache the lookup tables.
  project_usage_lookup();

  // Process each unprocessed file.
  $files = glob($glob);
  drush_log(dt("Processing @count file(s).", array('@count' => count($files))), 'ok');
  $db = project_usage_mongo();
  // 'processed-files' is a collection that tracks the filenames
  // we have processed in the past.
  $c = $db->selectCollection('processed-files');
  $c->ensureIndex('filename');
  foreach ($files as $file) {
    $record = $c->findOne(array('filename' => $file));
    if (!$record) {
      $record = array(
        'filename' => $file,
        'timestamp' => $_SERVER['REQUEST_TIME'],
      );
      drush_log(dt("Processing @file", array('@file' => $file)));
      $return = 0;
      // Run the loader as a subprocess.
      _project_usage_process_varnish_file($file, $updates_url);
      // Ping the database to keep our connection alive.
      db_query('SELECT 1');
      $c->save($record);
    }
    else {
      drush_log(dt("Skipping @file -- was processed already at @timestamp", array('@file' => $file, '@timestamp' => $record['timestamp'])), 'warning');
    }
  }
}

function drush_project_usage_process_varnish_file($filename = NULL) {
  module_load_include('inc', 'project_usage', 'includes/date_api');
  if (!$filename) {
    return drush_set_error('PROJECT_USAGE', dt('You must supply a filename!'));
  }
  // Determine the updates url to search.
  $updates_url = drush_get_option('updates-url');
  if (!$updates_url) {
    $updates_url = USAGE_STATS_UPDATES_URL;
  }

  // Safety harness off.
  ini_set('memory_limit', '-1');

  // Update Mongo's copy of the project metadata.
  project_usage_load_metadata();

  // Precache the lookup tables.
  project_usage_lookup();

  _project_usage_process_varnish_file($filename, $updates_url);
}

function _project_usage_process_varnish_file($filename = NULL, $updates_url = NULL) {
  $handle = _project_usage_open_file($filename);
  $releasedata = project_usage_lookup('releasedata');
  $rterms = project_usage_lookup('rterms');

  $db = project_usage_mongo();

  drush_log(dt("Begin processing log file."));
  $time1 = microtime(TRUE);
  $timestr = '';
  $timestamp = 0;
  $week = 0;
  // Current collection.
  $ccol = 0;
  $c = FALSE;
  $st = array(
    '@change' => 0,
    '@nochange' => 0,
    '@wrongcore' => 0,
    '@invalid' => 0,
    '@file' => $filename,
  );
  $buffer = array();
  $bcnt = 0;
  while ($line = fgets($handle)) {
    $regex = variable_get('project_usage_updates_regex', '@\[([^\]]+)\] "?(?:GET|HEAD) ' . $updates_url . '([^/]+)/([^\?]+)\?([^ ]+) @');
    if (preg_match($regex, $line, $matches)) {
      // We are cheating on url parsing a bit for speed reasons. We have a lot
      // of log to get through as fast as possible. None of the "sane" entries
      // will have stuff like anchors in the url.
      list(, $time, $project, $core, $query) = $matches;
      $ip = substr($line, 0, strpos($line, " "));

      // This cache is awesome. Hit rate was ~92% on a one-day logfile.
      // On my laptop, it reduces execution time of the parse loop from 170
      // seconds to 70 seconds. I feel I will be unable to top that.
      if ($timestr != $time) {
        $timestr = $time;
        $timestamp = strtotime($time);
        $week = project_usage_weekly_timestamp($timestamp);
        // Change collections on a week boundary.
        if ($ccol != $week) {
          $ccol = $week;
          if ($bcnt > 0) {
            // We need to process the buffer before switching weeks.
            project_usage_process_batch($c, $buffer, $st);
            $bcnt = 0;
            $buffer = array();
          }
          $c = $db->selectCollection("$week");
          $c->ensureIndex('site_key', array('unique' => TRUE));
        }
      }

      $qdata = array();
      foreach (explode('&', $query) as $part) {
        list($k, $v) = explode('=', $part, 2);
        $qdata[$k] = urldecode($v);
      }


      if (!empty($qdata['site_key']) && !empty($qdata['version'])) {
        $rkey = strtolower($project . ':' . $qdata['version']);
        if (isset($releasedata[$rkey]) && isset($rterms[$core]) && ($rterms[$core] == $releasedata[$rkey][2])) {
          $rd = $releasedata[$rkey];
          $entry = array(
            'site_key' => strtolower($qdata['site_key']),
            'ip' => $ip,
            'project' => $rd[0],
            'core' => $rd[2],
            'timestamp' => $timestamp,
            'release' => $rd[1],
          );

          $bcnt++;
          $buffer[$qdata['site_key']][] = $entry;

          // Tune the 32768 as necessary. Sites checking in will generally
          // leave their hits in the logfile in close proximity. We can often
          // aggregate several of the hits from a site into a single update, if
          // they appear in the same "chunk". This is purely for efficiency
          // reasons, using the "wrong" number will at worst slow down
          // statistics processing. 32768 was found to do reasonably well for
          // updates.drupal.org.
          if ($bcnt >= 32768) {
            project_usage_process_batch($c, $buffer, $st);
            $bcnt = 0;
            $buffer = array();
          }
        }
        else {
          $st['@wrongcore']++;
        }
      }
      else {
        $st['@invalid']++;
      }
    }
  }
  if ($bcnt > 0) {
    // Process the remaining partial buffer.
    project_usage_process_batch($c, $buffer, $st);
  }

  fclose($handle);
  $time2 = microtime(2);

  $st['@time'] = ($time2 - $time1);
  drush_log(dt("Update statistics for @file (Run time:@time seconds): File totals: change: @change, nochange: @nochange, wrongcore: @wrongcore, invalid: @invalid", $st), 'ok');
}

/**
 * Get a MongoDB reference.
 */
function project_usage_mongo() {
  static $db;
  if (!$db) {
    $conn = new Mongo(variable_get('project_usage_mongo_server', 'mongodb://localhost:27017'));
    $db = $conn->selectDB("update-statistics");
  }
  return $db;
}

/**
 * Get a lookup table.
 *
 * Information about cores, projects and releases are loaded once and cached in
 * memory for speed.
 */
function project_usage_lookup($type = 'terms') {
  $data = drupal_static(__FUNCTION__, array());

  if (empty($data)) {
    // Terms.
    $db = project_usage_mongo();
    $c = $db->selectCollection('terms');
    $cursor = $c->find();
    foreach ($cursor as $row) {
      $data['terms'][$row['tid']] = $row['name'];
      $data['rterms'][$row['name']] = $row['tid'];
    }

    // Projects.
    $c = $db->selectCollection('projects');
    $cursor = $c->find();
    foreach ($cursor as $row) {
      $data['projdata'][$row['uri']] = $row['pid'];
    }

    // Releases.
    $c = $db->selectCollection('releases');
    $cursor = $c->find();
    foreach ($cursor as $r) {
      $row = (object) $r;
      $data['releasedata']["{$row->uri}:{$row->version}"] = array(
        $row->pid,
        $row->nid,
        $row->tid,
      );
    }
  }
  return isset($data[$type]) ? $data[$type] : FALSE;
}

/**
 * Feed a batch of processed data into mongo.
 */
function project_usage_process_batch(&$c, $buffer, &$st) {
  // Buffers are broken down by site so we don't have to jump back and forth
  // between sites while talking to mongo. We can for the most part do things
  // a site at a time.
  foreach ($buffer as $site_key => $data) {
    if (!drupal_validate_utf8($site_key)) {
      drush_log(dt("Skipping corrupted site!"), 'warning');
      continue;
    }
    $lasthit = $data[count($data) - 1];
    $changed = FALSE;
    $record = $c->findOne(array('site_key' => $site_key));
    if (!$record) {
      $record = array(
        'site_key' => $site_key,
        'modules' => array(),
      );
      $changed = TRUE;
    }

    // IP accounting is somewhat minimal, and is used to allow cleaning up
    // after a remote site calls in repeatedly with different site_keys.
    // This is pretty minimal -- it doesn't check for multiple ips, and only
    // updates with the rest of the record.
    $record['ip'] = $lasthit['ip'];

    // Core also needs to be tracked in a similar fashion for determining the
    // "correct" core for end-of-week.
    $record['core'] = $lasthit['core'];

    foreach ($data as $entry) {
      if (!isset($record['modules'][$entry['project']])) {
        // If it's the first time this week, just add it.
        $record['modules'][$entry['project']] = array(
          'timestamp' => $entry['timestamp'],
          'release' => $entry['release'],
          'core' => $entry['core'],
        );
        $changed = TRUE;
      }
      else {
        // If the entry on file is older than the incoming one...
        if ($record['modules'][$entry['project']]['timestamp'] < $entry['timestamp']) {
          // Change it.
          $record['modules'][$entry['project']] = array(
            'timestamp' => $entry['timestamp'],
            'release' => $entry['release'],
            'core' => $entry['core'],
          );
          $changed = TRUE;
        }
      }
    }
    if ($changed) {
      $c->save($record);
      $st['@change']++;
    }
    else {
      $st['@nochange']++;
    }
  }
}

/**
 * Load project metadata into the database so we have fastish access to it.
 */
function project_usage_load_metadata() {
  static $initialized = FALSE;
  if ($initialized) {
    return;
  }
  $initialized = TRUE;
  $db = project_usage_mongo();

  // Load information about core.
  $c = $db->selectCollection("terms");
  $c->remove(array());
  foreach (taxonomy_get_tree(variable_get('project_release_api_vocabulary', '')) as $term) {
    // Quick ugly hack to skip drupal < 5.x.
    if (strlen($term->name) == 3) {
      $c->insert(array(
        'tid' => $term->tid,
        'name' => $term->name,
      ));
    }
  }

  // Load information about projects.
  $c = $db->selectCollection("projects");
  $c->remove(array());
  $projdata = array();
  $query = new EntityFieldQuery();
  $result = $query->entityCondition('entity_type', 'node')
    ->entityCondition('bundle', project_project_node_types())
    ->execute();
  if (isset($result['node'])) {
    foreach (array_chunk(array_keys($result['node']), 100) as $projects) {
      foreach (node_load_multiple($projects, array(), TRUE) as $project) {
        $c->insert(array(
          'pid' => $project->nid,
          'uri' => $project->field_project_machine_name[LANGUAGE_NONE][0]['value'],
        ));
        $projdata[$project->nid] = $project->field_project_machine_name[LANGUAGE_NONE][0]['value'];
      }
    }
  }

  // Load information about releases.
  $c = $db->selectCollection("releases");
  $c->remove(array());
  $api_vocabulary = taxonomy_vocabulary_load(variable_get('project_release_api_vocabulary', ''));
  $query = new EntityFieldQuery();
  $result = $query->entityCondition('entity_type', 'node')
    ->entityCondition('bundle', project_release_release_node_types())
    ->execute();
  if (isset($result['node'])) {
    foreach (array_chunk(array_keys($result['node']), 100) as $releases) {
      foreach (node_load_multiple($releases, array(), TRUE) as $release) {
        $row = array(
          'uri' => $temp_projdata[$release->field_release_project[LANGUAGE_NONE][0]['target_id']],
          'nid' => $release->nid,
          'pid' => $release->field_release_project[LANGUAGE_NONE][0]['target_id'],
          'version' => $release->field_release_version[LANGUAGE_NONE][0]['value'],
          'tid' => isset($release->{'taxonomy_' . $api_vocabulary->machine_name}[LANGUAGE_NONE]) ? $release->{'taxonomy_' . $api_vocabulary->machine_name}[LANGUAGE_NONE][0]['tid'] : 0,
        );
        $data['releasedata']["{$row->uri}:{$row->version}"] = array(
          $row->pid,
          $row->nid,
          $row->tid,
        );
        if ($use_mongo) {
          // Load the row in mongo before we throw it away.
          $c->insert($row);
        }
      }
    }
  }
}

/**
 * Open a file, transparently handling compression.
 *
 * @param $filename
 *
 * @return resource
 */
function _project_usage_open_file($filename) {
  if (substr($filename, -3) == '.gz') {
    return fopen('compress.zlib://' . $filename, 'r');
  }
  return fopen($filename, 'r');
}

/**
 * Remove old site records from mongo.
 *
 * (Only bother with the last 3 expired ones, if stats haven't been run in more
 * than 3 weeks, you can delete the rest yourself.)
 */
function project_usage_expire_statfiles() {
  $db = project_usage_mongo();
  $week = project_usage_weekly_timestamp(NULL, -(PROJECT_USAGE_SHOW_WEEKS + 4));
  drush_log(dt("Cleaning up @week", array('@week' => $week)));
  $db->dropCollection($week);
  $week = project_usage_weekly_timestamp(NULL, -(PROJECT_USAGE_SHOW_WEEKS + 3));
  drush_log(dt("Cleaning up @week", array('@week' => $week)));
  $db->dropCollection($week);
  $week = project_usage_weekly_timestamp(NULL, -(PROJECT_USAGE_SHOW_WEEKS + 2));
  drush_log(dt("Cleaning up @week", array('@week' => $week)));
  $db->dropCollection($week);
}

/**
 * Run the counts for projects and releases, and update the database.
 */
function project_usage_tally() {
  $projdata = project_usage_lookup('projdata');
  $releasedata = project_usage_lookup('releasedata');
  $terms = project_usage_lookup('terms');

  $db = project_usage_mongo();

  drush_log(dt("Tallying projects."));

  $weeks = project_usage_get_weeks_since(project_usage_weekly_timestamp(NULL, -PROJECT_USAGE_SHOW_WEEKS));

  // Skip the current week.
  array_pop($weeks);

  foreach ($weeks as $week) {
    $tally = array();
    $ptally = array();
    $release_proj = array();
    foreach ($terms as $tid => $name) {
      $tally[$tid] = array();
      $ptally[$tid] = array();
    }
    $c = $db->selectCollection("$week");
    $cursor = $c->find();
    foreach ($cursor as $record) {
      foreach ($record['modules'] as $project => $data) {
        if ($data['core'] != $record['core']) {
          // Site changed core in the middle of the week, and this entry is for
          // the "old" site, disregard it.
          continue;
        }
        if (!isset($tally[$record['core']][$data['release']])) {
          $tally[$record['core']][$data['release']] = 0;
        }
        if (!isset($ptally[$record['core']][$project])) {
          $ptally[$record['core']][$project] = 0;
        }
        $tally[$record['core']][$data['release']]++;
        $ptally[$record['core']][$project]++;
        $release_proj[$data['release']] = $project;
      }
    }

    foreach ($ptally as $tid => $projects) {
      drush_log(dt("Begin updating @week tallies on database for @name", array('@week' => $week, '@name' => $terms[$tid])));
      if (!empty($projects)) {
        db_delete('project_usage_week_project')
          ->condition('tid', $tid)
          ->condition('timestamp', $week)
          ->execute();
        $qcount = 0;
        $query = db_insert('project_usage_week_project')
          ->fields(array('nid', 'timestamp', 'tid', 'count'));
        foreach ($projects as $project => $count) {
          $qcount++;
          $query->values(array(
            'nid' => $project,
            'timestamp' => $week,
            'tid' => $tid,
            'count' => $count,
          ));

          if ($qcount > 1000) {
            $query->execute();
            $qcount = 0;
            $query = db_insert('project_usage_week_project')
              ->fields(array('nid', 'timestamp', 'tid', 'count'));
          }
        }
        if ($qcount) {
          $query->execute();
        }
      }
    }

    $updateweek = FALSE;
    foreach ($tally as $tid => $releases) {
      if (!empty($releases)) {
        $updateweek = TRUE;
      }
    }
    if ($updateweek) {
      // If we have release data for this week, then clear out the week in the
      // database to avoid duplicate key errors. Do the update in a transaction
      // to prevent glitches if someone requests stats while we're writing.
      db_query("START TRANSACTION");

      db_delete('project_usage_week_release')
        ->condition('timestamp', $week)
        ->execute();
    }

    foreach ($tally as $tid => $releases) {
      if (!empty($releases)) {
        $qcount = 0;
        $query = db_insert('project_usage_week_release')
          ->fields(array('nid', 'timestamp', 'count'));
        foreach ($releases as $release => $count) {
          $qcount++;
          $query->values(array(
            'nid' => $release,
            'timestamp' => $week,
            'count' => $count,
          ));

          if ($qcount > 1000) {
            $query->execute();
            $qcount = 0;
            $query = db_insert('project_usage_week_release')
              ->fields(array('nid', 'timestamp', 'count'));
          }
        }
        if ($qcount) {
          $query->execute();
        }
      }
    }

    if ($updateweek) {
      db_query('COMMIT');
    }

    if (empty($ptally[$week])) {
      drush_log(dt("Skipping @week tallies -- no data for time period.", array('@week' => $week)));
    }
    drush_log(dt("End updating @week tallies on database.", array('@week' => $week)));
  }
}

/**
 * Gets data about projects, releases, and core versions.
 */
function project_usage_retrieve_metadata() {

  static $data = array();

  if (empty($data)) {

    // Load core api versions.
    foreach (taxonomy_get_tree(variable_get('project_release_api_vocabulary', '')) as $term) {
      // Quick ugly hack to skip drupal < 5.x.
      if (strlen($term->name) == 3) {
        $coreterms[$term->name] = $term->tid;
      }
    }

    // Load information about projects.
    $projdata = array();
    $query = new EntityFieldQuery();
    $result = $query->entityCondition('entity_type', 'node')
      ->entityCondition('bundle', project_project_node_types())
      ->fieldCondition('field_project_type', 'value', 'full', '=')
      ->fieldCondition('field_project_has_releases', 'value', '1', '=')
      ->execute();
    foreach (project_project_node_types() as $bundle) {
      $fields = field_info_instances('node', $bundle);
      $machine_name_id[$bundle] = $fields['field_project_machine_name']['field_id'];
    }
    if (isset($result['node'])) {
      foreach (array_chunk($result['node'], 100) as $projects) {
        foreach ($projects as $project) {
          field_attach_load('node', array($project->nid => $project), FIELD_LOAD_CURRENT, array('field_id' => $machine_name_id[$project->type]));
          // Language 'und' is very d.o. specific. loading the language takes
          // time for 19000 nodes.
          $project_name = $project->field_project_machine_name['und'][0]['value'];
          $projnames[$project->nid] = $project_name;

          $projnids[$project_name] = $project->nid;
        }
      }
    }

    // Load information about releases.
    $api_vocabulary = taxonomy_vocabulary_load(variable_get('project_release_api_vocabulary', ''));
    $query = new EntityFieldQuery();
    $result = $query->entityCondition('entity_type', 'node')
      ->entityCondition('bundle', project_release_release_node_types())
      ->fieldCondition('taxonomy_vocabulary_6', 'tid', array_values($coreterms))
      ->fieldCondition('field_release_vcs_label', 'value', 'master', '<>')
      ->execute();
    if (isset($result['node'])) {
      foreach (array_chunk(array_keys($result['node']), 100) as $releases) {
        foreach (node_load_multiple($releases, array(), TRUE) as $release) {
          $project_name = $projnames[$release->field_release_project[LANGUAGE_NONE][0]['target_id']];
          $releasedata[$project_name]['projectname'] = $project_name;
          $releasedata[$project_name]['project_nid'] = $release->field_release_project[LANGUAGE_NONE][0]['target_id'];
          $releasedata[$project_name][$release->field_release_version[LANGUAGE_NONE][0]['value']]['release_nid'] = $release->nid;
          $releasedata[$project_name][$release->field_release_version[LANGUAGE_NONE][0]['value']]['release_compatibility'] = isset($release->{'taxonomy_' . $api_vocabulary->machine_name}[LANGUAGE_NONE]) ? $release->{'taxonomy_' . $api_vocabulary->machine_name}[LANGUAGE_NONE][0]['tid'] : 0;
        }
      }
    }

    $data['terms'] = $coreterms;
    $data['projects'] = $projnids;
    $data['releases'] = $releasedata;
  }
  return $data;
}

/**
 * Run the counts for projects and releases, and update the database.
 */
function drush_project_usage_import_usage_stats($week) {
  module_load_include('inc', 'project_usage', 'includes/date_api');
  drush_log(dt("Starting @week tallies on database.", array('@week' => $week)));


  $count_file_path = drush_get_option('count-file-path');
  if (!$count_file_path) {
    $count_file_path = variable_get('project_usage_count_file_path', '/var/log/updatestats/counts');
  }

  ini_set('memory_limit', '-1');
  $metadata = project_usage_retrieve_metadata();

  // Project release counts.
  drush_log(dt("Importing Release stats."));

  $project_releasecounts_fh = _project_usage_open_file($count_file_path . "/" . $week . '.releasecounts');

  // Clear out existing data.
  db_delete('project_usage_week_release')
    ->condition('timestamp', $week)
    ->execute();
  // Load in new counts.
  $qcount = 0;
  $query = db_insert('project_usage_week_release')
    ->fields(array('nid', 'timestamp', 'count'));
  while ($line = fgets($project_releasecounts_fh)) {
    $nid = 0;
    $term = 0;
    // Split up $line into its components.
    list($count, $pdata) = explode(' ', trim($line), 2);
    list($project_machine_name, $rel_ver, $maj_api) = explode('|', $pdata, 3);

    // Skip projects that are not ours.
    if (substr($rel_ver, 0, 2) !== substr($maj_api, 0, 2) || empty($metadata['releases'][$project_machine_name][$rel_ver])) {
      continue;
    }
    else {
      // Add up major api counts.
      $project_api_counts[$project_machine_name][$maj_api] += $count;
    }
    $term = $metadata['terms'][$maj_api];
    $nid = $metadata['releases'][$project_machine_name][$rel_ver]['release_nid'];

    if (!empty($nid) && !empty($term)) {
      $qcount++;

      $query->values(array(
        'nid' => $nid,
        'timestamp' => $week,
        'count' => $count,
      ));
    }

    if ($qcount > 1000) {
      $query->execute();
      $qcount = 0;
      $query = db_insert('project_usage_week_release')
        ->fields(array('nid', 'timestamp', 'count'));
    }

  }
  if ($qcount) {
    $query->execute();
  }
  fclose($project_releasecounts_fh);


  drush_log(dt("Importing Project stats."));

  // Clear out existing data.
  db_delete('project_usage_week_project')
    ->condition('timestamp', $week)
    ->execute();
  // Load in new counts.
  $qcount = 0;
  $query = db_insert('project_usage_week_project')
    ->fields(array('nid', 'timestamp', 'tid', 'count'));

  // Update the project major API counts based on the aggregated counts from
  // the releases.
  foreach ($project_api_counts as $project_machine_name => $maj_apis) {
    foreach ($maj_apis as $maj_api => $count) {
      $nid = 0;
      $term = 0;
      drush_log(dt("Begin updating @week tallies on database", array('@week' => $week)));

      $nid = $metadata['projects'][$project_machine_name];
      $term = $metadata['terms'][$maj_api];
      if (!empty($nid) && !empty($term)) {
        $qcount++;
        $query->values(array(
          'nid' => $nid,
          'timestamp' => $week,
          'tid' => $term,
          'count' => $count,
        ));
      }

      if ($qcount > 1000) {
        $query->execute();
        $qcount = 0;
        $query = db_insert('project_usage_week_project')->fields(array(
          'nid',
          'timestamp',
          'tid',
          'count'
        ));
      }

    }
  }
  if ($qcount) {
    $query->execute();
  }

  drush_log(dt("End updating   @week tallies on database.", array('@week' => $week)));
  project_usage_get_active_weeks(TRUE);

}
