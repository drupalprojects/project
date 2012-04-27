<?php

/**
 * @file
 * API documentation for hooks invokved (provided) by the Project module.
 */

/**
 * Info hook to advertise per-project permissions supported by a module.
 *
 * @param $project
 *   The project for which permissions are being fetched.
 *
 * @return
 *   Nested array of permission information. The keys of this array should be
 *   the lower-case English version of the permission name, as used throughout
 *   the code. The values of the array should be associative arrays of
 *   information about each permission. These subarrays for each permission
 *   must include a 'title' key for the human-readable (and translatable)
 *   version of the permission for display in the UI, and an optional
 *   'description' key for a description of what the permission allows a
 *   project maintainer to do.
 */
function hook_project_permission_info($project = NULL) {
  return array(
    'some permission name' => array(
      'title' => t('Name of this permission'),
      'description' => t('Description of what this this permission allows project maintainers to do.'),
    ),
  );
}

/**
 * Alter hook for per-project permissions supported by a module.
 *
 * @param $permissions
 *   Reference to an array of all the permissions defined via
 *   hook_project_permission_info().
 * @param $project
 *   The project for which permissions are being fetched.
 *
 * @see hook_project_permission_info().
 * @see project_permission_load().
 * @see drupal_alter()
 */
function hook_project_permission_alter(&$permissions, $project = NULL) {
  // I can't yet fathom why we need an alter hook here, but we might need it
  // and it was free to include it, so why not? ;)
}

/**
 * Invoked whenever a project maintainer is added or updated.
 *
 * This gives any modules that are providing their own per-project permissions
 * a chance to store the data about a maintainer's permissions whenever the
 * record for that maintainer is being saved.
 *
 * @param $nid
 *   The Project NID to save the maintainer information for.
 * @param $uid
 *   The user ID of the maintainer to save.
 * @param array $permissions
 *   Associative array of which project-level permissions the maintainer
 *   should have. The keys are permission names, and the values are if the
 *   permission should be granted or not.
 *
 * @see hook_project_permission_info()
 */
function hook_project_maintainer_save($nid, $uid, $permissions) {
  // Try to update an existing record for this maintainer for our permission.
  db_merge('example_project_maintainer')
    ->key(array('nid' => $nid, 'uid' => $uid))
    ->fields(array(
      'some_project_permission' => $permissions['some project permission'],
      'another_permission' => $permissions['another permission']
    ))
    ->execute();
}

/**
 * Invoked whenever a maintainer is removed from a given project.
 *
 * @param $nid
 *   The Project NID to remove the maintainer from.
 * @param $uid
 *   The user ID of the maintainer to remove.
 *
 * @see project_maintainer_remove()
 */
function hook_project_maintainer_remove($nid, $uid) {
  db_query("DELETE FROM {example_project_maintainer} WHERE nid = :nid and uid = :uid", array(':nid' => $nid, ':uid' => $uid));
}

/**
 * Populate the maintainer information for a given project.
 *
 * Whenever a project node is being loaded, this hook is invoked to give any
 * modules providing per-project permissions a chance to update the maintainer
 * array. This array is stored in the project as $node->project['maintainers'].
 *
 * The maintainers array is keyed by the UID of each maintainer. Each value is
 * itself a nested array of information about the maintainer. These arrays
 * have the keys 'name' for the username and 'permissions', which is an array
 * of per-project permissions associated with the maintainer.  This
 * 'permissions' subarray is keyed by permission name, and the values are 0 or
 * 1 to indicate if the maintainer should have access to that permission.
 *
 * @param $nid
 *   The Project NID to populate maintainer information about.
 * @param $maintainers
 *   Reference to a nested array of maintainers.
 */
function hook_project_maintainer_project_load($nid, &$maintainers) {
  $result = db_query('SELECT u.name, pm.* FROM {example_project_maintainer} epm INNER JOIN {users} u ON epm.uid = u.uid WHERE epm.nid = :nid ORDER BY u.name', array(':nid' => $nid));
  foreach ($result as $maintainer) {
    if (empty($maintainers[$maintainer->uid])) {
      $maintainers[$maintainer->uid]['name'] = $maintainer->name;
    }
    $maintainers[$maintainer->uid]['permissions']['some project permission'] = $maintainer->some_project_permission;
  }
}
