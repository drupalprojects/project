<?php

/**
 * @file
 * Code related to node/add/project-release and friends.
 */

/**
 * Project release node add page callback.
 */
function project_release_node_add($type, $project_entity) {
  global $user;
  $field = field_info_field('field_release_project');
  list($target_id) = entity_extract_ids($field['settings']['target_type'], $project_entity);

  $types = node_type_get_types();
  $node = (object) array(
    'uid' => $user->uid,
    'name' => (isset($user->name) ? $user->name : ''),
    'type' => $type,
    'language' => LANGUAGE_NONE
  );
  $node->field_release_project[LANGUAGE_NONE][0]['target_id'] = $target_id;
  drupal_set_title(t('Create @name', array('@name' => $types[$type]->name)), PASS_THROUGH);
  module_load_include('inc', 'node', 'node.pages');
  return drupal_get_form($type . '_node_form', $node);
}

/**
 * Form builder for a simple form to select a project when creating a new
 * release (as the first "page", but this is not really a multi-page form).
 */
function project_release_pick_project_form($form, &$form_state, $node_type) {
  // Fetch a list of all projects.
  $machine_names = NULL; // We don't need an array of nid => machine_name.
  $projects = array(0 => t('- Select a project -')) + project_projects_select_options($machine_names);

  if (count($projects) == 1) {
    drupal_set_message(t('You do not have access to any projects.'), 'error');
  }

  $form_state['node_type'] = $node_type;

  $form['project_id'] = array(
    '#type' => 'select',
    '#title' => t('Project'),
    '#options' => $projects,
    '#required' => TRUE,
  );
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Next'),
  );
  return $form;
}

function project_release_pick_project_form_validate($form, &$form_state) {
  if (!empty($form_state['values']['project_id'])) {
    $node = node_load($form_state['values']['project_id']);
    if (empty($node) || !project_node_is_project($node)) {
      form_set_error('project_id', t('Invalid project selected.'));
    }
    else {
      $form_state['project_machine_name'] = $node->field_project_machine_name[LANGUAGE_NONE][0]['value'];
    }
  }
}

function project_release_pick_project_form_submit($form, &$form_state) {
  $type_url_str = str_replace('_', '-', $form_state['node_type']);
  $form_state['redirect'] = 'node/add/' . $type_url_str . '/' . $form_state['project_machine_name'];
}
