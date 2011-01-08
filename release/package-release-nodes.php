#!/usr/bin/php
<?php

// $Id$

/**
 * @file
 * Automated packaging script to generate tarballs from release nodes.
 *
 * @author Derek Wright (http://drupal.org/user/46549)
 */

// ------------------------------------------------------------
// Required customization
// ------------------------------------------------------------

// The root of your Drupal installation, so we can properly bootstrap
// Drupal. This should be the full path to the directory that holds
// your index.php file, the "includes" subdirectory, etc.
$drupal_root = '';

// The name of your site. Required so that when we bootstrap Drupal in
// this script, we find the right settings.php file in your sites folder.
// For example, on drupal.org:
// $site_name = 'drupal.org';
$site_name = '';

// The CVSROOT for the repository this script will be packaging
// releases from. For example, on drupal.org:
// $cvs_root = ':pserver:anonymous@cvs.drupal.org:/cvs/drupal';
$cvs_root = '';

// Root of the temporary directory where you want packages to be
// made. Subdirectories will be created depending on the task.
$tmp_root = '';

// Location of the LICENSE.txt file you want included in all packages.
$license = '';

// -------------------------
// drush/drush_make settings
// -------------------------
// Full path to the drush executable.
$drush = '';

// Full path to the directory where drush_make is located. This is needed to
// manually include it as a searchable path for drush extensions, as this
// script's owner will not likely have a home directory to search.
$drush_make_dir = '';


// ------------------------------------------------------------
// Optional customization
// ------------------------------------------------------------

// ----------------
// File destination
// ----------------
// This assumes you want to install the packaged releases in the
// "files/projects" directory of your root Drupal installation. If
// that's not the case, you should customize these.
$dest_root = $drupal_root;
$dest_rel = 'files/projects';

// --------------
// External tools
// --------------
// If you want this program to always use absolute paths for all the
// tools it invokes, provide a full path for each one. Otherwise,
// the script will find these tools in your PATH.
$tar = '/usr/bin/tar';
$gzip = '/usr/bin/gzip';
$zip = '/usr/bin/zip';
$cvs = '/usr/bin/cvs';
$ln = '/bin/ln';
$rm = '/bin/rm';
$php = '/usr/bin/php';

// If you are using project-release-create-history.php to generate XML release
// history files for Update status clients, if you include the full path to
// your copy of that script here, after all the packages are re(generated),
// this script will regenerate the XML release history files for any projects
// with new/updated releases.
$project_release_create_history = '';

// The repository ID's for core and contributions.
define('DRUPAL_CORE_REPOSITORY_ID', 1);
define('DRUPAL_CONTRIB_REPOSITORY_ID', 2);


// ------------------------------------------------------------
// Initialization
// (Real work begins here, nothing else to customize)
// ------------------------------------------------------------

// Check if all required variables are defined
$vars = array(
  'drupal_root' => $drupal_root,
  'site_name' => $site_name,
  'cvs_root' => $cvs_root,
  'tmp_root' => $tmp_root,
  'license' => $license,
);
foreach ($vars as $name => $val) {
  if (empty($val)) {
    print "ERROR: \"\$$name\" variable not set, aborting\n";
    $fatal_err = true;
  }
}
if (!empty($fatal_err)) {
  exit(1);
}

putenv("CVSROOT=$cvs_root");
putenv("TERM=vt100");  // drush requires a terminal.
$script_name = $argv[0];

// Find what kind of packaging we need to do
if (!empty($argv[1])) {
  $task = $argv[1];
}
else {
  $task = 'tag';
}
switch($task) {
  case 'tag':
  case 'branch':
  case 'check':
  case 'repair':
    break;
  default:
    print "ERROR: $argv[0] invoked with invalid argument: \"$task\"\n";
    exit (1);
}

$project_id = 0;
if (!empty($argv[2])) {
  $project_id = $argv[2];
}

// Setup variables for Drupal bootstrap
$_SERVER['HTTP_HOST'] = $site_name;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/' . $script_name;
$_SERVER['SCRIPT_NAME'] = '/' . $script_name;
$_SERVER['PHP_SELF'] = '/' . $script_name;
$_SERVER['SCRIPT_FILENAME'] = $_SERVER['PWD'] . '/' . $script_name;
$_SERVER['PATH_TRANSLATED'] = $_SERVER['SCRIPT_FILENAME'];

if (!chdir($drupal_root)) {
  print "ERROR: Can't chdir($drupal_root): aborting.\n";
  exit(1);
}

// Force the right umask while this script runs, so that everything is created
// with sane file permissions.
umask(0022);

require_once 'includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
// We have to initialize the theme() system before we leave $drupal_root
$hack = theme('placeholder', 'hack');

if ($task == 'check' || $task == 'repair') {
  verify_packages($task, $project_id);
}
else {
  initialize_tmp_dir($task);
  initialize_repository_info();
  package_releases($task, $project_id);
  // Now that we're done, clean out the tmp/task dir we created
  chdir($tmp_root);
  drupal_exec("$rm -rf $tmp_dir");
}

