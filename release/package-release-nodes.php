#!/usr/bin/php
<?php

// $Id$
// $Name$

/**
 * @file
 * Automated packaging script to generate tarballs from release nodes.
 *
 * @author Derek Wright (http://drupal.org/user/46549)
 *
 * TODO:
 * - translation stats
 * 
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
// releases from.  For example, on drupal.org:
// $cvs_root = ':pserver:anonymous@cvs.drupal.org:/cvs/drupal';
$cvs_root = '';

// Temporary directory where you want packages to be created.
$tmp_dir = '';

// Location of the LICENSE.txt file you want included in all packages.
$license = '';

// Location of the INSTALL.txt file you want included in all
// translation packages.
$trans_install = '';


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
$cvs = '/usr/bin/cvs';
$ln = '/bin/ln';
$rm = '/bin/rm';
$msgcat = 'msgcat';
$msgattrib = 'msgattrib';
$msgfmt = 'msgfmt';


// ------------------------------------------------------------
// Initialization
// (Real work begins here, nothing else to customize)
// ------------------------------------------------------------

// Check if all required variables are defined
$vars = array(
  'drupal_root' => $drupal_root,
  'site_name' => $site_name,
  'cvs_root' => $cvs_root,
  'tmp_dir' => $tmp_dir,
  'license' => $license,
  'trans_install' => $trans_install,
);
foreach ($vars as $name => $val) {
  if (empty($val)) {
    print "ERROR: \"\$$name\" variable not set, aborting\n";
    $fatal_err = true;
  }
}
if ($fatal_err) {
  exit(1);
}

putenv("CVSROOT=$cvs_root");
$script_name = $argv[0];

// Find what kind of packaging we need to do
if ($argv[1]) {
  $task = $argv[1];
}
else {
  $task = 'tag';
}
switch($task) {
  case 'tag':
  case 'branch':
    break;
  default:
    print "ERROR: $argv[0] invoked with invalid argument: \"$task\"\n";
    exit (1);
}

// Setup variables for Drupal bootstrap
$_SERVER['HTTP_HOST'] = $site_name;
$_SERVER['REQUEST_URI'] = '/' . $script_name;
$_SERVER['SCRIPT_NAME'] = '/' . $script_name;
$_SERVER['PHP_SELF'] = '/' . $script_name;
$_SERVER['SCRIPT_FILENAME'] = $_SERVER['PWD'] . '/' . $script_name;
$_SERVER['PATH_TRANSLATED'] = $_SERVER['SCRIPT_FILENAME'];

if (!chdir($drupal_root)) {
  print "ERROR: Can't chdir($drupal_root): aborting.\n";
  exit(1);
}

require_once 'includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

initialize_tmp_dir($task);
initialize_repository_info();

package_releases($task);


// ------------------------------------------------------------
// Functions: main work
// ------------------------------------------------------------

function package_releases($type) {
  if ($type == 'tag') {
    $where = " AND (prn.rebuild = 0) AND (prn.file_path = '')";
    $plural = 'tags';
  }
  elseif ($type == 'branch') {
    $rel_node_join = " INNER JOIN {node} nr ON prn.nid = nr.nid";
    $where = " AND (prn.rebuild = 1) AND ((prn.file_path = '') OR (nr.status = 1))";
    $plural = 'branches';
    wd_msg(t("Starting to package all snapshot releases."));
  }
  else {
    wd_err(t("ERROR: package_releases() called with unknown type: %type", array('%type' => $type)));
    return;
  }

  $query = db_query("SELECT pp.uri, prn.nid, prn.tag, prn.version, c.directory, c.rid FROM {project_release_nodes} prn $rel_node_join INNER JOIN {project_projects} pp ON prn.pid = pp.nid INNER JOIN {node} np ON prn.pid = np.nid INNER JOIN {project_release_projects} prp ON prp.nid = prn.pid INNER JOIN {cvs_projects} c ON prn.pid = c.nid WHERE np.status = 1 AND prp.releases = 1" . $where . ' ORDER BY pp.uri');

  $num_built = 0;
  $num_considered = 0;
  while ($release = db_fetch_object($query)) {
    $version = $release->version;
    $uri = $release->uri;
    $tag = $release->tag;
    $nid = $release->nid;
    $rev = ($tag == 'TRUNK') ? '-r HEAD' : "-r $tag";
    wd_msg(t("Working on !type release: %id from $type: %tag", array('!type' => $release->rid == 1 ? t('core') : t('contrib'), '%id' => $uri . '-' . $version, '%tag' => $tag)), l(t('view'), 'node/' . $nid));
    $uri = escapeshellcmd($uri);
    $version = escapeshellcmd($version);
    $rev = escapeshellcmd($rev);
    if ($release->rid == 1) {
      $built = package_release_core($nid, $uri, $version, $rev);
    }
    else {
      $dir = escapeshellcmd($release->directory);
      $built = package_release_contrib($nid, $uri, $version, $rev, $dir);
    }
    if ($built) {
      $num_built++;
    }
    $num_considered++;
  }
  if ($num_built || $type == 'branch') {
    wd_msg(t("Done packaging releases from $plural: !num_built built, !num_considered considered.", array('!num_built' => $num_built, '!num_considered' => $num_considered)));
  }
}

function package_release_core($nid, $uri, $version, $rev) {
  global $tmp_dir, $repositories, $dest_root, $dest_rel;
  global $cvs, $tar, $gzip, $rm;
  $rid = 1;

  if (!drupal_chdir($tmp_dir)) {
    return false;
  }

  $id = $uri . '-' . $version;
  $view_link = l(t('view', 'node/' . $nid);
  $file_name = $id . '.tar.gz';
  $file_path = $dest_rel . '/' . $file_name;
  $full_dest = $dest_root . '/' . $file_path;

  // Don't use drupal_exec or return if this fails, we expect it to be empty.
  exec("$rm -rf $tmp_dir/$id");

  // Actually generate the tarball:
  if (!drupal_exec("$cvs -q export $rev -d $id drupal")) {
    return false;
  }

  $info_files = array();
  $exclude = array('.', '..', 'LICENSE.txt');
  $youngest = file_find_youngest($id, 0, $exclude, $info_files);
  if (is_file($full_dest) && filectime($full_dest) + 300 > $youngest) {
    // The existing tarball for this release is newer than the youngest
    // file in the directory, we're done.
    wd_msg(t("%id is unchanged, not re-packaging", array('%id' => $id)), $view_link);
    return false;
  }

  // Fix any .info files
  foreach ($info_files as $file) {
    if (!fix_info_file_version($file, $uri, $version)) {
      wd_err(t("ERROR: Failed to update version in %file, aborting packaging", array('%file' => $file)), $view_link);
      return false;
    }
  }

  if (!drupal_exec("$tar -c --file=- $id | $gzip -9 --no-name > $full_dest")) {
    return false;
  }

  if (!drupal_exec("$rm -rf $tmp_dir/$id")) {
    return false;
  }

  package_release_update_node($nid, $file_path);
  return true;
}

function package_release_contrib($nid, $uri, $version, $rev, $dir) {
  global $tmp_dir, $repositories, $dest_root, $dest_rel;
  global $cvs, $tar, $gzip, $rm, $ln;
  global $msgcat, $msgattrib, $msgfmt;
  global $license, $trans_install;

  $rid = 2;
  // Files to ignore when checking timestamps:
  $exclude = array('.', '..', 'LICENSE.txt');

  $parts = split('/', $dir);
  // modules, themes, theme-engines, or translations
  $contrib_type = $parts[1];
  // specific directory (same as uri)
  $uri = $parts[2];

  $id = $uri . '-' . $version;
  $view_link = l(t('view'), 'node/' . $nid);
  $basedir = $repositories[$rid]['modules'] . '/' . $contrib_type;
  $fulldir = $basedir . '/' . $uri;
  $file_name = $id . '.tar.gz';
  $file_path = $dest_rel . '/' . $file_name;
  $full_dest = $dest_root . '/' . $file_path;

  if (!drupal_chdir($tmp_dir)) {
    return false;
  }

  // Don't use drupal_exec or return if this fails, we expect it to be empty.
  exec("$rm -rf $tmp_dir/$fulldir");

  // Checkout this release from CVS, and see if we need to rebuild it
  if (!drupal_exec("$cvs -q export $rev $fulldir")) {
    return false;
  }
  if (!is_dir($fulldir)) {
    wd_err(t("ERROR: %dir does not exist after cvs export %rev", array('%dir' => $fulldir, '%rev' =>  $rev)), $view_link);
    return false;
  }
  if (!drupal_chdir($basedir)) {
    // TODO: try to clean up the cvs export we just did?
    // seems scary if we can't even chdir($basedir)...
    return false;
  }

  if ($contrib_type == 'translations') {
    $exclude = array_merge($exclude, 'README.txt');
  }
  $info_files = array();
  $youngest = file_find_youngest($uri, 0, $exclude, $info_files);
  if (is_file($full_dest) && filectime($full_dest) + 300 > $youngest) {
    // The existing tarball for this release is newer than the youngest
    // file in the directory, we're done.
    wd_msg(t("%id is unchanged, not re-packaging", array('%id' => $id)), $view_link);
    return false;
  }

  // Fix any .info files
  foreach ($info_files as $file) {
    if (!fix_info_file_version($file, $uri, $version)) {
      wd_err(t("ERROR: Failed to update version in %file, aborting packaging", array('%file' => $file)), $view_link);
      return false;
    }
  }

  // Link not copy, since we want to preserve the date...
  if (!drupal_exec("$ln -sf $license $uri/LICENSE.txt")) {
    return false;
  }
  if ($contrib_type == 'translations' && $uri != 'drupal-pot') {
    if ($handle = opendir($uri)) {
      $po_files = array();
      while ($file = readdir($handle)) {
        if ($file == 'general.po') {
          $found_general_po = true;
        }
        elseif ($file == 'installer.po') {
          $found_installer_po = true;
        }
        elseif (preg_match('/.*\.po/', $file)) {
          $po_files[] = "$uri/$file";
        }
      }
      if ($found_general_po) {
        @unlink("$uri/$uri.po");
        $po_targets = "$uri/general.po ";
        $po_targets .= implode(' ', $po_files);
        if (!drupal_exec("$msgcat $po_targets | $msgattrib --no-fuzzy -o $uri/$uri.po")) {
          return false;
        }
      }
    }
    if (is_file("$uri/$uri.po")) {
      if (!drupal_exec("$msgfmt --statistics $uri/$uri.po 2>> $uri/README.txt")) {
        return false;
      }
      $to_tar = "$uri/*.txt $uri/$uri.po";
      if ($found_installer_po) {
        $to_tar .= " $uri/installer.po";
      }
    }
    else {
      wd_err(t("ERROR: %uri translation does not contain a %uri_po file for version %version, not packaging", array('%uri' => $uri, '%uri_po' => "$uri.po", '%version' => $version)), $view_link);
      return false;
    }
  }
  else {
    // NOT a translation: no special packaging, grab the whole directory.
    $to_tar = $uri;
  }

  // 'h' is for dereference, we want to include the files, not the links
  if (!drupal_exec("$tar -ch --file=- $to_tar | $gzip -9 --no-name > $full_dest")) {
    return false;
  }

  if (!drupal_exec("$rm -rf $tmp_dir/$basedir/$uri")) {
    return false;
  }

  package_release_update_node($nid, $file_path);
  return true;
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
    wd_err(t("ERROR: %cmd failed with status !rval", array('%cmd' => $cmd, '!rval' => $rval)) . '<pre>' . implode("\n", array_map('htmlspecialchars', $output)));
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
    wd_err(t("ERROR: Can't chdir(@dir)", array('@dir' => $dir)));
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
function wd_msg($msg, $link = NULL) {
  global $task;
  watchdog('package_' . $task, $msg, WATCHDOG_NOTICE, $link);
}

/**
 * Wrapper function for watchdog() to log error messages.
 */
