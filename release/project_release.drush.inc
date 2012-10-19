<?php

/**
 * @file
 * Drush command for packaging files for release nodes.
 *
 * @author Derek Wright (http://drupal.org/user/46549)
 */

function project_release_drush_command() {
  return array(
    'release-package' => array(
      'description' => 'Package files for release nodes.',
      'arguments' => array(
        'type' => '"branch" or "tag", required.',
        'project_id' => 'Project, either a Node ID or machine name. Omit to build all projects.'
      ),
    ),
  );
}

function project_release_drush_help($section) {
  switch ($section) {
    case 'meta:project_release:title':
      return dt('Project release commands');

    case 'drush:release-package':
      return dt('Package files for release nodes.');
  }
}

function drush_project_release_release_package_validate($type = NULL, $project_id = NULL) {
  if ($type !== 'tag' && $type !== 'branch') {
    return drush_set_error('INVALID_TYPE', dt('First type must be "branch" or "tag".'));
  }
}

function drush_project_release_release_package($type, $project_id = NULL) {
  // Force the right umask while this script runs, so that everything is created
  // with sane file permissions.
  umask(0022);

  // Load the include file for packager-related helper functions.
  module_load_include('inc', 'project_release', 'includes/packager');

  // Initialize temporary directory.
  $tmp_dir = drush_tempdir();

  // If specified, find the project.
  if (!is_null($project_id)) {
    $project_node = project_load($project_id);
    if ($project_node === FALSE) {
      return drush_set_error('INVALID_PROJECT', dt('ERROR: Project ID @id not found', array('@id' => $project_id)));
    }
  }

  $query = new EntityFieldQuery();
  $query->entityCondition('entity_type', 'node')
    ->entityCondition('bundle', 'project_release');
  if ($type === 'tag') {
    $query->fieldCondition('field_release_build_type', 'value', 'static');
    $where = " AND (f.filepath IS NULL OR f.filepath = '')";
    $plural = t('tags');
  }
  elseif ($type === 'branch') {
    $query->fieldCondition('field_release_build_type', 'value', 'dynamic');
    $rel_node_join = " INNER JOIN {node} nr ON prn.nid = nr.nid";
    $where = " AND ((f.filepath IS NULL) OR (f.filepath = '') OR (nr.status = %d))";
    $where_args[] = 1;  // nr.status
    $plural = t('branches');
    if (empty($project_node)) {
      wd_msg("Starting to package all snapshot releases.");
    }
    else {
      wd_msg("Starting to package snapshot releases for project id: %project_short_name.", array('%project_short_name' => $project_node->project['uri']), l(t('view'), 'node/' . $project_node->nid));
    }
  }
  $args = array();
  $args[] = 1;    // Account for np.status = 1.
  $args[] = 1;    // Account for prp.releases = 1.
  if (!empty($project_node)) {
    $where .= ' AND prn.pid = %d';
    $where_args[] = $project_node->nid;
  }
  $args = array_merge($args, $where_args);
  $query = db_query("SELECT prn.nid FROM {project_release_nodes} prn $rel_node_join LEFT JOIN {project_release_file} prf ON prn.nid = prf.nid LEFT JOIN {files} f ON prf.fid = f.fid INNER JOIN {project_projects} pp ON prn.pid = pp.nid INNER JOIN {node} np ON prn.pid = np.nid INNER JOIN {project_release_projects} prp ON prp.nid = prn.pid WHERE np.status = %d AND prp.releases = %d " . $where . ' ORDER BY pp.uri', $args);
}

