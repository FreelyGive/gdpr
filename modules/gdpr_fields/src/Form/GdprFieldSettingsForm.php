<?php

namespace Drupal\gdpr_fields\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\gdpr_fields\Entity\GdprField;
use Drupal\gdpr_fields\Entity\GdprFieldConfigEntity;
use Drupal\gdpr_fields\GDPRCollector;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * GDPR Field settings.
 *
 */
class GdprFieldSettingsForm extends FormBase {

  /**
   * The entity field manager used to work with fields.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Entity type manager used to work with types.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * GdprFieldSettingsForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Gets the configuration for an entity/bundle/field.
   *
   * @param string $entity_type
   *   Entity type.
   * @param string $bundle
   *   Bundle.
   * @param string $field_name
   *   Field.
   *
   * @return \Drupal\gdpr_fields\Entity\GdprField
   *   Field metadata.
   */
  private static function getConfig($entity_type, $bundle, $field_name): GdprField {
    $config = GdprFieldConfigEntity::load($entity_type) ?? GdprFieldConfigEntity::create(['id' => $entity_type]);
    $field_config = $config->getField($bundle, $field_name);
    return $field_config;
  }

  /**
   * Sets the GDPR settings for a field.
   *
   * @param string $entity_type
   *   Entity type.
   * @param string $bundle
   *   Bundle.
   * @param string $field_name
   *   Field.
   * @param boolean $enabled
   *   Whether GDPR is enabled for this field
   * @param string $rta
   *   Right to Access setting
   * @param string $rtf
   *   Right to be forgotten
   * @param string $sanitizer
   *  Sanitizer to use.
   * @param string $notes
   *   Notes
   *
   * @return \Drupal\gdpr_fields\Entity\GdprFieldConfigEntity
   *   The config entity.
   */
  private static function setConfig($entity_type, $bundle, $field_name, $enabled, $rta, $rtf, $sanitizer, $notes, $no_follow, $owner, $sars_filename) {
    $config = GdprFieldConfigEntity::load($entity_type) ?? GdprFieldConfigEntity::create(['id' => $entity_type]);
    $config->setField($bundle, $field_name, [
      'enabled' => $enabled,
      'rta' => $rta,
      'rtf' => $rtf,
      'sanitizer' => $sanitizer,
      'notes' => $notes,
      'sars_filename' => $sars_filename,
      'owner' => $owner,
      'no_follow' => $no_follow,
    ]);
    return $config;
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'gdpr_fields_edit_field_form';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type = NULL, $bundle_name = NULL, $field_name = NULL) {
    if (empty($entity_type) || empty($bundle_name) || empty($field_name)) {
      $this->messenger()->addWarning('Could not load field.');
      return [];
    }

    $field_defs = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle_name);

    if (!array_key_exists($field_name, $field_defs)) {
      $this->messenger()->addWarning("The field $field_name does not exist.");
      return [];
    }
    $field_def = $field_defs[$field_name];
    $form['#title'] = 'GDPR Settings for ' . $field_def->getLabel();

    static::buildFormFields($form, $entity_type, $bundle_name, $field_name);


    $form['entity_type'] = [
      '#type' => 'hidden',
      '#default_value' => $entity_type,
    ];

    $form['bundle'] = [
      '#type' => 'hidden',
      '#default_value' => $bundle_name,
    ];

