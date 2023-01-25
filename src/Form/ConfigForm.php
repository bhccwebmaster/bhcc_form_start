<?php

namespace Drupal\bhcc_form_start\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class Form start global config form.
 */
class ConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'bhcc_form_start.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bhcc_form_start_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('bhcc_form_start.settings');
    $form['citizen_id_start_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Citizen ID start page URL'),
      '#description' => $this->t('The URL where users need to be sent to in order to go through Citizen ID.'),
      '#size' => 64,
      '#default_value' => $config->get('citizen_id_start_url'),
    ];
    $form['citizen_id_drupal_form_site_verify_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Drupal Form site path.'),
      '#description' => $this->t('The Path (excluding url) to redirect to once user completes citizen ID.'),
      '#size' => 64,
      '#default_value' => $config->get('citizen_id_drupal_form_site_verify_path'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('bhcc_form_start.settings')
      ->set('citizen_id_start_url', $form_state->getValue('citizen_id_start_url'))
      ->set('citizen_id_drupal_form_site_verify_path', $form_state->getValue('citizen_id_drupal_form_site_verify_path'))
      ->save();
  }

}
