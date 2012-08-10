<?php

/**
 * @file Code to define the default project_release node type and its fields.
 */

/**
 * Create a default node type for project_release nodes.
 */
function _project_release_create_release_node_type() {
  $t = get_t();

  $type = array(
    'type' => 'project_release',
    'name' => $t('Release'),
    'base' => 'node_content',
    'description' => $t('A release of a project with a specific version number.'),
    'has_title' => '1',
    'title_label' => $t('Title'),
    'custom' => 1,
    'modified' => 1,
    'locked' => 0,
    'help' => '',
  );

  $type = node_type_set_defaults($type);
  node_type_save($type);
  node_add_body_field($type);

  // Define node workflow and comment settings.
  variable_set('node_options_project_release', array('status', 'revision'));
  variable_set('comment_project_release', COMMENT_NODE_CLOSED);

  // Set the project behavior setting for this node type say it's a release.
  variable_set('project_behavior_project_release', 'project_release');

  // Define default fields and field instances.

  // Project
  $field = array(
    'entity_types' => array(),
    'settings' => array(
      'target_type' => 'node',
      'handler' => 'base',
      'handler_submit' => 'Change handler',
      'handler_settings' => array(
        'target_bundles' => array(
          'project' => 'project',
        ),
      ),
    ),
    'field_name' => 'field_release_project',
    'type' => 'entityreference',
    'module' => 'entityreference',
    'cardinality' => '1',
    'columns' => array(
      'target_id' => array(
        'description' => 'The id of the target entity',
        'type' => 'int',
        'unsigned' => TRUE,
        'not null' => FALSE,
      ),
    ),
  );
  field_create_field($field);

  $instance = array(
    'bundle' => 'project_release',
    'entity_types' => array(),
    'settings' => array(
      'target_type' => 'node',
      'handler' => 'base',
      'handler_submit' => 'Change handler',
      'handler_settings' => array(
        'target_bundles' => array(
          'project' => 'project',
        ),
        'sort' => array(
          'type' => 'none',
          'property' => 'nid',
          'field' => 'body:value',
          'direction' => 'ASC',
        ),
        'behaviors' => array(
          'views-select-list' => array(
            'status' => 0,
          ),
        ),
      ),
    ),
    'entity_type' => 'node',
    'required' => 1,
    'description' => '',
    'default_value' => NULL,
    'field_name' => 'field_release_project',
    'label' => $t('Project'),
    'type' => 'entityreference',
    'module' => 'entityreference',
    'widget' => array(
      'weight' => -1,
      'type' => 'field_extrawidgets_hidden',
      'module' => 'options',
      'active' => 1,
      'settings' => array(
        'match_operator' => 'CONTAINS',
        'size' => '60',
        'path' => '',
      ),
    ),
  );
  field_create_instance($instance);

  $field = array(
    'translatable' => '0',
    'settings' => array(
      'max_length' => '255',
    ),
    'field_name' => 'field_release_version',
    'type' => 'text',
    'active' => '1',
    'locked' => '0',
    'cardinality' => '1',
  );
  field_create_field($field);

  $instance = array(
    'label' => $t('Version'),
    'widget' => array(
      'weight' => '0',
      'type' => 'text_textfield',
      'active' => 1,
      'settings' => array(
        'size' => '60',
      ),
    ),
    'settings' => array(
      'text_processing' => '0',
      'user_register_form' => FALSE,
    ),
    'display' => array(
      'default' => array(
        'label' => 'above',
        'type' => 'text_default',
        'settings' => array(),
        'weight' => 2,
      ),
      'teaser' => array(
        'type' => 'hidden',
        'label' => 'above',
        'settings' => array(),
        'weight' => 0,
      ),
    ),
    'required' => 1,
    'description' => '',
    'default_value' => NULL,
    'field_name' => 'field_release_version',
    'entity_type' => 'node',
    'bundle' => 'project_release',
  );
  field_create_instance($instance);

  $field = array(
    'translatable' => '0',
    'settings' => array(
      'max_length' => '255',
    ),
    'field_name' => 'field_release_vcs_label',
    'type' => 'text',
    'active' => '1',
    'locked' => '0',
    'cardinality' => '1',
  );
  field_create_field($field);

  $instance = array(
    'label' => $t('VCS Label'),
    'widget' => array(
      'weight' => '1',
      'type' => 'text_textfield',
      'active' => 1,
      'settings' => array(
        'size' => '60',
      ),
    ),
    'settings' => array(
      'text_processing' => '0',
      'user_register_form' => FALSE,
    ),
    'display' => array(
      'default' => array(
        'label' => 'above',
        'type' => 'text_default',
        'settings' => array(),
        'weight' => 3,
      ),
      'teaser' => array(
        'type' => 'hidden',
        'label' => 'above',
        'settings' => array(),
        'weight' => 0,
      ),
    ),
    'required' => 0,
    'description' => $t('The label (branch or tag) in a version control system that represents this release.'),
    'default_value' => NULL,
    'field_name' => 'field_release_vcs_label',
    'entity_type' => 'node',
    'bundle' => 'project_release',
  );
  field_create_instance($instance);

  $field = array(
    'translatable' => '0',
    'settings' => array(
      'allowed_values' => array(
        'static' => 'Static',
        'dynamic' => 'Dynamic',
      ),
      'allowed_values_function' => '',
    ),
    'field_name' => 'field_release_build_type',
    'type' => 'list_text',
    'active' => '1',
    'locked' => '0',
    'cardinality' => '1',
  );
  field_create_field($field);

  $instance = array(
    'label' => $t('Release type'),
    'widget' => array(
      'weight' => '2',
      'type' => 'options_select',
      'active' => 1,
      'settings' => array(),
    ),
    'settings' => array(
      'user_register_form' => FALSE,
    ),
    'display' => array(
      'default' => array(
        'label' => 'above',
        'type' => 'list_default',
        'settings' => array(),
        'weight' => 4,
      ),
      'teaser' => array(
        'type' => 'hidden',
        'label' => 'above',
        'settings' => array(),
        'weight' => 0,
      ),
    ),
    'required' => 1,
    'description' => $t("How is this release built? Can be either 'Static' if the files associated with it are built once and remain unchanged (e.g. an official release from a tag), or 'Dynamic' if the files are regularly rebuilt and updated (e.g. a development snapshot rebuilt from the end of a branch)."),
    'default_value' => array(
      0 => array(
        'value' => 'static',
      ),
    ),
    'field_name' => 'field_release_build_type',
    'entity_type' => 'node',
    'bundle' => 'project_release',
  );
  field_create_instance($instance);

  // Field to mark if a project has releases.
  field_create_field(array(
    'translatable' => '0',
    'settings' => array(
      'allowed_values' => array(
        '0' => '',
        '1' => '',
      ),
      'allowed_values_function' => '',
    ),
    'field_name' => 'field_project_has_releases',
    'type' => 'list_boolean',
    'active' => '1',
    'locked' => '0',
    'cardinality' => '1',
  ));
  field_create_instance(array(
    'bundle' => 'project',
    'description' => $t(''),
    'entity_type' => 'node',
    'field_name' => 'field_project_has_releases',
    'label' => $t('Has project releases'),
    'widget' => array(
      'weight' => '-1',
      'type' => 'options_onoff',
      'module' => 'options',
      'settings' => array(
        'display_label' => TRUE,
      ),
    ),
    'default_value' => array(
      0 => array(
        'value' => '1',
      ),
    ),
  ));

  // Per-project custom version format string.
  field_create_field(array(
    'translatable' => '0',
    'settings' => array(
      'max_length' => '255',
    ),
    'field_name' => 'field_release_version_format',
    'type' => 'text',
    'active' => '1',
    'locked' => '0',
    'cardinality' => '1',
  ));
  field_create_instance(array(
    'bundle' => 'project',
    'entity_type' => 'node',
    'field_name' => 'field_release_version_format',
    'label' => $t('Custom version format string'),
    'widget' => array(
      'weight' => '0',
      'type' => 'text_textfield',
      'active' => 1,
      'settings' => array(
        'size' => '50',
      ),
    ),
  ));
}

/**
 * Uninstall the default project release node type.
 */
function _project_release_delete_release_node_type() {
  $fields = array(
    'field_release_project',
    'field_release_version',
    'field_release_vcs_label',
    'field_release_build_type',
    'field_project_has_releases',
  );
  foreach ($fields as $field) {
    field_delete_field($field);
    watchdog('project_release', 'Deleted the :field_name field from all content type instances.', array(':field_name' => $field));
  }
}
