<?php

$view = new view();
$view->name = 'project_release_files';
$view->description = 'List of all files attached to a given release';
$view->tag = 'default';
$view->base_table = 'field_collection_item';
$view->human_name = 'Project release files';
$view->core = 7;
$view->api_version = '3.0';
$view->disabled = FALSE; /* Edit this to true to make a default view disabled initially */

/* Display: Master */
$handler = $view->new_display('default', 'Master', 'default');
$handler->display->display_options['use_more_always'] = FALSE;
$handler->display->display_options['access']['type'] = 'none';
$handler->display->display_options['cache']['type'] = 'none';
$handler->display->display_options['query']['type'] = 'views_query';
$handler->display->display_options['query']['options']['query_comment'] = FALSE;
$handler->display->display_options['exposed_form']['type'] = 'basic';
$handler->display->display_options['pager']['type'] = 'none';
$handler->display->display_options['pager']['options']['offset'] = '0';
$handler->display->display_options['style_plugin'] = 'table';
$handler->display->display_options['style_options']['columns'] = array(
  'item_id' => 'item_id',
  'field_release_file' => 'field_release_file',
  'field_release_file_hash' => 'field_release_file_hash',
);
$handler->display->display_options['style_options']['default'] = '-1';
$handler->display->display_options['style_options']['info'] = array(
  'item_id' => array(
    'sortable' => 0,
    'default_sort_order' => 'asc',
    'align' => '',
    'separator' => '',
    'empty_column' => 0,
  ),
  'field_release_file' => array(
    'sortable' => 0,
    'default_sort_order' => 'asc',
    'align' => '',
    'separator' => '',
    'empty_column' => 0,
  ),
  'field_release_file_hash' => array(
    'sortable' => 0,
    'default_sort_order' => 'asc',
    'align' => '',
    'separator' => '',
    'empty_column' => 0,
  ),
);
$handler->display->display_options['style_options']['override'] = FALSE;
/* Relationship: Field collection item: Release file (field_release_file:fid) */
$handler->display->display_options['relationships']['field_release_file_fid']['id'] = 'field_release_file_fid';
$handler->display->display_options['relationships']['field_release_file_fid']['table'] = 'field_data_field_release_file';
$handler->display->display_options['relationships']['field_release_file_fid']['field'] = 'field_release_file_fid';
$handler->display->display_options['relationships']['field_release_file_fid']['required'] = TRUE;
/* Relationship: Field collection item: Entity with the Release files (field_release_files) */
$handler->display->display_options['relationships']['field_release_files_node']['id'] = 'field_release_files_node';
$handler->display->display_options['relationships']['field_release_files_node']['table'] = 'field_collection_item';
$handler->display->display_options['relationships']['field_release_files_node']['field'] = 'field_release_files_node';
$handler->display->display_options['relationships']['field_release_files_node']['required'] = TRUE;
/* Field: Field collection item: Release file */
$handler->display->display_options['fields']['field_release_file']['id'] = 'field_release_file';
$handler->display->display_options['fields']['field_release_file']['table'] = 'field_data_field_release_file';
$handler->display->display_options['fields']['field_release_file']['field'] = 'field_release_file';
$handler->display->display_options['fields']['field_release_file']['label'] = 'Download';
$handler->display->display_options['fields']['field_release_file']['element_label_colon'] = FALSE;
$handler->display->display_options['fields']['field_release_file']['click_sort_column'] = 'fid';
/* Field: File: Size */
$handler->display->display_options['fields']['filesize']['id'] = 'filesize';
$handler->display->display_options['fields']['filesize']['table'] = 'file_managed';
$handler->display->display_options['fields']['filesize']['field'] = 'filesize';
$handler->display->display_options['fields']['filesize']['relationship'] = 'field_release_file_fid';
$handler->display->display_options['fields']['filesize']['element_label_colon'] = FALSE;
/* Field: Field collection item: Release file hash */
$handler->display->display_options['fields']['field_release_file_hash']['id'] = 'field_release_file_hash';
$handler->display->display_options['fields']['field_release_file_hash']['table'] = 'field_data_field_release_file_hash';
$handler->display->display_options['fields']['field_release_file_hash']['field'] = 'field_release_file_hash';
$handler->display->display_options['fields']['field_release_file_hash']['label'] = 'md5 hash';
/* Sort criterion: File: Name */
$handler->display->display_options['sorts']['filename']['id'] = 'filename';
$handler->display->display_options['sorts']['filename']['table'] = 'file_managed';
$handler->display->display_options['sorts']['filename']['field'] = 'filename';
$handler->display->display_options['sorts']['filename']['relationship'] = 'field_release_file_fid';
/* Contextual filter: Content: Nid */
$handler->display->display_options['arguments']['nid']['id'] = 'nid';
$handler->display->display_options['arguments']['nid']['table'] = 'node';
$handler->display->display_options['arguments']['nid']['field'] = 'nid';
$handler->display->display_options['arguments']['nid']['relationship'] = 'field_release_files_node';
$handler->display->display_options['arguments']['nid']['default_action'] = 'not found';
$handler->display->display_options['arguments']['nid']['default_argument_type'] = 'fixed';
$handler->display->display_options['arguments']['nid']['summary']['number_of_records'] = '0';
$handler->display->display_options['arguments']['nid']['summary']['format'] = 'default_summary';
$handler->display->display_options['arguments']['nid']['summary_options']['items_per_page'] = '25';

