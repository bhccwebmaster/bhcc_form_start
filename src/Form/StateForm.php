<?php

namespace Drupal\bhcc_form_start\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Form start state form.
 */
class StateForm extends FormBase {

  const STATE_PREFIX = 'bhcc_form_state.';

  /**
   * Drupal\Core\State\StateInterface definition.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drupal\Core\Cache\CacheTagsInvalidatorInterface definition.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->state = $container->get('state');
    $instance->configFactory = $container->get('config.factory');
    $instance->cacheTagsInvalidator = $container->get('cache_tags.invalidator');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bhcc_form_start_state';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['on_off'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Form start state'),
    ];
    $form['on_off']['forms_status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Forms Status'),
      '#options' => [1 => $this->t('On'), 0 => $this->t('Off')],
      '#default_value' => $this->state->get(self::STATE_PREFIX . 'forms_status') ?? 1,
      '#weight' => '0',
    ];
    $default_message = $this->state->get(self::STATE_PREFIX . 'message_to_display_when_form_off');
    $form['on_off']['message_to_display_when_form_off'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Message to display when form unavailable'),
      '#rows' => 3,
      '#default_value' => $default_message['value'] ?? 'This form is currently unavailable.',
      '#format' => $default_message['format'] ?? NULL,
      '#weight' => '0',
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save the form values to state, not config.
    $ignore_keys = [
      'form_build_id',
      'form_id',
      'form_token',
      'op',
      'submit',
    ];
    foreach ($form_state->getValues() as $key => $value) {
      if (!in_array($key, $ignore_keys)) {
        $this->state->set(self::STATE_PREFIX . $key, $value);
      }
    }

    // Invalidate the form start cache tag, so forms show as en/disabled.
    $this->cacheTagsInvalidator->invalidateTags(['bhcc_form_start:status']);
  }

}
