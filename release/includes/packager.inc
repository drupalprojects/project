<?php

/**
 * @file Code required during release node packaging.
 *
 * In addition to some shared helper functions, this file defines the
 * interface that all CTools release_packager plugins must implement.
 *
 */

interface ProjectReleasePackagerInterface {
  /**
   * Constructor for a packager plugin class.
   *
   * @param stdClass $release_node
   *   The fully-loaded release node to create package(s) for.
   * @param string $temp_directory
   *   The full path to a temporary directory to use during packaging.
   */
  public function __construct($release_node, $temp_directory);

  /**
   * Create package(s) for the given release.
   *
   * @param array $files
   *   Reference to an associative array of files created for this release.
   *   The keys are the filename (relative to the root of the Drupal
   *   installation) and the values are the {project_release_files}.weight for
   *   each file.
   *
   * @return string
   *   The results of the packaging attempt. Must be one of:
   *   - 'success': Everything worked and new package(s) were created
   *   - 'rebuilt': Everything worked and package(s) were rebuilt
   *   - 'error': There was an error generating package(s)
   *   - 'no-op': There was nothing to do to package the given release
   */
  public function createPackage(&$files);
}

/**
 * Find the approriate packager CTools plugin for the given release.
 *
 * First, we see if $release_node->project_release['packager_plugin'] is
 * defined, and if so, we use that. Otherwise, we load the project the
 * release is attached to, and check in the project node to see if
 * $project_node->project_release['packager_plugin'] is defined. Then, we see
 * if there's a project-type-specific site-wide packager plugin variable
 * defined. Finally, we look for a global site-wide default plugin variable.
 *
 * Once we find the appropriate plugin name to use, we ask CTools to give us
 * the right class to use for that plugin, and try to instantiate an object.
 *
 * @param $release_node
 *   The fully-loaded release node to find the packaging plugin for.
 * @param string $temp_directory
 *   The full path to a temporary directory to use during packaging.
 *
 * @return
 *   An instantiated packager plugin for the given release node, or FALSE if
 *   we couldn't find or instantiate the right plugin.
 */
function project_release_get_packager_plugin($release_node, $temp_directory) {
  $packager_plugin = '';
  // If this particular release has its own plugin defined, use it.
  if (!empty($release_node->project_release['packager_plugin'])) {
    $packager_plugin = $release_node->project_release['packager_plugin'];
  }
  else {
    $project_node = node_load(project_release_get_release_project_nid($release_node));
    // If this particular project has its own plugin defined, use it.
    if (!empty($project_node->project_release['packager_plugin'])) {
      $packager_plugin = $project_node->project_release['packager_plugin'];
    }
    // Otherwise, look for a project-type-specific pluging variable.
    else {
      $packager_plugin = variable_get('project_release_packager_plugin_' . $project_node->type, '');
    }
    // If we still don't know the plugin to use, try the site-wide default.
    if (empty($packager_plugin)) {
      $packager_plugin = variable_get('project_release_packager_plugin', '');
    }
  }
  if (!empty($packager_plugin)) {
    ctools_include('plugins');
    if ($class = ctools_plugin_load_class('project_release', 'release_packager', $packager_plugin, 'packager')) {
      // Make sure the plugin implements the right interface.
      if (in_array('ProjectReleasePackagerInterface', class_implements($class, TRUE))) {
        return new $class($release_node, $temp_directory);
      }
    }
  }
  return FALSE;
}

/**
 * Update the DB with the new file info for a given release node.
 *
 * @param $release_node
 *   The fully loaded release node to update.
 * @param $files
 *   Array of files to add to the release node. The keys are filenames, and
 *   the values are the weight for the {project_release_file}.weight column.
 */
function project_release_packager_update_node($release_node, $files = array()) {
  // If the site is using DB replication, force this node_load() to use the
  // primary database to avoid node_load() failures.
  db_ignore_slave();

  if (!empty($files)) {
    // PHP will cache the results of stat() and give us stale answers
    // here, unless we manually tell it otherwise!
    clearstatcache();

    foreach ($files as $full_path => $file_weight) {
      /* todo update $release_node object for node_save()
      // Compute the metadata for this file that we care about.
      $file_name = basename($full_path);
      $file_date = filemtime($full_path);
      $file_size = filesize($full_path);
      $file_hash = md5_file($full_path);
      $file_mime = file_get_mimetype($full_path);

      // First, see if we already have this file for this release node
      $file_data = db_fetch_object(db_query("SELECT prf.* FROM {project_release_file} prf INNER JOIN {files} f ON prf.fid = f.fid WHERE prf.nid = %d AND f.filename = '%s'", $release_node->nid, $file_name));

      // Insert or update the record in the DB as need.
      if (empty($file_data)) {
        // Don't have this file, insert a new record.
        db_query("INSERT INTO {files} (uid, filename, filepath, filemime, filesize, status, timestamp) VALUES (%d, '%s', '%s', '%s', %d, %d, %d)", $release_node->uid, $file_name, $file_path, $file_mime, $file_size, FILE_STATUS_PERMANENT, $file_date);
        $fid = db_last_insert_id('files', 'fid');
        db_query("INSERT INTO {project_release_file} (fid, nid, filehash, weight) VALUES (%d, %d, '%s', %d)", $fid, $release_node->nid, $file_hash, $file_weight);
      }
      else {
        // Already have this file for this release, update it.
        db_query("UPDATE {files} SET uid = %d, filename = '%s', filepath = '%s', filemime = '%s', filesize = %d, status = %d, timestamp = %d WHERE fid = %d", $release_node->uid, $file_name, $file_path, $file_mime, $file_size, FILE_STATUS_PERMANENT, $file_date, $file_data->fid);
        db_query("UPDATE {project_release_file} SET filehash = '%s', weight = %d WHERE fid = %d", $file_hash, $file_weight, $file_data->fid);
      }
       */
    }
  }

  // Don't auto-publish security updates.
  if (project_release_is_security_update($release_node)) {
    watchdog('package_security', 'Not auto-publishing security update release.', array(), WATCHDOG_NOTICE, l(t('view'), 'node/' . $release_node->nid));
  }
  // Finally publish the node if it is currently unpublished.
  elseif ($release_node->status == NODE_NOT_PUBLISHED) {
    $release_node->status = NODE_PUBLISHED;
  }

  node_save($release_node);
}