/* Display: Content pane */
$handler = $view->new_display('panel_pane', 'Content pane', 'release_files_pane');
$handler->display->display_options['defaults']['style_plugin'] = FALSE;
$handler->display->display_options['style_plugin'] = 'default';
$handler->display->display_options['defaults']['style_options'] = FALSE;
$handler->display->display_options['defaults']['row_plugin'] = FALSE;
$handler->display->display_options['row_plugin'] = 'fields';
$handler->display->display_options['defaults']['row_options'] = FALSE;
$handler->display->display_options['defaults']['fields'] = FALSE;
/* Field: File: Size */
$handler->display->display_options['fields']['filesize']['id'] = 'filesize';
$handler->display->display_options['fields']['filesize']['table'] = 'file_managed';
$handler->display->display_options['fields']['filesize']['field'] = 'filesize';
$handler->display->display_options['fields']['filesize']['relationship'] = 'field_release_file_fid';
$handler->display->display_options['fields']['filesize']['label'] = '';
$handler->display->display_options['fields']['filesize']['exclude'] = TRUE;
$handler->display->display_options['fields']['filesize']['alter']['alter_text'] = TRUE;
$handler->display->display_options['fields']['filesize']['alter']['text'] = '([filesize])';
$handler->display->display_options['fields']['filesize']['element_label_colon'] = FALSE;
/* Field: Field collection item: Release file */
$handler->display->display_options['fields']['field_release_file']['id'] = 'field_release_file';
$handler->display->display_options['fields']['field_release_file']['table'] = 'field_data_field_release_file';
$handler->display->display_options['fields']['field_release_file']['field'] = 'field_release_file';
$handler->display->display_options['fields']['field_release_file']['label'] = '';
$handler->display->display_options['fields']['field_release_file']['alter']['alter_text'] = TRUE;
$handler->display->display_options['fields']['field_release_file']['alter']['text'] = '[field_release_file] [filesize]';
$handler->display->display_options['fields']['field_release_file']['element_label_colon'] = FALSE;
$handler->display->display_options['fields']['field_release_file']['click_sort_column'] = 'fid';
/* Field: Field collection item: Release file hash */
$handler->display->display_options['fields']['field_release_file_hash']['id'] = 'field_release_file_hash';
$handler->display->display_options['fields']['field_release_file_hash']['table'] = 'field_data_field_release_file_hash';
$handler->display->display_options['fields']['field_release_file_hash']['field'] = 'field_release_file_hash';
$handler->display->display_options['fields']['field_release_file_hash']['label'] = '';
$handler->display->display_options['fields']['field_release_file_hash']['exclude'] = TRUE;
$handler->display->display_options['fields']['field_release_file_hash']['element_label_colon'] = FALSE;
$handler->display->display_options['argument_input'] = array(
  'nid' => array(
    'type' => 'context',
    'context' => 'entity:node.nid',
    'context_optional' => 0,
    'panel' => '0',
    'fixed' => '',
    'label' => 'Content: Nid',
  ),
);
$translatables['project_release_files'] = array(
  t('Master'),
  t('more'),
  t('Apply'),
  t('Reset'),
  t('Sort by'),
  t('Asc'),
  t('Desc'),
  t('file from field_release_file'),
  t('field_release_files'),
  t('Download'),
  t('Size'),
  t('md5 hash'),
  t('All'),
  t('Content pane'),
  t('([filesize])'),
  t('[field_release_file] [filesize]'),
  t('View panes'),
);
