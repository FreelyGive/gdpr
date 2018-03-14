<?php

namespace Drupal\gdpr_consent\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\gdpr_consent\Entity\ConsentAgreement;


/**
 * Plugin implementation of the 'gdpr_consent_widget' widget.
 *
 * Provides the ability to attach a consent agreement to a form.
 *
 * @FieldWidget(
 *   id = "gdpr_consent_widget",
 *   label = @Translation("GDPR Consent"),
 *   description = @Translation("GDPR Consent"),
 *   field_types = {
 *     "gdpr_user_consent",
 *   },
 * )
 */
class ConsentWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $agreement_id = $items->getFieldDefinition()->getSetting('target_id');
    $agreement = ConsentAgreement::load($agreement_id);
    $item = $items[$delta];

    $element['consent_text'] = [
      '#markup' => 'By submitting this form you agree to the following privacy policy: ' . $agreement->get('description')->value,
    ];

    $element['target_id'] = [
      '#type' => 'hidden',
      '#default_value' => $agreement_id,
    ];

    $element['target_revision_id'] = [
      '#type' => 'hidden',
      '#default_value' => isset($item->target_revision_id) ? $item->target_revision_id : $agreement->getRevisionId(),
    ];

    if ($agreement->requiresExplicitAcceptance()) {
      $element['agreed'] = [
        '#type' => 'checkbox',
        '#title' => 'I agree',
        '#required' => TRUE,
        '#default_value' => isset($item->agreed) && $item->agreed == TRUE,
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    for ($i = 0; $i < count($values); ++$i) {
      if (!isset($values[$i]['user_id'])) {
        $values[$i]['user_id'] = \Drupal::currentUser()->id();
      }
      if (!isset($values[$i]['date'])) {
        $values[$i]['date'] = date('Y-m-d H:i:s');
      }
    }
    return $values;
  }

}