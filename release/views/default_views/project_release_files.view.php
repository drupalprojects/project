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
$handler->display->display_options['css_class'] = 'no-hashes';
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
$handler->display->display_options['style_options']['row_class_special'] = FALSE;
$handler->display->display_options['defaults']['style_options'] = FALSE;
$handler->display->display_options['defaults']['row_plugin'] = FALSE;
$handler->display->display_options['row_plugin'] = 'fields';
$handler->display->display_options['defaults']['row_options'] = FALSE;
$handler->display->display_options['defaults']['fields'] = FALSE;
/* Field: Field collection item: Release file */
$handler->display->display_options['fields']['field_release_file']['id'] = 'field_release_file';
$handler->display->display_options['fields']['field_release_file']['table'] = 'field_data_field_release_file';
$handler->display->display_options['fields']['field_release_file']['field'] = 'field_release_file';
$handler->display->display_options['fields']['field_release_file']['label'] = '';
$handler->display->display_options['fields']['field_release_file']['exclude'] = TRUE;
$handler->display->display_options['fields']['field_release_file']['alter']['text'] = '[field_release_file] [filesize]';
$handler->display->display_options['fields']['field_release_file']['element_label_colon'] = FALSE;
$handler->display->display_options['fields']['field_release_file']['click_sort_column'] = 'fid';
$handler->display->display_options['fields']['field_release_file']['type'] = 'file_url_plain';
/* Field: File: Name */
$handler->display->display_options['fields']['filename']['id'] = 'filename';
$handler->display->display_options['fields']['filename']['table'] = 'file_managed';
$handler->display->display_options['fields']['filename']['field'] = 'filename';
$handler->display->display_options['fields']['filename']['relationship'] = 'field_release_file_fid';
$handler->display->display_options['fields']['filename']['label'] = '';
$handler->display->display_options['fields']['filename']['exclude'] = TRUE;
$handler->display->display_options['fields']['filename']['element_label_colon'] = FALSE;
/* Field: File: Extension */
$handler->display->display_options['fields']['extension']['id'] = 'extension';
$handler->display->display_options['fields']['extension']['table'] = 'file_managed';
$handler->display->display_options['fields']['extension']['field'] = 'extension';
$handler->display->display_options['fields']['extension']['relationship'] = 'field_release_file_fid';
$handler->display->display_options['fields']['extension']['label'] = '';
$handler->display->display_options['fields']['extension']['alter']['alter_text'] = TRUE;
$handler->display->display_options['fields']['extension']['alter']['text'] = 'Download <span class="filename">[filename]</span><span class="extension">[extension]</span>';
$handler->display->display_options['fields']['extension']['alter']['make_link'] = TRUE;
$handler->display->display_options['fields']['extension']['alter']['path'] = '[field_release_file]';
$handler->display->display_options['fields']['extension']['alter']['link_class'] = 'download';
$handler->display->display_options['fields']['extension']['element_label_colon'] = FALSE;
$handler->display->display_options['fields']['extension']['element_wrapper_type'] = '0';
$handler->display->display_options['fields']['extension']['element_default_classes'] = FALSE;
$handler->display->display_options['fields']['extension']['extension_detect_tar'] = TRUE;
/* Field: File: Size */
$handler->display->display_options['fields']['filesize']['id'] = 'filesize';
$handler->display->display_options['fields']['filesize']['table'] = 'file_managed';
$handler->display->display_options['fields']['filesize']['field'] = 'filesize';
$handler->display->display_options['fields']['filesize']['relationship'] = 'field_release_file_fid';
$handler->display->display_options['fields']['filesize']['label'] = '';
$handler->display->display_options['fields']['filesize']['element_label_colon'] = FALSE;
$handler->display->display_options['fields']['filesize']['element_wrapper_type'] = '0';
/* Field: Field collection item: Release file hash */
$handler->display->display_options['fields']['field_release_file_hash']['id'] = 'field_release_file_hash';
$handler->display->display_options['fields']['field_release_file_hash']['table'] = 'field_data_field_release_file_hash';
$handler->display->display_options['fields']['field_release_file_hash']['field'] = 'field_release_file_hash';
$handler->display->display_options['fields']['field_release_file_hash']['label'] = 'MD5';
$handler->display->display_options['fields']['field_release_file_hash']['element_type'] = 'span';
$handler->display->display_options['fields']['field_release_file_hash']['element_class'] = 'hash';
$handler->display->display_options['fields']['field_release_file_hash']['element_label_type'] = 'span';
/* Field: Field collection item: Release file SHA-1 hash */
$handler->display->display_options['fields']['field_release_file_sha1']['id'] = 'field_release_file_sha1';
$handler->display->display_options['fields']['field_release_file_sha1']['table'] = 'field_data_field_release_file_sha1';
$handler->display->display_options['fields']['field_release_file_sha1']['field'] = 'field_release_file_sha1';
$handler->display->display_options['fields']['field_release_file_sha1']['label'] = 'SHA-1';
$handler->display->display_options['fields']['field_release_file_sha1']['element_type'] = 'span';
$handler->display->display_options['fields']['field_release_file_sha1']['element_class'] = 'hash';
$handler->display->display_options['fields']['field_release_file_sha1']['element_label_type'] = 'span';
/* Field: Field collection item: Release file SHA-256 hash */
$handler->display->display_options['fields']['field_release_file_sha256']['id'] = 'field_release_file_sha256';
$handler->display->display_options['fields']['field_release_file_sha256']['table'] = 'field_data_field_release_file_sha256';
$handler->display->display_options['fields']['field_release_file_sha256']['field'] = 'field_release_file_sha256';
$handler->display->display_options['fields']['field_release_file_sha256']['label'] = 'SHA-256';
$handler->display->display_options['fields']['field_release_file_sha256']['element_type'] = 'span';
$handler->display->display_options['fields']['field_release_file_sha256']['element_class'] = 'hash';
$handler->display->display_options['fields']['field_release_file_sha256']['element_label_type'] = 'span';
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
  t('[field_release_file] [filesize]'),
  t('Download <span class="filename">[filename]</span><span class="extension">[extension]</span>'),
  t('MD5'),
  t('SHA-1'),
  t('SHA-256'),
  t('View panes'),
);
