<?php

namespace Drupal\gdpr_consent\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataReferenceTargetDefinition;
use Drupal\gdpr_consent\Entity\ConsentAgreement;

/**
 * Plugin implementation of the 'gdpr_user_consent' field type.
 *
 * @FieldType(
 *   id = "gdpr_user_consent",
 *   label = @Translation("GDPR Consent"),
 *   description = @Translation("Stores user consent for a particular
 *   agreement"), category = @Translation("GDPR"), default_widget =
 *   "gdpr_consent_widget", default_formatter = "gdpr_consent_formatter",
 * )
 */
class UserConsentItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return ['target_id' => '']
      + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['target_id'] = DataReferenceTargetDefinition::create('integer')
      ->setLabel('Target agreement ID')
      ->setSetting('unsigned', TRUE)
      ->setRequired(TRUE);

    $properties['target_revision_id'] = DataDefinition::create('integer')
      ->setLabel('Revision ID');

    $properties['agreed'] = DataDefinition::create('boolean')
      ->setLabel('Agreed');

    $properties['date'] = DataDefinition::create('datetime_iso8601')
      ->setLabel('Date stored');

    $properties['user_id'] = DataReferenceTargetDefinition::create('integer')
      ->setLabel('User ID');

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $agreement_ids = \Drupal::entityQuery('gdpr_consent_agreement')
      ->condition('status', 1)
      ->sort('title')
      ->execute();

    $agreements = ConsentAgreement::loadMultiple($agreement_ids);

    $element = [];

    $element['agreement'] = [
      '#type' => 'details',
      '#title' => 'Agreement',
      '#open' => TRUE,
      '#tree' => TRUE,
      'agreement' => [
        '#type' => 'select',
        '#required' => TRUE,
        '#options' => $agreements,
        '#default_value' => $this->getSetting('target_id'),
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = [
      'indexes' => [
        'target_id' => ['target_id'],
      ],
    ];

    $schema['columns']['target_id'] = [
      'description' => 'The ID of the target entity.',
      'type' => 'int',
      'unsigned' => TRUE,
    ];

    $schema['columns']['target_revision_id'] = [
      'description' => 'The Revision ID of the target entity.',
      'type' => 'int',
    ];

    $schema['columns']['agreed'] = [
      'description' => 'Whether the user has agreed.',
      'type' => 'int',
      'size' => 'tiny',
      'default' => 0,
    ];

    $schema['columns']['user_id'] = [
      'description' => 'The user ID',
      'type' => 'int',
    ];

    $schema['columns']['date'] = [
      'description' => 'Time that the user agreed.',
      'type' => 'varchar',
      'length' => 20,
    ];

    return $schema;
  }

}
