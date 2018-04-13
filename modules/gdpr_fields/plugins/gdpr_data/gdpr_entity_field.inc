<?php

/**
 * @file
 * Contains the gdpr_entity_field plugin definition.
 */
$plugin = array(
  // As this is the base class plugin, it shouldn't declare any menu items.
  'handler' => array(
    'class' => 'gdpr_entity_field',
  ),
  'get child' => 'gdpr_fields_gdpr_entity_field_get_child',
  'get children' => 'gdpr_fields_gdpr_entity_field_get_children',
);


function gdpr_fields_gdpr_entity_field_get_child($plugin, $parent, $child) {
  $plugins = gdpr_fields_gdpr_entity_field_get_children($plugin, $parent);
  return $plugins[$parent . ':' . $child];
}

function gdpr_fields_gdpr_entity_field_get_children($plugin, $parent) {
  $instances = field_info_instances();
  $plugins = array();

  foreach ($instances as $entity_type => $type_bundles) {
    foreach ($type_bundles as $bundle => $bundle_instances) {
      foreach ($bundle_instances as $field_name => $instance) {
//        $field = field_info_field($field_name);
        $name = "{$parent}:{$entity_type}|{$bundle}|{$field_name}";
        $child_plugin = $plugin;
        $child_plugin['name'] = $name;
        $child_plugin['label'] = $instance['label'];
        $child_plugin['description'] = $instance['description'];
        $plugins[$name] = $child_plugin;
      }
    }
  }

  return $plugins;
}