function wd_err($msg, $link = NULL) {
  watchdog('package_error', $msg, WATCHDOG_ERROR, $link);
}

/**
 * Initialize the tmp directory. Use different subdirs for building
 * snapshots than official tags, so there's no potential directory
 * collisions and race conditions if both are running at the same time
 * (due to how long it takes to complete a branch snapshot run, and
 * how often we run this for tag-based releases).
 */
function initialize_tmp_dir($task) {
  global $tmp_dir;

  $task_dir = $tmp_dir . '/' . $task;
  if (!is_dir($tmp_dir)) {
    wd_err(t("ERROR: tmp_dir: @dir is not a directory", array('@dir' => $tmp_dir)));
    exit(1);
  }
  if (!is_dir($task_dir) && !@mkdir($task_dir)) {
    wd_err(t("ERROR: mkdir(@dir) failed", array('@dir' => $task_dir)));
    exit(1);
  }
  $tmp_dir = $task_dir;
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
function fix_info_file_version($file, $uri, $version) {
  global $site_name;

  $info = "\n; Information added by $site_name packaging script on " . date('Y-m-d') . "\n";
  $info .= "version = \"$version\"\n";
  $info .= "project = \"$uri\"\n";
  $info .= "\n";

  if (!$info_fd = fopen($file, 'ab')) { 
    wd_err(t("ERROR: fopen(@file, 'ab') failed", array('@file' => $file)));
    return false;
  }
  if (!fwrite($info_fd, $info)) { 
    wd_err(t("ERROR: fwrite(@file) failed", array('@file' => $file)) . '<pre>' . $info);
    return false;
  }
  return true;
}


/**
 * Update the DB with the new file info for a given release node.
 */
function package_release_update_node($nid, $file_path) {
  global $dest_root;
  $full_path = $dest_root . '/' . $file_path;

  // Now that we have the official file, compute some metadata:
  $file_date = filemtime($full_path);
  $file_hash = md5_file($full_path);

  // Finally, update the node in the DB about this file:
  db_query("UPDATE {project_release_nodes} SET file_path = '%s', file_hash = '%s', file_date = %d WHERE nid = %d", $file_path, $file_hash, $file_date, $nid);
  db_query("UPDATE {node} SET status = 1 WHERE nid = %d", $nid);
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


// ------------------------------------------------------------
// Functions: translation-status-related methods
// TODO: get all this working. ;)
// ------------------------------------------------------------


/**
 * Extract some translation statistics:
 */
function translation_status($dir, $version) {
  global $translations;

  $number_of_strings = translation_number_of_strings('drupal-pot', $version);

  $line = exec("$msgfmt --statistics $dir/$dir.po 2>&1");
  $words = preg_split('[\s]', $line, -1, PREG_SPLIT_NO_EMPTY);

  if (is_numeric($words[0]) && is_numeric($number_of_strings)) {
    $percentage = floor((100 * $words[0]) / ($number_of_strings));
    if ($percentage >= 100) {
      $translations[$dir][$version] = "<td style=\"color: green; font-weight: bold;\">100% (complete)</td>";
    }
    else {
      $translations[$dir][$version] = "<td>". $percentage ."% (". ($number_of_strings - $words[0]). " missing)</td>";
    }
  }
  else {
    $translations[$dir][$version] = "<td style=\"color: red; font-weight: bold;\">translation broken</td>";
  }
}

function translation_report($versions) {
  global $dest, $translations;

  $output  = "<table>\n";
  $output .= " <tr><th>Language</th>";
  foreach ($versions as $version) {
    $output .= "<th>$version</th>";
  }
  $output .= " </tr>\n";

  ksort($translations);
  foreach ($translations as $language => $data) {
    $output .= " <tr><td><a href=\"project/$language\">$language</a></td>";
    foreach ($versions as $version) {
      if ($data[$version]) {
        $output .= $data[$version];
      }
      else {
        $output .= "<td></td>";
      }
    }
    $output .= "</tr>\n";
  }
  $output .= "</table>";

  $fd = fopen("$dest/translation-status.txt", 'w');
  fwrite($fd, $output);
  fclose($fd);
  wprint("wrote $dest/translation-status.txt");
}

function translation_number_of_strings($dir, $version) {
  static $number_of_strings = array();
  if (!isset($number_of_strings[$version])) {
    drupal_exec("$msgcat $dir/general.pot $dir/[^g]*.pot | $msgattrib --no-fuzzy -o $dir/$dir.pot");
    $line = exec("$msgfmt --statistics $dir/$dir.pot 2>&1");
    $words = preg_split('[\s]', $line, -1, PREG_SPLIT_NO_EMPTY);
    $number_of_strings[$version] = $words[3];
    @unlink("$dir/$dir.pot");
  }
  return $number_of_strings[$version];
}
