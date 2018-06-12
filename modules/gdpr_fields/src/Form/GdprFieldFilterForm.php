<?php

namespace Drupal\gdpr_fields\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\gdpr_fields\Entity\GdprField;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Filter form for GDPR field list page.
 *
 * @package Drupal\gdpr_fields\Form
 */
class GdprFieldFilterForm extends FormBase {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * GdprFieldFilterForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gdpr_fields_field_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $filters = self::getFilters($this->getRequest());

    $form['container'] = [];

    $entities = [];
    foreach ($this->entityTypeManager->getDefinitions() as $key => $definition) {
      // Exclude anything not fieldable (ie config entities)
      if ($definition->entityClassImplements(FieldableEntityInterface::class)) {
        $entities[$key] = $definition->getLabel();
      }
    }

    $form['container']['search'] = [
      '#type' => 'textfield',
      '#title' => t('Search'),
      '#default_value' => $filters['search'],
    ];

    $form['container']['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity Type'),
      '#options' => $entities,
      '#multiple' => TRUE,
      '#default_value' => $filters['entity_type'],
    ];

    $form['container']['rta'] = [
      '#type' => 'select',
      '#title' => $this->t('Right to access'),
      '#options' => ['' => 'Not configured'] + GdprField::rtaOptions(),
      '#multiple' => TRUE,
      '#default_value' => $filters['rta'],
    ];

    $form['container']['rtf'] = [
      '#type' => 'select',
      '#title' => $this->t('Right to be forgotten'),
      '#options' => ['' => 'Not configured'] + GdprField::rtfOptions(),
      '#multiple' => TRUE,
      '#default_value' => $filters['rtf'],
    ];

    $form['container']['empty'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Filter out Entities where all fields are not configured'),
      '#default_value' => $filters['empty'],
    ];

    $form['container']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      '#button_type' => 'primary',
      '#name' => 'Apply',
    ];

    $form['container']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset'),
      '#name' => 'Reset',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#name'] == 'Reset') {
      $arguments = [];
    }
    else {
      $arguments = [
        'search' => $form_state->getValue('search'),
        'entity_type' => $form_state->getValue('entity_type'),
        'rta' => $form_state->getValue('rta'),
        'rtf' => $form_state->getValue('rtf'),
        'empty' => $form_state->getValue('empty'),
      ];
    }

    $form_state->setRedirect('gdpr_fields.fields_list', $arguments);
  }

  /**
   * Gets gdpr field filters from the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @return array
   *   The filters keyed by filter name.
   */
  public static function getFilters(Request $request) {
    $filters = [
      'search' => $request->get('search'),
      'entity_type' => $request->get('entity_type'),
      'rta' => $request->get('rta'),
      'rtf' => $request->get('rtf'),
      'empty' => $request->get('empty'),
    ];

    if ($filters['rtf'] === NULL) {
      $filters['rtf'] = [];
    }
    if ($filters['rta'] === NULL) {
      $filters['rta'] = [];
    }
    return $filters;
  }

}
