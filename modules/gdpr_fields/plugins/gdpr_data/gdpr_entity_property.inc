<?php

/**
 * @file
 * Contains the gdpr_entity_property plugin definition.
 */
$plugin = array(
  // As this is the base class plugin, it shouldn't declare any menu items.
  'has menu' => FALSE,
  'handler' => array(
    'class' => 'gdpr_entity_property',
  ),
  'get child' => 'gdpr_fields_gdpr_entity_property_get_child',
  'get children' => 'gdpr_fields_gdpr_entity_property_get_children',
);

function gdpr_fields_gdpr_entity_property_get_child($plugin, $parent, $child) {
  $plugins = gdpr_fields_gdpr_entity_property_get_children($plugin, $parent);
  return $plugins[$parent . ':' . $child];
}

function gdpr_fields_gdpr_entity_property_get_children($plugin, $parent) {
  $entities = entity_get_info();

  $plugins = array();
  foreach (array_keys($entities) as $entity_type) {

    $info = entity_get_info($entity_type);
    $property_info = entity_get_property_info($entity_type);

    if (empty($property_info)) {
      continue;
    }

    foreach (array_keys($info['bundles']) as $bundle) {
      $properties = array();

      // Default properties
      foreach ($property_info['properties'] as $property_name => $property) {
        $name = "{$parent}:{$entity_type}|{$bundle}|{$property_name}";
        $properties[$name] = $property;
      }

      // Bundle properties
      if (isset($property_info['bundles'][$bundle])) {
        foreach ($property_info['bundles'][$bundle]['properties'] as $property_name => $property) {
          $name = "{$parent}:{$entity_type}|{$bundle}|{$property_name}";
          $properties[$name] = $property;
        }
      }

      // Add plugins for properties.
      foreach ($properties as $name => $property) {
        if (!empty($property['field'])) {
          continue;
        }
        $child_plugin = $plugin;
        $child_plugin['name'] = $name;
        $child_plugin['label'] = $property['label'];
        $child_plugin['description'] = $property['description'];
        // @todo Should computed properties be removed instead or disabled?
        if (!empty($property['computed'])) {
          $child_plugin['computed'] = TRUE;
        }
        $plugins[$name] = $child_plugin;
      }
    }
  }

  return $plugins;
}