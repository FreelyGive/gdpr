<?php


namespace Drupal\gdpr_fields\Form;


use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class GdprFieldFilterForm extends FormBase {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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

    $form['container'] = [
      '#prefix' => '<div class="ctools-export-ui-row ctools-export-ui-top-row clearfix">',
      '#suffix' => '</div>',
    ];

    $entities = [];
    foreach ($this->entityTypeManager->getDefinitions() as $key => $definition) {
      $entities[$key] = $definition->getLabel();
    }

    $form['container']['search'] = [
      '#type' => 'textfield',
      '#title' => t('Search'),
      '#default_value' => $filters['search'],
    ];

    $form['container']['gdpr_entity'] = [
      '#type' => 'select',
      '#title' => t('Entity'),
      '#options' => $entities,
      '#multiple' => TRUE,
      '#default_value' => $filters['gdpr_entity'],
    ];

    $form['container']['rta'] = [
      '#type' => 'select',
      '#title' => t('Right to access'),
      '#options' => $this->rtaOptions(),
      '#multiple' => TRUE,
      '#default_value' => $filters['rta'],
    ];

    $form['container']['rtf'] = [
      '#type' => 'select',
      '#title' => t('Right to be forgotten'),
      '#options' => $this->rtfOptions(),
      '#multiple' => TRUE,
      '#default_value' => $filters['rtf'],
    ];

    $form['container']['empty'] = [
      '#type' => 'checkbox',
      '#title' => t('Filter out Entities where all fields are not configured'),
      '#default_value' => $filters['empty'],
    ];

    $form['container']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      '#button_type' => 'primary',
      '#name' => 'Apply',
    ];

    return $form;
  }

  /**
   * Get the options array for right to access field.
   *
   * @return array
   *   Right to access field options array.
   */
  protected function rtaOptions() {
    return [
      '' => 'Not configured',
      'inc' => 'Included',
      'maybe' => 'Maybe included',
      'no' => 'Not included',
    ];
  }

  /**
   * Get the options array for right to be forgotten field.
   *
   * @return array
   *   Right to be forgotten field options array.
   */
  protected function rtfOptions() {
    return [
      '' => 'Not configured',
      'anonymise' => 'Anonymise',
      'remove' => 'Remove',
      'maybe' => 'Maybe included',
      'no' => 'Not included',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('gdpr_fields.fields_list', [
      'search' => $form_state->getValue('search'),
      'gdpr_entity' => $form_state->getValue('gdpr_entity'),
      'rta' => $form_state->getValue('rta'),
      'rtf' => $form_state->getValue('rtf'),
      'empty' => $form_state->getValue('empty'),
    ]);
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
      'gdpr_entity' => $request->get('gdpr_entity'),
      'rta' => $request->get('rta'),
      'rtf' => $request->get('rtf'),
      'empty' => $request->get('empty'),
    ];

    if($filters['rtf'] === NULL) {
      $filters['rtf'] = [];
    }
    if($filters['rta'] === NULL) {
      $filters['rta'] = [];
    }
    return $filters;
  }

}