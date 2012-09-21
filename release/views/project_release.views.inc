<?php

/**
 * @file
 * Provide views data and handlers for project_release.module.
 */

/**
 * Implementation of hook_views_data().
 *
 * Exposes all fields to the views system.
 */
function project_release_views_data() {
  $data = array();

  $data['project_release_supported_versions']['table']['group']  = t('Project release');
  // We only want to JOIN on this table via explicit relationships.

  $data['project_release_supported_versions']['nid'] = array(
    'title' => t('Project ID'),
    'help' => t('Project node ID of the supported version information.'),
    'field' => array(
      'handler' => 'views_handler_field_node',
      'click sortable' => TRUE,
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_node_nid',
      'name field' => 'title',
      'numeric' => TRUE,
      'validate type' => 'nid',
    ),
    'relationship' => array(
      'base' => 'node',
      'handler' => 'views_handler_relationship',
      'label' => t('Project node'),
    ),
  );
  $data['project_release_supported_versions']['tid'] = array(
    'title' => t('Supported API Compatibility term'),
    'help' => t('The term ID of a supported API compatibility version.'),
    'field' => array(
      'handler' => 'views_handler_field_taxonomy',
      'click sortable' => FALSE,
    ),
    'filter' => array(
      'handler' => 'project_release_handler_filter_version_api_tid',
    ),
  );
  $data['project_release_supported_versions']['supported'] = array(
    'title' => t('Supported'),
    'help' => t('Is the specific branch supported by a given project.'),
    'field' => array(
      'handler' => 'views_handler_field_boolean',
      'click sortable' => TRUE,
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_boolean_operator',
      'type' => 'yes-no',
    ),
  );
  $data['project_release_supported_versions']['recommended'] = array(
    'title' => t('Recommended branch'),
    'help' => t('Is the specific branch the recommended branch for a given project.'),
    'field' => array(
      'handler' => 'views_handler_field_boolean',
      'click sortable' => TRUE,
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_boolean_operator',
      'type' => 'yes-no',
    ),
  );
  $data['project_release_supported_versions']['snapshot'] = array(
    'title' => t('Show snapshot'),
    'help' => t('Show development snapshot releases from a branch.'),
    'field' => array(
      'handler' => 'views_handler_field_boolean',
      'click sortable' => TRUE,
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_boolean_operator',
      'type' => 'yes-no',
    ),
  );
  $data['project_release_supported_versions']['recommended_release'] = array(
    'title' => t('Recommended release'),
    'help' => t('The recommended release from a specific branch of a project.'),
    'field' => array(
      'handler' => 'views_handler_field_node',
      'click sortable' => TRUE,
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_node_nid',
      'name field' => 'title',
      'numeric' => TRUE,
      'validate type' => 'nid',
    ),
    'relationship' => array(
      'base' => 'node',
      'handler' => 'views_handler_relationship',
      'label' => t('Recommended release node'),
    ),
  );
  $data['project_release_supported_versions']['latest_release'] = array(
    'title' => t('Latest release'),
    'help' => t('The latest release from a specific branch of a project.'),
    'field' => array(
      'handler' => 'views_handler_field_node',
      'click sortable' => TRUE,
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_node_nid',
      'name field' => 'title',
      'numeric' => TRUE,
      'validate type' => 'nid',
    ),
    'relationship' => array(
      'base' => 'node',
      'handler' => 'views_handler_relationship',
      'label' => t('Latest release node'),
    ),
  );
  $data['project_release_supported_versions']['latest_security_release'] = array(
    'title' => t('Latest security release'),
    'help' => t('The latest security release from a specific branch of a project (if any).'),
    'field' => array(
      'handler' => 'views_handler_field_node',
      'click sortable' => TRUE,
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_node_nid',
      'name field' => 'title',
      'numeric' => TRUE,
      'validate type' => 'nid',
    ),
    'relationship' => array(
      'base' => 'node',
      'handler' => 'views_handler_relationship',
      'label' => t('Latest security release node'),
    ),
  );
  $data['project_release_supported_versions']['release_type'] = array(
    'title' => t('Latest or recommended release'),
    'help' => t('Filter based on the latest or recommended release from a specific branch of a project.'),
    'filter' => array(
      'handler' => 'project_release_handler_filter_release_node_nid',
    ),
  );

  return $data;
}

/**
 * Implementation of hook_views_data_alter().
 */