/*
// This assumes you want to install the packaged releases in the
// "files/projects" directory of your root Drupal installation. If
// that's not the case, you should customize these.
$dest_root = $drupal_root;
$dest_rel = 'files/projects';

// If you are using project-release-create-history.php to generate XML release
// history files, if you include the full path to your copy of that script
// here, after all the packages are re(generated), this script will regenerate
// the XML release history files for any projects with new/updated releases.
$project_release_create_history = '';

function package_releases($type, $project_id = 0) {
  global $dest_root, $dest_rel, $wd_err_msg;
  global $php, $project_release_create_history;

  $num_built = 0;
  $num_considered = 0;
  $project_nids = array();

  // Read everything out of the query immediately so that we don't leave the
  // query object/connection open while doing other queries.
  $releases = array();
  while ($release = db_fetch_object($query)) {
    // This query could pull multiple rows of the same release since multiple
    // files per release node are allowed. Account for this by keying on
    // release nid.
    $releases[$release->nid] = $release->nid;
  }
  foreach ($releases as $release_nid) {
    $wd_err_msg = array();

    // We don't want to waste too much RAM by leaving all these loaded nodes
    // in RAM, so we reset the node_load() cache each time we call it.
    $release_node = node_load($release_nid, NULL, TRUE);
    if (empty($release_node)) {
      wd_err("ERROR: Can't load release node for release ID %nid", array('%nid' => $release_nid));
      continue;
    }

    // Load the corresponding project node.
    $project_node = project_release_packager_node_load($release_node->project_release['pid']);

    $packager = project_release_get_packager_plugin($release_node, $dest_root, $dest_rel, $tmp_dir);
    if (empty($project_node) || empty($packager)) {
      wd_err("ERROR: Can't find packager plugin to use for %release", array('%release' => $release_node->title));
      continue;
    }

    db_query("DELETE FROM {project_release_package_errors} WHERE nid = %d", $release_node->nid);

    $files = array();
    $rval = $packager->createPackage($files);
    $num_considered++;

    switch ($rval) {
      case 'success':
      case 'rebuild':
        project_release_packager_update_node($release_node, $dest_root, $files);
        module_invoke_all('project_release_create_package', $project_node, $release_node);
        $num_built++;
        $packager->cleanupSuccessfulBuild();
        $release_pid = $release_node->project_release['pid'];
        $project_nids[$release_pid] = TRUE;
        $release_node_view_link = l(t('View'), 'node/' . $release_node->nid);
        if ($rval == 'rebuild') {
          $msg = '%release_title has changed, re-packaged.';
        }
        else {
          $msg = 'Packaged %release_title.';
        }
        wd_msg($msg, array('%release_title' => $release_node->title), $release_node_view_link);
        break;

      case 'error':
        $packager->cleanupFailedBuild();
        break;

    }

    if (count($wd_err_msg)) {
      db_query("INSERT INTO {project_release_package_errors} (nid, messages) values (%d, '%s')", $release_node->nid, serialize($wd_err_msg));
    }
  }

  if ($num_built || $type == 'branch') {
    if (!empty($project_node)) {
      wd_msg("Done packaging releases for @project_short_name from !plural: !num_built built, !num_considered considered.", array('@project_short_name' => $project_node->project['uri'], '!plural' => $plural, '!num_built' => $num_built, '!num_considered' => $num_considered));
    }
    else {
      wd_msg("Done packaging releases from !plural: !num_built built, !num_considered considered.", array('!plural' => $plural, '!num_built' => $num_built, '!num_considered' => $num_considered));
    }
  }

  // Finally, regenerate release history XML files for all projects we touched.
  if (!empty($project_nids) && !empty($project_release_create_history)) {
    wd_msg('Re-generating release history XML files');
    $i = $fails = 0;
    foreach ($project_nids as $project_nid => $value) {
      if (drupal_exec("$php $project_release_create_history $project_nid")) {
        $i++;
      }
      else {
        $fails++;
      }
    }
    if (!empty($fails)) {
      wd_msg('ERROR: Failed to re-generate release history XML files for !num project(s)', array('!num' => $fails));
    }
    wd_msg('Done re-generating release history XML files for !num project(s)', array('!num' => $i));
  }
}

// ------------------------------------------------------------
// Functions: utility methods
// ------------------------------------------------------------

/**
 * Wrapper for exec() that logs errors to the watchdog.
 * @param $cmd
 *   String of the command to execute (assumed to be safe, the caller is
 *   responsible for calling escapeshellcmd() if necessary).
 * @return true if the command was successful (0 exit status), else false.
 */
function drupal_exec($cmd) {
  // Made sure we grab stderr, too...
  exec("$cmd 2>&1", $output, $rval);
  if ($rval) {
    wd_err("ERROR: %cmd failed with status !rval" . '<pre>' . implode("\n", array_map('htmlspecialchars', $output)), array('%cmd' => $cmd, '!rval' => $rval));
    return false;
  }
  return true;
}

/**
 * Wrapper for chdir() that logs errors to the watchdog.
 * @param $dir Directory to change into.
 * @return true if the command was successful (0 exit status), else false.
 */
function drupal_chdir($dir) {
  if (!chdir($dir)) {
    wd_err("ERROR: Can't chdir(@dir)", array('@dir' => $dir));
    return false;
  }
  return true;
}

/**
 * Wrapper function for watchdog() to log notice messages. Uses a
 * different watchdog message type depending on the type (branch vs. tag).
 */
function wd_msg($msg, $variables = array(), $link = NULL) {
  global $type;
  watchdog('package_' . $type, $msg, $variables, WATCHDOG_NOTICE, $link);
  echo t($msg, $variables) . "\n";
}

/**
 * Wrapper function for watchdog() to log error messages.
 */
function wd_err($msg, $variables = array(), $link = NULL) {
  global $wd_err_msg;
  if (!isset($wd_err_msg)) {
    $wd_err_msg = array();
  }
  watchdog('package_error', $msg, $variables, WATCHDOG_ERROR, $link);
  echo t($msg, $variables) . "\n";
  $wd_err_msg[] = t($msg, $variables);
}
