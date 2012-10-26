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
      'required-arguments' => TRUE,
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

function drush_project_release_release_package($type, $project_id) {
  // Force the right umask while this script runs, so that everything is created
  // with sane file permissions.
  umask(0022);

  // Load the include file for packager-related helper functions.
  module_load_include('inc', 'project_release', 'includes/packager');

  // Initialize temporary directory.
  $tmp_dir = drush_tempdir();

  // Find the project.
  $project_node = project_load($project_id);
  if ($project_node === FALSE) {
    return drush_set_error('INVALID_PROJECT', dt('ERROR: Project ID @id not found', array('@id' => $project_id)));
  }
  if ($project_node->status != NODE_PUBLISHED) {
    return drush_set_error('UNPUBLISHED_PROJECT', dt('ERROR: Project @id not published', array('@id' => $project_id)));
  }
  if (!$project_node->field_project_has_releases[$project_node->language][0]['value']) {
    return drush_set_error('NO_RELEASES_PROJECT', dt('ERROR: Project @id does not have releases', array('@id' => $project_id)));
  }

  $num_built = 0;
  $num_considered = 0;

  // Find release nodes.
  $query = new EntityFieldQuery();
  $result = $query->entityCondition('entity_type', 'node')
    ->entityCondition('bundle', 'project_release')
    ->fieldCondition('field_release_project', 'target_id', $project_node->nid)
    ->fieldCondition('field_release_build_type', 'value', $type === 'tag' ? 'static' : 'dynamic')
    ->execute();
  if (empty($result)) {
    drush_log(dt('No !type releases found for @id.', array('!type' => $type, '@id' => $project_id)), 'warning');
    return;
  }
  foreach (array_keys($result['node']) as $release_nid) {
    // We don't want to waste too much RAM by leaving all these loaded nodes
    // in RAM, so we reset the node_load() cache each time we call it.
    $release_node = node_load($release_nid, NULL, TRUE);
    if (empty($release_node)) {
      watchdog('package_error', "Can't load release node for release ID %nid", array('%nid' => $release_nid), WATCHDOG_ERROR);
      continue;
    }

    // If file is not empty, a tag release is already packaged.
    if ($type === 'tag' && !empty($release_node->field_release_files)) {
      continue;
    }
    // If published and file is not empty, a branch release is already
    // packaged. Published releases may be repackaged.
    if ($type === 'branch' && $release_node->status == NODE_PUBLISHED && !empty($release_node->field_release_files)) {
      continue;
    }

    // Find the packager plugin.
    $packager = project_release_get_packager_plugin($release_node, $tmp_dir);
    if ($packager === FALSE) {
      watchdog('package_error', "Can't find packager plugin to use for %release", array('%release' => $release_node->title), WATCHDOG_ERROR);
      continue;
    }

    // Delete existing log messages.
    db_delete('project_release_package_errors')
      ->condition('nid', $release_node->nid)
      ->execute();

    /*
    $files = array();
    $rval = $packager->createPackage($files);
    $num_considered += 1;

    switch ($rval) {
      case 'success':
      case 'rebuild':
        project_release_packager_update_node($release_node, $files);
        module_invoke_all('project_release_create_package', $project_node, $release_node);
        $num_built += 1;
        $packager->cleanupSuccessfulBuild();
        $release_pid = $release_node->project_release['pid'];
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
    */
  }

  /*
  if ($num_built || $type == 'branch') {
    wd_msg("Done packaging !type releases for @project_short_name: !num_built built, !num_considered considered.", array('@project_short_name' => $project_node->project['uri'], '!type' => $type, '!num_built' => $num_built, '!num_considered' => $num_considered));
  }

  // Finally, regenerate release history XML files for all projects we touched.
  if (!empty($project_release_create_history)) {
    wd_msg('Re-generating release history XML files');
    if (drupal_exec("$php $project_release_create_history " . $project_node->nid)) {
      wd_msg('Done re-generating release history XML files');
    }
    else {
      wd_msg('ERROR: Failed to re-generate release history XML files');
    }
  }
  */
}