if ($task == 'branch') {
  // Clear any cached data set to expire.
  cache_clear_all(NULL, 'cache_project_release');
}
elseif ($task == 'repair') {
  // Clear all cached data
  cache_clear_all('*', 'cache_project_release', TRUE);
}

// ------------------------------------------------------------
// Functions: main work
// ------------------------------------------------------------

function package_releases($type, $project_id = 0) {
  global $drupal_root, $wd_err_msg;
  global $php, $project_release_create_history;

  $rel_node_join = '';
  $where_args = array();
  if ($type == 'tag') {
    $where = " AND (prn.rebuild = %d) AND (f.filepath IS NULL OR f.filepath = '')";
    $where_args[] = 0;  // prn.rebuild
    $plural = t('tags');
  }
  elseif ($type == 'branch') {
    $rel_node_join = " INNER JOIN {node} nr ON prn.nid = nr.nid";
    $where = " AND (prn.rebuild = %d) AND ((f.filepath IS NULL) OR (f.filepath = '') OR (nr.status = %d))";
    $where_args[] = 1;  // prn.rebuild
    $where_args[] = 1;  // nr.status
    $plural = t('branches');
    if (empty($project_id)) {
      wd_msg("Starting to package all snapshot releases.");
    }
    else {
      wd_msg("Starting to package snapshot releases for project id: @project_id.", array('@project_id' => $project_id), l(t('view'), 'node/' . $project_id));
    }
  }
  else {
    wd_err("ERROR: package_releases() called with unknown type: %type", array('%type' => $type));
    return FALSE;
  }
  $args = array();
  $args[] = 1;    // Account for np.status = 1.
  $args[] = 1;    // Account for prp.releases = 1.
  if (!empty($project_id)) {
    $where .= ' AND prn.pid = %d';
    $where_args[] = $project_id;
  }
  $args = array_merge($args, $where_args);
  $query = db_query("SELECT pp.uri, prn.nid, prn.pid, prn.tag, prn.version, c.directory, c.rid FROM {project_release_nodes} prn $rel_node_join LEFT JOIN {project_release_file} prf ON prn.nid = prf.nid LEFT JOIN {files} f ON prf.fid = f.fid INNER JOIN {project_projects} pp ON prn.pid = pp.nid INNER JOIN {node} np ON prn.pid = np.nid INNER JOIN {project_release_projects} prp ON prp.nid = prn.pid INNER JOIN {cvs_projects} c ON prn.pid = c.nid WHERE np.status = %d AND prp.releases = %d " . $where . ' ORDER BY pp.uri', $args);

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
    $releases[$release->nid] = $release;
  }
  foreach ($releases as $release) {
    $wd_err_msg = array();
    $version = $release->version;
    $project_short_name = $release->uri;
    $tag = $release->tag;
    $nid = $release->nid;
    $pid = $release->pid;
    $tag = ($tag == 'TRUNK') ? 'HEAD' : $tag;
    $project_short_name = escapeshellcmd($project_short_name);
    $version = escapeshellcmd($version);
    $tag = escapeshellcmd($tag);
    $release_dir = '';
    db_query("DELETE FROM {project_release_package_errors} WHERE nid = %d", $nid);
    if ($release->rid == DRUPAL_CORE_REPOSITORY_ID) {
      $built = package_release_core($type, $nid, $project_short_name, $version, $tag);
    }
    else {
      $release_dir = escapeshellcmd($release->directory);
      $built = package_release_contrib($type, $nid, $project_short_name, $version, $tag, $release_dir);
    }
    chdir($drupal_root);

    if ($built == 'success') {
      $num_built++;
      $project_nids[$pid] = TRUE;
    }
    // Perform cleanup of failed builds.
    else if ($built == 'error') {
      cleanup_failed_build($type, $nid, $project_short_name, $version, $tag, $release_dir);
    }
    $num_considered++;
    if (count($wd_err_msg)) {
      db_query("INSERT INTO {project_release_package_errors} (nid, messages) values (%d, '%s')", $nid, serialize($wd_err_msg));
    }
  }
  if ($num_built || $type == 'branch') {
    if (!empty($project_id)) {
      wd_msg("Done packaging releases for @project_short_name from !plural: !num_built built, !num_considered considered.", array('@project_short_name' => $project_short_name, '!plural' => $plural, '!num_built' => $num_built, '!num_considered' => $num_considered));
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

function package_release_core($type, $nid, $project_short_name, $version, $tag) {
  global $tmp_dir, $repositories, $dest_root, $dest_rel;
  global $cvs, $tar, $gzip, $zip, $rm;

  if (!drupal_chdir($tmp_dir)) {
    return 'error';
  }

  $release_file_id = $project_short_name . '-' . $version;
  $release_node_view_link = l(t('view'), 'node/' . $nid);
  $file_path_tgz = $dest_rel . '/' . $release_file_id . '.tar.gz';
  $full_dest_tgz = $dest_root . '/' . $file_path_tgz;
  $file_path_zip = $dest_rel . '/' . $release_file_id . '.zip';
  $full_dest_zip = $dest_root . '/' . $file_path_zip;

  // Remember if the tar.gz version of this release file already exists.
  $tgz_exists = is_file($full_dest_tgz);

  // Don't use drupal_exec or return if this fails, we expect it to be empty.
  exec("$rm -rf $tmp_dir/$release_file_id");

  // Actually generate the tarball:
  if (!drupal_exec("$cvs -q export -r $tag -d $release_file_id drupal")) {
    return 'error';
  }

  $info_files = array();
  $exclude = array('.', '..', 'LICENSE.txt');
  $youngest = file_find_youngest($release_file_id, 0, $exclude, $info_files);
  if ($type == 'branch' && $tgz_exists && filemtime($full_dest_tgz) + 300 > $youngest) {
    // The existing tarball for this release is newer than the youngest
    // file in the directory, we're done.
    return 'no-op';
  }

  // Update any .info files with packaging metadata.
  foreach ($info_files as $file) {
    if (!fix_info_file_version($file, $project_short_name, $version)) {
      wd_err("ERROR: Failed to update version in %file, aborting packaging", array('%file' => $file), $release_node_view_link);
      return 'error';
    }
  }

  if (!drupal_exec("$tar -c --file=- $release_file_id | $gzip -9 --no-name > $full_dest_tgz")) {
    return 'error';
  }
  $files[$file_path_tgz] = 0;

  if (!drupal_exec("$zip -rq $full_dest_zip $release_file_id")) {
    return 'error';
  }
  $files[$file_path_zip] = 1;

  // As soon as the tarball exists, we want to update the DB about it.
  package_release_update_node($nid, $files);

  if ($tgz_exists) {
    wd_msg("%id has changed, re-packaged.", array('%id' => $release_file_id), $view_link);
  }
  else {
    wd_msg("Packaged %id.", array('%id' => $release_file_id), $view_link);
  }

  // Don't consider failure to remove this directory a build failure.
  drupal_exec("$rm -rf $tmp_dir/$release_file_id");
  return 'success';
}

function package_release_contrib($type, $nid, $project_short_name, $version, $tag, $release_dir) {
  global $tmp_dir, $repositories, $dest_root, $dest_rel;
  global $cvs, $tar, $gzip, $zip, $rm, $ln;
  global $drush, $drush_make_dir;
  global $license;

  // Files to ignore when checking timestamps:
  $exclude = array('.', '..', 'LICENSE.txt');

  $parts = split('/', $release_dir);
  // modules, themes, theme-engines, or profiles.
  $contrib_type = $parts[1];
  // specific directory (same as uri)
  $project_short_name = $parts[2];

  $project_build_root = "$tmp_dir/$project_short_name";
  $cvs_export_dir = "{$repositories[DRUPAL_CONTRIB_REPOSITORY_ID]['modules']}/$contrib_type/$project_short_name";

  $release_file_id = $project_short_name . '-' . $version;
  $release_node_view_link = l(t('view'), 'node/' . $nid);
  $file_path_tgz = $dest_rel . '/' . $release_file_id . '.tar.gz';
  $full_dest_tgz = $dest_root . '/' . $file_path_tgz;
  $file_path_zip = $dest_rel . '/' . $release_file_id . '.zip';
  $full_dest_zip = $dest_root . '/' . $file_path_zip;

  // Remember if the tar.gz version of this release file already exists.
  $tgz_exists = is_file($full_dest_tgz);

  // Clean up any old build directory if it exists.
  // Don't use drupal_exec or return if this fails, we expect it to be empty.
  exec("$rm -rf $project_build_root");

  // Make a fresh build directory and move inside it.
  if (!mkdir($project_build_root) || !drupal_chdir($project_build_root)) {
    return 'error';
  }

  // Checkout this release from CVS, and see if we need to rebuild it
  if (!drupal_exec("$cvs -q export -r $tag -d $project_short_name $cvs_export_dir")) {
    return 'error';
  }
  if (!is_dir("$project_build_root/$project_short_name")) {
    wd_err("ERROR: %dir does not exist after cvs export -r %tag -d %dir %cvs_export_dir", array('%dir' => $project_short_name, '%rev' =>  $tag, '%cvs_export_dir' => $cvs_export_dir), $release_node_view_link);
    return 'error';
  }

  $info_files = array();
  $youngest = file_find_youngest($project_short_name, 0, $exclude, $info_files);
  if ($type == 'branch' && $tgz_exists && filemtime($full_dest_tgz) + 300 > $youngest) {
    // The existing tarball for this release is newer than the youngest
    // file in the directory, we're done.
    return 'no-op';
  }

  // Update any .info files with packaging metadata.
  foreach ($info_files as $file) {
    if (!fix_info_file_version($file, $project_short_name, $version)) {
      wd_err("ERROR: Failed to update version in %file, aborting packaging", array('%file' => $file), $release_node_view_link);
      return 'error';
    }
  }

  // Link not copy, since we want to preserve the date...
  if (!drupal_exec("$ln -sf $license $project_short_name/LICENSE.txt")) {
    return 'error';
  }

  // 'h' is for dereference, we want to include the files, not the links
  if (!drupal_exec("$tar -ch --file=- $project_short_name | $gzip -9 --no-name > $full_dest_tgz")) {
    return 'error';
  }
  $files[$file_path_tgz] = 0;

  // If we're rebuilding, make sure the previous .zip is gone, since just
  // running zip again with the same zip archive won't give us the semantics
  // we want. For example, files that are removed in CVS will still be left
  // in the .zip archive.
  @unlink($full_dest_zip);
  if (!drupal_exec("$zip -rq $full_dest_zip $project_short_name")) {
    return 'error';
  }
  $files[$file_path_zip] = 1;

  // Start with no package contents, since this is only valid for profiles.
  $package_contents = array();

  // This is a profile, so invoke the drush_make routines to package core
  // and/or any other contrib releases specified in the profile's .make file.
  if ($contrib_type == 'profiles') {
    // Move inside the profile directory.
    if (!drupal_chdir("$project_build_root/$project_short_name")) {
      return 'error';
    }

    // In order for extended packaging to take place, the profile must have a
    // file named drupal-org.make in the main directory of their profile.
    $drupalorg_makefile = 'drupal-org.make';

    if (file_exists($drupalorg_makefile)) {
      // On packaged install profiles, we want the profile-only tarball to be
      // the heaviest weight in the {project_release_file} table so it sinks
      // to the bottom of various listings.
      $files[$file_path_tgz] = 10;
      $files[$file_path_zip] = 11;

      // Search the .make file for the required 'core' attribute.
      $info = drupal_parse_info_file($drupalorg_makefile);

      // Only proceed if a core release was found.
      if (!isset($info['core'])) {
        wd_err("ERROR: %profile does not have the required 'core' attribute.", array('%profile' => $release_file_id), $release_node_view_link);
        return 'error';
      }
      else {

        // Basic sanity check for the format of the attribute. The CVS checkout
        // attempt of core will handle the rest of the validation (ie, it will
        // fail if a non-existant tag is specified.
        if (!preg_match("/^(\d+)\.(\d+)(-[a-z0-9]+)?$/", $info['core'], $matches)) {
          wd_err("ERROR: %profile specified an invalid 'core' attribute -- both API version and release are required.", array('%profile' => $release_file_id), $release_node_view_link);
          return 'error';
        }
        else {
          // Compare the Drupal API version in the profile's version string with
          // the API version of core specificed in the .make file -- these should
          // match.
          $profile_api_version = $matches[1];
          $parts = explode('.', $version);
          $release_api_version = $parts[0];
          if ($profile_api_version != $release_api_version) {
            wd_err("ERROR: %profile specified an invalid 'core' attribute -- the API version must match the API version of the release.", array('%profile' => $release_file_id), $release_node_view_link);
            return 'error';
          }
        }

        // NO-CORE DISTRIBUTION.

        $no_core_id = "$release_file_id-no-core";

        // Build the drupal file path and the full file path for tgz and zip.
        $no_core_file_path_tgz = "$dest_rel/$no_core_id.tar.gz";
        $no_core_full_dest_tgz = "$dest_root/$no_core_file_path_tgz";
        $no_core_file_path_zip = "$dest_rel/$no_core_id.zip";
        $no_core_full_dest_zip = "$dest_root/$no_core_file_path_zip";

        // Run drush_make to build the profile's contents.
        // --drupal-org: Invoke drupal.org specific validation/processing.
        // --drupal-org-build-root: Let the script know where to place it's
        //   build-related files.
        // --drupal-org-log-errors-to-file: Store build errors for later output.
        // --drupal-org-log-package-items-to-file: Store package items for
        //   later recording in the database.
        if (!drupal_exec("$drush --include=$drush_make_dir make --drupal-org --drupal-org-build-root=$project_build_root --drupal-org-log-errors-to-file --drupal-org-log-package-items-to-file $drupalorg_makefile .")) {
          // The build failed, get any output error messages and include them
          // in the packaging error report.
          $build_errors_file = "$project_build_root/build_errors.txt";
          if (file_exists($build_errors_file)) {
            $lines = file($build_errors_file, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
            	wd_err("ERROR: $line");
            }
          }
          wd_err("ERROR: Build for %profile failed.", array('%profile' => $no_core_id), $release_node_view_link);
          return 'error';
        }

        // Change into the profile build directory.
        if (!drupal_chdir($project_build_root)) {
          return 'error';
        }

        // Package the no-core distribution.
        // 'h' is for dereference, we want to include the files, not the links
        if (!drupal_exec("$tar -ch --file=- $project_short_name | $gzip -9 --no-name > $no_core_full_dest_tgz")) {
          return 'error';
        }
        $files[$no_core_file_path_tgz] = 6;

        @unlink($no_core_full_dest_zip);
        if (!drupal_exec("$zip -rq $no_core_full_dest_zip $project_short_name")) {
          return 'error';
        }
        $files[$no_core_file_path_zip] = 7;


        // CORE DISTRIBUTION.

        // Write a small .make file used to build core.
        $core_version = $info['core'];
        $core_build_dir = "drupal-$core_version";
        $core_makefile = "$core_build_dir.make";
        file_put_contents($core_makefile, core_make_file($core_version));

        // Run drush_make to build core.
        if (!drupal_exec("$drush --include=$drush_make_dir make $core_makefile $core_build_dir")) {
          // The build failed, bail out.
          wd_err("ERROR: Build for %core failed.", array('%core' => $core_build_dir), $release_node_view_link);
          return 'error';
        }

        // Move the profile into place inside core.
        if (!rename($project_short_name, "$core_build_dir/profiles/$project_short_name")) {
          return 'error';
        }

        $core_id = "$release_file_id-core";

        // Build the drupal file path and the full file path for tgz and zip.
        $core_file_path_tgz = "$dest_rel/$core_id.tar.gz";
        $core_full_dest_tgz = "$dest_root/$core_file_path_tgz";
        $core_file_path_zip = "$dest_rel/$core_id.zip";
        $core_full_dest_zip = "$dest_root/$core_file_path_zip";

        // Package the core distribution.
        // 'h' is for dereference, we want to include the files, not the links
        if (!drupal_exec("$tar -ch --file=- $core_build_dir | $gzip -9 --no-name > $core_full_dest_tgz")) {
          return 'error';
        }
        // We want this to float to the top, so give it the lightest weight.
        $files[$core_file_path_tgz] = 0;

        @unlink($core_full_dest_zip);
        if (!drupal_exec("$zip -rq $core_full_dest_zip $core_build_dir")) {
          return 'error';
        }
        // We want the .zip version with core to be ligher than the non-core
        // files, but heavier than .tar.gz.
        $files[$core_file_path_zip] = 1;

        // Development releases may have changed package contents -- clear out
        // their package item summary so a fresh item summary will be inserted.
        if ($type == 'branch' && module_exists('project_package')) {
          db_query("DELETE FROM {project_package_local_release_item} WHERE package_nid = %d", $nid);
        }

        // Core was built without the drupal.org drush extension, so the
        // package item for core isn't in the package contents file. Retrieve
        // it manually.
        $core_tag = 'DRUPAL-'. str_replace('.', '-', $core_version);
        if (!($core_release_nid = db_result(db_query("SELECT nid FROM {project_release_nodes} WHERE tag = '%s'", $core_tag)))) {
          return 'error';
        }
        $package_contents[] = $core_release_nid;

        // Retrieve the package contents for the release.
        $package_contents_file = "$project_build_root/package_contents.txt";
        if (file_exists($package_contents_file)) {
          $lines = file($package_contents_file, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
          foreach ($lines as $line) {
            if (is_numeric($line)) {
              $package_contents[] = $line;
            }
          }
        }
        else {
          wd_err("ERROR: %file does not exist for %profile release.", array('%file' => $package_contents_file, '%profile' => $release_file_id), $release_node_view_link);
          return 'error';
        }
      }
    }
    else {
      wd_msg("No makefile for %profile profile -- skipping extended packaging.", array('%profile' => $release_file_id), $release_node_view_link);
    }
  }

  // Now that all the files exist, update the DB about them.
  package_release_update_node($nid, $files, $package_contents);

  if ($tgz_exists) {
    wd_msg("%id has changed, re-packaged.", array('%id' => $release_file_id), $view_link);
  }
  else {
    wd_msg("Packaged %id.", array('%id' => $release_file_id), $view_link);
  }

  // Don't consider failure to remove this directory a build failure.
  drupal_exec("$rm -rf $project_build_root");
  return 'success';
}


// ------------------------------------------------------------
// Functions: metadata validation functions
// ------------------------------------------------------------

/**
 * Check that file metadata on disk matches the values stored in the DB.
 */
function verify_packages($task, $project_id) {
  global $dest_root;
  $do_repair = $task == 'repair' ? TRUE : FALSE;
  $args = array(1);
  $where = '';
  if (!empty($project_id)) {
    $where = ' AND prn.pid = %d';
    $args[] = $project_id;
  }
  $query = db_query("SELECT prn.nid, f.filepath, f.timestamp, prf.filehash FROM {project_release_nodes} prn INNER JOIN {node} n ON prn.nid = n.nid INNER JOIN {project_release_file} prf ON prn.nid = prf.nid INNER JOIN {files} f ON prf.fid = f.fid WHERE n.status = %d AND f.filepath <> '' $where", $args);
  while ($release = db_fetch_object($query)) {
    // Grab all the results into RAM to free up the DB connection for
    // when we need to update the DB to correct metadata or log messages.
    $releases[] = $release;
  }

  $num_failed = 0;
  $num_repaired = 0;
  $num_not_exist = 0;
  $num_need_repair = 0;
  $num_considered = 0;
  $num_wrong_date = 0;
  $num_wrong_hash = 0;

  // Now, process the files, and check metadata
  foreach ($releases as $release) {
    $valid_hash = TRUE;
    $valid_date = TRUE;
    $num_considered++;
    $nid = $release->nid;
    $release_node_view_link = l(t('view'), 'node/' . $nid);
    $file_path = $release->filepath;
    $full_path = $dest_root . '/' . $file_path;
    $db_date = (int)$release->timestamp;
    $db_hash = $release->filehash;

    if (!is_file($full_path)) {
      $num_not_exist++;
      wd_err('WARNING: %file does not exist.', array('%file' => $full_path), $release_node_view_link);
      continue;
    }
    $real_date = filemtime($full_path);
    $real_hash = md5_file($full_path);

    $variables = array();
    $variables['%file'] = $file_path;
    if ($real_hash != $db_hash) {
      $valid_hash = FALSE;
      $num_wrong_hash++;
      $variables['@db_hash'] = $db_hash;
      $variables['@real_hash'] = $real_hash;
    }
    if ($real_date != $db_date) {
      $valid_date = FALSE;
      $num_wrong_date++;
      $variables['!db_date'] = format_date($db_date);
      $variables['!db_date_raw'] = $db_date;
      $variables['!real_date'] = format_date($real_date);
      $variables['!real_date_raw'] = $real_date;
    }
    if ($valid_date && $valid_hash) {
      // Nothing else to do.
      continue;
    }

    if (!$valid_date && !$valid_hash) {
      wd_check('All file meta data for %file is incorrect: saved date: !db_date (!db_date_raw), real date: !real_date (!real_date_raw); saved md5hash: @db_hash, real md5hash: @real_hash', $variables, $release_node_view_link);
    }
    else if (!$valid_date) {
      wd_check('File date for %file is incorrect: saved date: !db_date (!db_date_raw), real date: !real_date (!real_date_raw)', $variables, $release_node_view_link);
    }
    else { // !$valid_hash
      wd_check('File md5hash for %file is incorrect: saved: @db_hash, real: @real_hash', $variables, $release_node_view_link);
    }

    if (!$do_repair) {
      $num_need_repair++;
    }
    else {
      $ret1 = $ret2 = FALSE;
      // TODO: Broken for N>1 files per release.
      $fid = db_result(db_query("SELECT fid FROM {project_release_file} WHERE nid = %d", $nid));
      if (!empty($fid)) {
        $ret1 = db_query("UPDATE {project_release_file} SET filehash = '%s' WHERE fid = %d", $real_hash, $fid);
        $ret2 = db_query("UPDATE {files} SET timestamp = %d WHERE fid = %d", $real_date, $fid);
      }
      if ($ret1 && $ret2) {
        $num_repaired++;
      }
      else {
        wd_err('ERROR: db_query() failed trying to update metadata for %file', array('%file' => $file_path), $release_node_view_link);
        $num_failed++;
      }
    }
  }

  $num_vars = array(
    '!num_considered' => $num_considered,
    '!num_repaired' => $num_repaired,
    '!num_need_repair' => $num_need_repair,
    '!num_wrong_date' => $num_wrong_date,
    '!num_wrong_hash' => $num_wrong_hash,
  );
  if ($num_failed) {
    wd_err('ERROR: unable to repair !num_failed releases due to db_query() failures.', array('!num_failed' => $num_failed));
  }
  if ($num_not_exist) {
    wd_err('ERROR: !num_not_exist files are in the database but do not exist on disk.', array('!num_not_exist' => $num_not_exist));
  }
  if ($do_repair) {
    wd_check('Done checking releases: !num_repaired repaired, !num_wrong_date invalid dates, !num_wrong_hash invalid md5 hashes, !num_considered considered.', $num_vars);
  }
  else {
    if (empty($project_id)) {
      wd_check('Done checking releases: !num_need_repair need repairing, !num_wrong_date invalid dates, !num_wrong_hash invalid md5 hashes, !num_considered considered.', $num_vars);
    }
    else {
      $num_vars['@project_id'] = $project_id;
      wd_check('Done checking releases for project id @project_id: !num_need_repair need repairing, !num_wrong_date invalid dates, !num_wrong_hash invalid md5 hashes, !num_considered considered.', $num_vars, l(t('view'), 'node/' . $project_id));
    }
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

/// TODO: remove this before the final script goes live -- debugging only.
function wprint($var) {
  watchdog('package_debug', '<pre>' . var_export($var, TRUE));
}

/**
 * Wrapper function for watchdog() to log notice messages. Uses a
 * different watchdog message type depending on the task (branch vs. tag).
 */
function wd_msg($msg, $variables = array(), $link = NULL) {
  global $task;
  watchdog('package_' . $task, $msg, $variables, WATCHDOG_NOTICE, $link);
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

/**
 * Wrapper function for watchdog() to log messages about checking
 * package metadata.
 */
function wd_check($msg, $variables = array(), $link = NULL) {
  watchdog('package_check', $msg, $variables, WATCHDOG_NOTICE, $link);
  echo t($msg, $variables) . "\n";
}

/**
 * Initialize the tmp directory. Use different subdirs for building
 * snapshots than official tags, so there's no potential directory
 * collisions and race conditions if both are running at the same time
 * (due to how long it takes to complete a branch snapshot run, and
 * how often we run this for tag-based releases).
 */
function initialize_tmp_dir($task) {
  global $tmp_dir, $tmp_root, $rm;

  if (!is_dir($tmp_root) && !@mkdir($tmp_root, 0777, TRUE)) {
    wd_err("ERROR: mkdir(@dir) (tmp_root) failed", array('@dir' => $tmp_root));
    exit(1);
  }

  // Use a tmp directory *specific* to this invocation, so that we don't
  // clobber other runs if the script is invoked twice (e.g. via cron and
  // manually, etc).
  $tmp_dir = $tmp_root . '/' . $task . '.' . getmypid();
  if (is_dir($tmp_dir)) {
    // Make sure we start with a clean slate
    drupal_exec("$rm -rf $tmp_dir/*");
  }
  else if (!@mkdir($tmp_dir, 0777, TRUE)) {
    wd_err("ERROR: mkdir(@dir) failed", array('@dir' => $tmp_dir));
    exit(1);
  }
}

/**
 * Initialize info from the {cvs_repositories} table, since there are
 * usually only a tiny handful of records, and it'll be faster to do
 * whatever we need via php than another JOIN...
 */
function initialize_repository_info() {
  global $repositories;
  $query = db_query("SELECT rid, root, modules, name FROM {cvs_repositories}");
  while ($repo = db_fetch_object($query)) {
    $repositories[$repo->rid] = array('root' => $repo->root, 'modules' => $repo->modules, 'name' => $repo->name);
  }
}


/**
 * Fix the given .info file with the specified version string
 */
function fix_info_file_version($file, $project_short_name, $version) {
  global $site_name;

  $info = "\n; Information added by $site_name packaging script on " . date('Y-m-d') . "\n";
  $info .= "version = \"$version\"\n";
  // .info files started with 5.x, so we don't have to worry about version
  // strings like "4.7.x-1.0" in this regular expression. If we can't parse
  // the version (also from an old "HEAD" release), or the version isn't at
  // least 6.x, don't add any "core" attribute at all.
  $matches = array();
  if (preg_match('/^((\d+)\.x)-.*/', $version, $matches) && $matches[2] >= 6) {
    $info .= "core = \"$matches[1]\"\n";
  }
  $info .= "project = \"$project_short_name\"\n";
  $info .= 'datestamp = "'. time() ."\"\n";
  $info .= "\n";

  if (!chmod($file, 0644)) {
    wd_err("ERROR: chmod(@file, 0644) failed", array('@file' => $file));
    return false;
  }
  if (!$info_fd = fopen($file, 'ab')) {
    wd_err("ERROR: fopen(@file, 'ab') failed", array('@file' => $file));
    return false;
  }
  if (!fwrite($info_fd, $info)) {
    wd_err("ERROR: fwrite(@file) failed". '<pre>' . $info, array('@file' => $file));
    return false;
  }
  return true;
}

/**
 * Update the DB with the new file info for a given release node.
 *
 * @param $nid
 *   The node ID of the release node to update.
 * @param $files
 *   Array of files to add to the release node. The keys are filenames, and
 *   the values are the weight for the {project_release_file}.weight column.
 * @param $package_contents
 *   Optional. Array of nids of releases contained in a release package.
 */
function package_release_update_node($nid, $files, $package_contents = array()) {
  global $drupal_root, $dest_root, $task;

  // PHP will cache the results of stat() and give us stale answers
  // here, unless we manually tell it otherwise!
  clearstatcache();

  // Make sure we're back at the webroot so node_load() and node_save()
  // can always find any files they (and the hooks they invoke) need.
  if (!drupal_chdir($drupal_root)) {
    return FALSE;
  }

  // If the site is using DB replication, force this node_load() to use the
  // primary database to avoid node_load() failures.
  if (function_exists('db_set_ignore_slave')) {
    db_set_ignore_slave();
  }
  // We don't want to waste too much RAM by leaving all these loaded nodes
  // in RAM, so we reset the node_load() cache each time we call it.
  $node = node_load($nid, NULL, TRUE);
  if (empty($node->nid)) {
    wd_err('node_load(@nid) failed', array('@nid' => $nid));
    return FALSE;
  }

  foreach ($files as $file_path => $file_weight) {
    // Compute the metadata for this file that we care about.
    $full_path = $dest_root . '/' . $file_path;
    $file_name = basename($file_path);
    $file_date = filemtime($full_path);
    $file_size = filesize($full_path);
    $file_hash = md5_file($full_path);
    $file_mime = file_get_mimetype($full_path);

    // First, see if we already have this file for this release node
    $file_data = db_fetch_object(db_query("SELECT prf.* FROM {project_release_file} prf INNER JOIN {files} f ON prf.fid = f.fid WHERE prf.nid = %d AND f.filename = '%s'", $node->nid, $file_name));

    // Insert or update the record in the DB as need.
    if (empty($file_data)) {
      // Don't have this file, insert a new record.
      db_query("INSERT INTO {files} (uid, filename, filepath, filemime, filesize, status, timestamp) VALUES (%d, '%s', '%s', '%s', %d, %d, %d)", $node->uid, $file_name, $file_path, $file_mime, $file_size, FILE_STATUS_PERMANENT, $file_date);
      $fid = db_last_insert_id('files', 'fid');
      db_query("INSERT INTO {project_release_file} (fid, nid, filehash, weight) VALUES (%d, %d, '%s', %d)", $fid, $node->nid, $file_hash, $file_weight);
    }
    else {
      // Already have this file for this release, update it.
      db_query("UPDATE {files} SET uid = %d, filename = '%s', filepath = '%s', filemime = '%s', filesize = %d, status = %d, timestamp = %d WHERE fid = %d", $node->uid, $file_name, $file_path, $file_mime, $file_size, FILE_STATUS_PERMANENT, $file_date, $file_data->fid);
      db_query("UPDATE {project_release_file} SET filehash = '%s', weight = %d WHERE fid = %d", $file_hash, $file_weight, $file_data->fid);
    }
  }

  // Store package contents if necessary.
  if (!empty($package_contents) && module_exists('project_package')) {
    foreach ($package_contents as $item_nid) {
      db_query("INSERT INTO {project_package_local_release_item} (package_nid, item_nid) VALUES (%d, %d)", $nid, $item_nid);
    }
  }

  // Don't auto-publish security updates.
  $security_update_tid = variable_get('project_release_security_update_tid', 0);
  if ($task == 'tag' && !empty($node->taxonomy[$security_update_tid])) {
    watchdog('package_security', 'Not auto-publishing security update release.', array(), WATCHDOG_NOTICE, l(t('view'), 'node/' . $node->nid));
    return;
  }

  // Finally publish the node if it is currently unpublished. Instead of
  // directly updating {node}.status, we use node_save() so that other modules
  // which implement hook_nodeapi() will know that this node is now published.
  if (empty($node->status)) {
    $node->status = 1;
    node_save($node);
  }
}

/**
 * Find the youngest (newest) file in a directory tree.
 * Stolen wholesale from the original package-drupal.php script.
 * Modified to also notice any files that end with ".info" and store
 * all of them in the array passed in as an argument. Since we have to
 * recurse through the whole directory tree already, we should just
 * record all the info we need in one pass instead of doing it twice.
 */
function file_find_youngest($dir, $timestamp, $exclude, &$info_files) {
  if (is_dir($dir)) {
    $fp = opendir($dir);
    while (FALSE !== ($file = readdir($fp))) {
      if (!in_array($file, $exclude)) {
        if (is_dir("$dir/$file")) {
          $timestamp = file_find_youngest("$dir/$file", $timestamp, $exclude, $info_files);
        }
        else {
          $mtime = filemtime("$dir/$file");
          $timestamp = ($mtime > $timestamp) ? $mtime : $timestamp;
          if (preg_match('/^.+\.info$/', $file)) {
            $info_files[] = "$dir/$file";
          }
        }
      }
    }
    closedir($fp);
  }
  return $timestamp;
}

/**
 * Construct a .make file which will build Drupal core.
 *
 * This is a very simple 'bootstrap' .make file, which should only ever include
 * the minimal package metadata to build core.
 *
 * All arguments should be in a format that drush_make can understand.
 *
 * @param $core
 *   The core release to package with the profile.
 */
function core_make_file($core) {

  $output = '';
  $output .= "core = $core\n";
  $output .= "projects[drupal] = $core\n";

  return $output;
}

/**
 * Clean up any junk from failed builds.
 *
 * @param $type
 *   The type of release, 'branch' or 'tag'.
 * @param $nid
 *   The node ID of the release.
 * @param $project_short_name
 *   {project_projects}.uri
 * @param $version
 *   Version string for the release.
 * @param $tag
 *   CVS tag for the release.
 * @param $release_dir
 *   The CVS directory path for the project (contrib only).
 */
function cleanup_failed_build($type, $nid, $project_short_name, $version, $tag, $release_dir) {
  global $dest_root, $dest_rel;

  $extensions = array('.tar.gz', '.zip');

  $release_file_base = $dest_root . '/' . $dest_rel . '/' . $project_short_name . '-' . $version;

  // Remove the main release files.
  foreach ($extensions as $extension) {
    $filename = $release_file_base . $extension;
    if (file_exists($filename)) {
      unlink($filename);
    }
  }

  // For profiles, clean up any other profile-specific files.
  if (!empty($release_dir)) {
    $parts = split('/', $release_dir);
    // modules, themes, theme-engines, profiles, or translations
    $contrib_type = $parts[1];
    if ($contrib_type == 'profiles') {
      foreach (array('no-core', 'core') as $variant) {
        foreach ($extensions as $extension) {
          $filename = $release_file_base . '-' . $variant . $extension;
          if (file_exists($filename)) {
            unlink($filename);
          }
        }
      }
    }
  }
}