    $form['field_name'] = [
      '#type' => 'hidden',
      '#default_value' => $field_name,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#button_type' => 'primary',
        '#name' => 'Save',
      ],
      'submit_cancel' => [
        '#type' => 'submit',
        '#weight' => 99,
        '#value' => $this->t('Cancel'),
        '#name' => 'Cancel',
        '#limit_validation_errors' => [],
      ],
    ];

    return $form;
  }

  /**
   * Builds the form fields for GDPR settings.
   *
   * This is in a separate method so it can also be attached to the regular
   * field settings page by hook.
   *
   * @see gdpr_fields_form_field_config_edit_form_submit
   *
   * @param array $form
   *   Form
   * @param string $entity_type
   *   Entity type
   * @param string $bundle_name
   *   Bundle
   * @param string $field_name
   *   Field
   */
  public static function buildFormFields(array &$form, $entity_type = NULL, $bundle_name = NULL, $field_name = NULL) {
    $config = static::getConfig($entity_type, $bundle_name, $field_name);

    /* @var \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager */
    /* @var \Drupal\gdpr_dump\Sanitizer\GdprSanitizerFactory $sanitizer_factory */
    $entity_type_manager = \Drupal::entityTypeManager();
    $field_manager = \Drupal::service('entity_field.manager');
    $sanitizer_factory = \Drupal::service('gdpr_dump.sanitizer_factory');

    $entity_definition = $entity_type_manager->getDefinition($entity_type);
    $field_definition = $field_manager->getFieldDefinitions($entity_type, $bundle_name)[$field_name];
    $sanitizer_definitions = $sanitizer_factory->getDefinitions();

    $form['gdpr_enabled'] = [
      '#type' => 'checkbox',
      '#title' => t('This is a GDPR field'),
      '#default_value' => $config->enabled,
    ];

    $form['gdpr_owner'] = [
      '#type' => 'value',
      '#value' => FALSE,
    ];
    $form['gdpr_no_follow'] = [
      '#type' => 'value',
      '#value' => FALSE,
    ];
    $form['gdpr_sars_filename'] = [
      '#type' => 'value',
      '#value' => FALSE,
    ];

    if ($field_definition->getType() == 'entity_reference') {
      $inner_entity_type = $field_definition->getSetting('target_type');
      //@todo exclude files?
      $form['gdpr_owner'] = [
        '#type' => 'checkbox',
        '#default_value' => $config->owner,
        '#title' => t('Field is owner'),
        '#description' => t('If checked, this entity will be included for any task including the %type this property references.', [
          '%type' => $entity_definition->getLabel(),
        ]),
        '#states' => [
          'visible' => [
            ':input[name="gdpr_enabled"]' => [
              'checked' => TRUE,
            ],
          ],
        ],
      ];

      $form['gdpr_no_follow'] = [
        '#type' => 'checkbox',
        '#default_value' => $config->noFollow,
        '#title' => t('Do no follow this relationship'),
        '#description' => t('If checked, this relationship will not be followed when looking for additional personal information.'),
        '#states' => [
          'visible' => [
            ':input[name="gdpr_enabled"]' => [
              'checked' => TRUE,
            ],
          ],
        ],
      ];

      // Target file.
      // @todo: Move to a form alter in gdpr_tasks.
      $form['gdpr_sars_filename'] = [
        '#type' => 'textfield',
        '#title' => t('SARs filename'),
        '#description' => t('Specify which file this should be included in. The base user will go into %main.', [
          '%main' => 'main.csv',
        ]),
        // Default to the entity type.
        '#default_value' => $config->sarsFilename ? $config->sarsFilename : $inner_entity_type,
        '#field_suffix' => '.csv',
        '#size' => 20,
        // Between RTA and RTF
        '#weight' => 15,
        '#required' => TRUE,
        '#states' => [
          'visible' => [
            ':input[name="gdpr_enabled"]' => ['checked' => TRUE],
            ':input[name="gdpr_no_follow"]' => ['checked' => FALSE],
          ],
        ]
      ];
    }

    $form['gdpr_rta'] = [
      '#type' => 'select',
      '#weight' => 10,
      '#title' => t('Right to access'),
      '#options' => [
        'inc' => 'Included',
        'maybe' => 'Maybe',
        'no' => 'Not Included',
      ],
      '#default_value' => $config->rta,
      '#states' => [
        'visible' => [
          ':input[name="gdpr_enabled"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form['gdpr_rtf'] = [
      '#weight' => 20,
      '#type' => 'select',
      '#title' => t('Right to be forgotten'),
      '#options' => [
        'anonymise' => 'Anonymise',
        'remove' => 'Remove',
        'maybe' => 'Maybe',
        'no' => 'Not Included',
      ],
      '#default_value' => $config->rtf,
      '#states' => [
        'visible' => [
          ':input[name="gdpr_enabled"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    // If this is the entity's ID, treat the removal as remove the entire
    // entity.
    if ($entity_definition->getKey('id') == $field_name) {
      unset($form['gdpr_rtf']['#options']['anonymise']);
      $form['gdpr_rtf']['#options']['remove'] = new TranslatableMarkup('Delete entire entity');
    }
    // Otherwise check if this can be removed.
    elseif (!GDPRCollector::propertyCanBeRemoved($entity_definition, $field_definition, $error_message)) {
      unset($form['gdpr_rtf']['#options']['remove']);
      $form['gdpr_rtf_disabled'] = [
        '#type' => 'item',
        '#markup' => new TranslatableMarkup('This field cannot be removed, only anonymised.'),
        '#description' => $error_message,
        //'#weight' => $form['gdpr_rtf']['#weight'] + 0.1,
      ];
    }

    // Force removal to 'no' for computed properties.
    if ($field_definition->isComputed()) {
      $form['gdpr_rtf']['#default_value'] = 'no';
      $form['gdpr_rtf']['#disabled'] = TRUE;
      $form['gdpr_rtf']['#description'] = '*This is a computed field and cannot be removed.';
    }

    // @todo what aboyt system fields (uuid etc?)

    $sanitizer_options = ['' => ''] + array_map(function ($s) {
        return $s['label'];
      }, $sanitizer_definitions);

    $form['gdpr_sanitizer'] = [
      '#type' => 'select',
      '#weight' => 30,
      '#title' => t('Sanitizer to use'),
      '#options' => $sanitizer_options,
      '#default_value' => $config->sanitizer,
      '#states' => [
        'visible' => [
          ':input[name="gdpr_enabled"]' => ['checked' => TRUE],
          ':input[name="gdpr_rtf"]' => ['value' => 'anonymise'],
        ],
      ],
    ];

    $form['gdpr_notes'] = [
      '#weight' => 40,
      '#type' => 'textarea',
      '#title' => 'Notes',
      '#default_value' => $config->notes,
      '#states' => [
        'visible' => [
          ':input[name="gdpr_enabled"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#name'] == 'Cancel') {
      $form_state->setRedirect('gdpr_fields.fields_list');
      return;
    }

    $config = static::setConfig(
      $form_state->getValue('entity_type'),
      $form_state->getValue('bundle'),
      $form_state->getValue('field_name'),
      $form_state->getValue('gdpr_enabled'),
      $form_state->getValue('gdpr_rta'),
      $form_state->getValue('gdpr_rtf'),
      $form_state->getValue('gdpr_sanitizer'),
      $form_state->getValue('gdpr_notes'),
      $form_state->getValue('gdpr_no_follow'),
      $form_state->getValue('gdpr_owner'),
      $form_state->getValue('gdpr_sars_filename')
    );

    $config->save();
    \Drupal::messenger()->addMessage('Field settings saved.');
    $form_state->setRedirect('gdpr_fields.fields_list');
  }

}