function project_release_views_data_alter(&$data) {
  // Project release version relationship.
  $data['node']['project_release_version'] = array(
    'group' => t('Project release'),
    'title' => t('Version'),
    'help' => t('Create a relationship from a project node to a project release node.'),
    'real field' => 'nid',
    'relationship' => array(
      'handler' => 'project_release_handler_relationship_project_project_release_node',
      'base' => 'node',
      'field' => 'nid',
      'label' => t('release'),
    ),
  );

  // Add a relationship for releases associated with a given project.
  $data['node']['project_release_rel'] = array(
    'group' => t('Project'),
    'title' => t('Releases'),
    'help' => t('Create a relationship to more information about all releases for a project.'),
    'real field' => 'nid',
    'relationship' => array(
      'base' => 'project_release_nodes',
      'base field' => 'pid',
      'relationship field' => 'nid',
      'handler' => 'views_handler_relationship',
      'label' => t('releases'),
    ),
  );

  // Add a relationship for information and filters about supported releases.
  $data['project_projects']['supported_releases_rel'] = array(
    'group' => t('Project'),
    'title' => t('Supported releases'),
    'help' => t('Create a relationship to more information about supported releases.'),
    'real field' => 'nid',
    'relationship' => array(
      'base' => 'project_release_supported_versions',
      'base field' => 'nid',
      'relationship field' => 'nid',
      'handler' => 'views_handler_relationship',
      'label' => t('supported versions'),
    ),
  );

  /**
   * Special fields
   *
   * These fields are not not stored in the tables created by project_release
   * and are provided as a way to provide information that would be unavailable
   * using other fields provided by this module or by views itself.
   */
  // changed field
  $data['node']['project_release_most_recent_release'] = array(
    'group' => t('Project release'),
    'title' => t('Most recent release changed time'),
    'help' => t('The date the most recent project release node associated with a project was last updated.'),
    'real field' => 'changed',
    'field' => array(
      'handler' => 'project_release_handler_field_most_recent_release',
      'click sortable' => TRUE,
    ),
    'sort' => array(
      'handler' => 'project_release_handler_sort_most_recent_release',
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_date', // @TODO
    ),
  );

  // API version compatability term field.
  // We could just use the Taxonomy: Term ID in taxonomy.views.inc except
  // that we want to only display terms the admin has indicated as being
  // active on the Project release module settings page.
  $data['term_node']['project_release_api_version'] = array(
    'group' => t('Project release'),
    'title' => t('API compatability term'),
    'help' => t('The project release API compatability term (version).'),
    'field' => array(
      'title' => t('All terms'),
      'help' => t('Display all taxonomy terms associated with a node.'),
      'handler' => 'views_handler_field_term_node_tid',
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_term_node_tid',
      'name table' => 'term_data',
      'name field' => 'name',
      'empty name field' => t('Uncategorized'),
      'numeric' => TRUE,
    ),
    'filter' => array(
      'field' => 'tid',
      'handler' => 'project_release_handler_filter_project_release_api_version',
      'hierarchy table' => 'term_hierarchy',
      'numeric' => TRUE,
    ),
  );

  // Project download table field
  $data['node']['download_table'] = array(
    'group' => t('Project release'),
    'field' => array(
      'title' => t('Download table'),
      'help' => t('A project release download table for the project.'),
      'handler' => 'project_release_handler_field_download_table',
      'format' => 'format',
    ),
  );
}

/**
 * Implementation of hook_views_handlers().
 */
function project_release_views_handlers() {
  return array(
    'info' => array(
      'path' => drupal_get_path('module', 'project_release') .'/views/handlers',
    ),
    'handlers' => array(
      'project_release_handler_field_download_table' => array(
        'parent' => 'views_handler_field',
      ),
      'project_release_handler_field_most_recent_release' => array(
        'parent' => 'views_handler_field_date',
      ),
      'project_release_handler_field_release_update_status' => array(
        'parent' => 'views_handler_field',
      ),
      'project_release_handler_filter_project_release_api_version' => array(
        'parent' => 'views_handler_filter_term_node_tid',
      ),
      'project_release_handler_filter_release_node_nid' => array(
        'parent' => 'views_handler_filter',
      ),
      'project_release_handler_filter_release_update_status' => array(
        'parent' => 'views_handler_filter_in_operator',
      ),
      'project_release_handler_filter_version_api_tid' => array(
        'parent' => 'views_handler_filter_in_operator',
      ),
      'project_release_handler_relationship_project_project_release_node' => array(
        'parent' => 'views_handler_relationship',
      ),
      'project_release_handler_relationship_supported_versions' => array(
        'parent' => 'views_handler_relationship',
      ),
      'project_release_handler_sort_most_recent_release' => array(
        'parent' => 'views_handler_sort_date',
      ),
    ),
  );
}

/**
 * Implement hook_views_plugins().
 */
function project_release_views_plugins() {
  return array(
    'style' => array(
      'project_release_table' => array(
        'title' => t('Project release table'),
        'help' => t('Table with colored rows depending on release update status.'),
        'handler' => 'project_release_table_plugin_style',
        'theme' => 'views_view_table',
        'theme file' => 'theme.inc',
        'theme path' => drupal_get_path('module', 'views') . '/theme',
        'uses row plugin' => FALSE,
        'uses fields' => TRUE,
        'uses options' => TRUE,
        'type' => 'normal',
      ),
    ),
  );
}
