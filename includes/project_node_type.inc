<?php

/**
 * @file
 *  Project node types.
 */

/**
 * Install the default project node type.
 */
function project_install_default_project_node_type() {
  $t = get_t();

  /**
   * Set up project-specific field for short name.
   */
  $machine_name = array(
    'type' => 'machine_name',
    'field_name' => 'field_project_machine_name',
    'label' => $t('Project short name'),
  );
  field_create_field($machine_name);

  /**
   * Set up a default project node type, which includes title, body, and short
   * name.
   */
  $node_type = array(
    'type' => 'project',
    'name' => $t('Project'),
    'base' => 'node_content',
    'description' => $t('Content type for project management.'),
    'title_label' => $t('Name'),
    'custom' => 1,
    'modified' => 1,
    'locked' => 0,
  );
  $node_type = node_type_set_defaults($node_type);
  node_type_save($node_type);
  $description = $t('Description');
  node_add_body_field($node_type, $description);
  $project_type_instance = array(
    'bundle' => 'project',
    'description' => $t(''),
    'entity_type' => 'node',
    'field_name' => 'field_project_type',
    'label' => $t('Project type'),
    'required' => 1,
    'widget' => array(
      'weight' => '-3',
    ),
  );
  field_create_instance($project_type_instance);
  $machine_name_instance = array(
    'bundle' => 'project',
    'description' => $t(''),
    'entity_type' => 'node',
    'field_name' => 'field_project_machine_name',
    'label' => $t('Short name'),
    'required' => 1,
    'widget' => array(
      'weight' => '-2',
    ),
  );
  field_create_instance($machine_name_instance);
}

/**
 * Uninstall the default project node type.
 */
function project_uninstall_default_project_node_type() {
  field_delete_field('field_project_machine_name');
  watchdog('project', 'Deleted the :field_name field from all content type instances.', array(':field_name' => 'field_project_machine_name'));
  field_delete_field('field_project_components');
  watchdog('project', 'Deleted the :field_name field from all content type instances.', array(':field_name' => 'field_project_components'));
}

