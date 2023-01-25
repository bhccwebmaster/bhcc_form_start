<?php

namespace Drupal\bhcc_form_start\Plugin\Field\FieldWidget;

use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'bhcc_form_start_field_widget' widget.
 *
 * @FieldWidget(
 *   id = "bhcc_form_start_field_widget",
 *   module = "bhcc_form_start",
 *   label = @Translation("Form Start Button"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class FormStartFieldWidget extends LinkWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $form_values = $items[$delta]->getValue();

    // Get form start groups.
    $form_group_storage = \Drupal::service('entity_type.manager')
      ->getStorage('bhcc_form_start_group');
    $form_start_group_ids = $form_group_storage->getQuery()
      ->execute();
    $form_start_groups = array_map(function($id) use ($form_group_storage) {
      return $form_group_storage->load($id)->label();
    }, $form_start_group_ids);

    // Form start group select.
    $element['form_start_group'] = [
      '#type' => 'select',
      '#title' => $this->t('Form start group'),
      '#options' => $form_start_groups,
      '#default_value' => $form_values['options']['form_start_group'] ?? NULL,
      '#empty_option' => $this->t('- No group -'),
      '#empty_value' => '',
    ];

    // CitizenID form elements.
    $element['use_citizenid'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('This form requires the Citizen ID service.'),
      '#default_value' => $form_values['options']['use_citizenid'] ?? NULL,
    ];
    $element['extra_paremeters'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Extra parameters to pass through in format param1=value1&amp;param2=value2 etc...'),
      '#default_value' => $form_values['options']['extra_paremeters'] ?? NULL,
      '#maxlength' => 1024,
    ];
    $element['group'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Citizen form group ID.'),
      '#default_value' => $form_values['options']['group'] ?? NULL,
      '#maxlength' => 32,
    ];

    // Check box to display privacy notice checkbox.
    $element['use_privacy_notice'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('This form requires a privacy notice.'),
      '#default_value' => $form_values['options']['use_privacy_notice'] ?? NULL,
    ];

    $element['message_to_display_with_privacy_notice'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Privacy Notice text'),
      '#rows' => 3,
      '#default_value' => $form_values['options']['message_to_display_with_privacy_notice']['value'] ?? NULL,
      '#format' => $form_values['options']['message_to_display_with_privacy_notice']['format'] ?? NULL,
    ];

    // MyAccount extra parameters.
    $module_handler = \Drupal::service('module_handler');
    if ($module_handler->moduleExists('bhcc_myaccount')) {
      $element['add_myaccount_url'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Generate a MyAccount link for this form.'),
        '#default_value' => $form_values['options']['add_myaccount_url'] ?? NULL,
      ];
    }

    return $element;

  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    parent::massageFormValues($values, $form, $form_state);
    foreach ($values as &$value) {

      // Store the form start group.
      if (isset($value['form_start_group'])) {
        $value['options']['form_start_group'] = $value['form_start_group'];
      }

      // Set the value of use Citizen ID to the form options array.
      if (isset($value['use_citizenid'])) {
        $value['options']['use_citizenid'] = $value['use_citizenid'];
      }
      if (isset($value['extra_paremeters'])) {
        $value['options']['extra_paremeters'] = $value['extra_paremeters'];
      }
      if (isset($value['group'])) {
        $value['options']['group'] = $value['group'];
      }

      // Store privacy notice checkbox.
      if (isset($value['use_privacy_notice'])) {
        $value['options']['use_privacy_notice'] = $value['use_privacy_notice'];
      }

      // Store privacy notice text.
      if (isset($value['message_to_display_with_privacy_notice'])) {
        $value['options']['message_to_display_with_privacy_notice'] = $value['message_to_display_with_privacy_notice'];
      }

      // Store MyAccount checkbox.
      if (isset($value['add_myaccount_url'])) {
        $value['options']['add_myaccount_url'] = $value['add_myaccount_url'];
      }
    }
    return $values;
  }

}
