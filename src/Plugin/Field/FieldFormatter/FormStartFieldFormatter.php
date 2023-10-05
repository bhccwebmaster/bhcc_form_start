<?php

namespace Drupal\bhcc_form_start\Plugin\Field\FieldFormatter;

use Drupal\bhcc_localgov_services_api\ServiceHelper;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\link\LinkItemInterface;
use Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Plugin implementation of the 'form_start_field_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "bhcc_form_start_field_formatter",
 *   label = @Translation("Form start field formatter"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class FormStartFieldFormatter extends LinkFormatter {

  const STATE_PREFIX = 'bhcc_form_state.';

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    // Build the original URLs.
    $element = parent::viewElements($items, $langcode);

    // Handle private notice display and add arrow to button.
    foreach ($items as $delta => $item) {

      // Retrieve all the url/ information.
      $url = $item->getUrl();

      // Attach JS to swap MyAccount form urls.
      $module_handler = \Drupal::service('module_handler');
      $add_myaccount_url = $url->getOption('add_myaccount_url');
      if ($module_handler->moduleExists('bhcc_myaccount') && $add_myaccount_url) {
        $element['#attached']['library'][] = 'bhcc_myaccount/myaccount_session';
      }

      // Get privacy notice.
      $use_privacy_notice = $url->getOption('use_privacy_notice');
      $message_to_display_with_privacy_notice = $url->getOption('message_to_display_with_privacy_notice')['value'] ?? NULL;
      $message_to_display_with_privacy_notice_format = $url->getOption('message_to_display_with_privacy_notice')['value']['format'] ?? NULL;

      // Add aria-label attribute for accessibility.
      $element[$delta]['#options']['attributes']['aria-label'] = $element[$delta]['#title'] . ' (opens in a new tab)';

      // Add arrow icon to start button.
      $icon_render_array = [
        '#type' => 'html_tag',
        '#tag' => 'svg',
        '#attributes' => [
          'class' => [
            'button__icon',
            'button__icon--after',
          ],
          'xmlns' => 'http://www.w3.org/2000/svg',
          'viewBox' => '0 0 13 22',
          'width' => '13',
          'height' => '22',
          'aria-hidden' => 'true',
          'focusable' => 'false',
        ],
        'child' => [
          '#type' => 'html_tag',
          '#tag' => 'use',
          '#attributes' => [
            'xmlns' => 'http://www.w3.org/1999/xlink',
            'xlink:href' => '#arrow-right',
          ],
        ],
      ];
      $icon_html = \Drupal::service('renderer')->renderPlain($icon_render_array);
      $element[$delta]['#title'] = Markup::create($element[$delta]['#title'] . $icon_html);

      // Does the user need to confirm that they have read a privacy statement
      // by clicking a checkbox?
      if (!empty($use_privacy_notice)) {

        // Set a unique ID.
        $html_id = Html::getUniqueId('bhcc-form-start-with-privacy');

        // Create a container class for the privacy notice info so that we can
        // access it in javascript.
        $new_element[$delta] = [
          '#type' => 'container',
          '#id' => $html_id,
        ];

        $new_element[$delta]['#attributes'] = new Attribute();
        $new_element[$delta]['#attributes']->addClass('js-privacy-form-start');

        // Create a new element with all info for privacy notice.
        // Text and checkbox.
        // Add wrapper element for flex positioning.
        $new_element[$delta]['link'] = [
          '#type' => 'container',
        ];

        $new_element[$delta]['link']['elem'] = $element[$delta];

        // Add default link classes, disable the link by default.
        $new_element[$delta]['link']['elem']['#options']['attributes']['class'] = array_merge(
          [
            'js-cta-button',
            'link-disabled',
            'js-link-disabled',
          ],
          $new_element[$delta]['link']['elem']['#options']['attributes']['class']
        );

        // Privacy notice text.
        $new_element[$delta]['privacy'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => [
              'line-height-larger',
            ],
          ],
          '#weight' => -100,
        ];

        $new_element[$delta]['privacy']['child'] = [
          '#type' => 'processed_text',
          '#text' => $message_to_display_with_privacy_notice,
          '#format' => $message_to_display_with_privacy_notice_format,
        ];

        // Privacy notice checkbox.
        $new_element[$delta]['privacy_checkbox'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('I have read and understand'),
          '#id' => $html_id .= '--checkbox',
          '#weight' => -90,
          '#attributes' => [
            'class' => [
              'js-privacy-checkbox',
            ],
          ],
        ];

        // Attach privacy notice JS.
        $new_element[$delta]['#attached']['library'][] = 'bhcc_form_start/privacy_notice';

        // Set the element delta to this new element.
        $element[$delta] = $new_element[$delta];
      }

      // Get the global forms start status.
      $state = \Drupal::service('state');
      $forms_status = $state->get(self::STATE_PREFIX . 'forms_status');

      // Get the form group status.
      $form_start_group = $url->getOption('form_start_group');
      $form_group_status = 1;
      if ($form_start_group) {
        $form_group_status = $state->get(self::STATE_PREFIX . 'forms_status__' . $form_start_group);
      }

      // Deal with page cache (anon users won't see the change without this).
      $element['#cache']['tags'][] = 'bhcc_form_start:status';
      \Drupal::service('page_cache_kill_switch')->trigger();

      // If forms are set to up just return the element unaltered.
      // Check explicitly if the forms are disabled, as this could be NULL
      // if the form status is not defined.
      // includes check for the form start group status.
      if ($forms_status !== '0' && $form_group_status !== '0') {
        return $element;
      }

      // Form urls to check.
      // @todo replace these with config / state versions.
      $form_site_urls = [
        'forms.brighton-hove.gov.uk',
        'formsstg.brighton-hove.gov.uk',
        'formsdev.brighton-hove.gov.uk',
        'citizenform.brighton-hove.gov.uk',
        'workplace.brighton-hove.gov.uk/form/',
      ];

      // Check if each form needs to be disabled.
      $set_disabled_msg = FALSE;

      // Check against the original url (not the url generated in buildUrl).
      if ($url) {

        // If the host matches a forms site, and a message has not been set,
        // replace the form button with the forms down message.
        // If a message has already been set on a previous delta,
        // just remove the form button.
        $url->setAbsolute(TRUE);
        $form_url_components = parse_url($url->toString());
        $form_url_base = ($form_url_components['host'] ?? '') . ($form_url_components['path'] ?? '');
        // Loop through each form site url to compare as substr.
        $found_form_url = FALSE;
        foreach ($form_site_urls as $form_site_url) {

          // If url starts with a known form url, set found here.
          if (strpos($form_url_base, $form_site_url) === 0) {
            $found_form_url = TRUE;
          }
        }

        // Only block if the form url is found.
        if ($found_form_url) {
          if (!$set_disabled_msg) {
            $message = $state->get(self::STATE_PREFIX . 'message_to_display_when_form_off');
            $element[$delta] = [
              '#type' => 'markup',
              '#markup' => $message['value'] ?? 'Form is currently unavailable',
              '#prefix' => '<div class="bhcc-alert bhcc-alert-info">',
              '#suffix' => '</div>',
            ];
            $set_disabled_msg = TRUE;
          }
          else {
            unset($element[$delta]);
          }
        }
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildUrl(LinkItemInterface $item) {

    // Get config.
    // @todo use dependency injection to get config.
    $config = \Drupal::config('bhcc_form_start.settings');

    // Use LinkFormmater to build a uri.
    $url = parent::buildUrl($item);

    // Check if use citizen ID is set.
    // If so rewrite the url to the citizenform format,
    // else return plain url.
    $citizen_id_url = $config->get('citizen_id_start_url');
    if ($url->getOption('use_citizenid') && $citizen_id_url) {
      $return_url = $this->buildCitizenFormUrl($citizen_id_url, $url, $item);
    }
    elseif ($url->getOption('extra_paremeters')) {
      $query_options = explode('&', $url->getOption('extra_paremeters'));
      $query = [];
      foreach ($query_options as $param_pair) {
        $param = explode('=', $param_pair);
        $query[$param[0]] = $param[1] ?? NULL;
      }
      $return_url = $url;
      $return_url->setOption('query', $query);
    }
    else {
      $return_url = $url;
    }

    // Set attributes to render as a button.
    $attributes = new Attribute();
    $attributes->addClass([
      'button',
      'button--action',
      'button--single',
      'rounded',
      'margin-top-large',
    ]);
    $attributes->setAttribute('target', '_blank');

    // MyAccount extra parameters.
    $module_handler = \Drupal::service('module_handler');
    if ($module_handler->moduleExists('bhcc_myaccount') && $url->getOption('add_myaccount_url')) {
      $myaccount_config = \Drupal::config('bhcc_myaccount.settings');
      $myaccount_citizen_id_uri = $myaccount_config->get('myaccount_citizen_id_service_url');
      if ($myaccount_citizen_id_uri) {
        $myaccount_url = $this->buildCitizenFormUrl($myaccount_citizen_id_uri, $url, $item);
        $attributes['data-myaccount-url'] = $myaccount_url->toString();
      }
    }

    // Set the attributes.
    $return_url->setOption('attributes', $attributes->toArray());

    return $return_url;
  }

  /**
   * Build CitizenID Form URL.
   *
   * @param string $citizen_form_url
   *   Citizen form url to use as base url.
   * @param \Drupal\Core\Url $url
   *   Current Url object to transform.
   * @param \Drupal\link\LinkItemInterface $item
   *   Link field item.
   *
   * @return \Drupal\Core\Url
   *   Url modified to direct through the citizen ID form service.
   */
  protected function buildCitizenFormUrl(string $citizen_form_url, Url $url, LinkItemInterface $item):Url {
    $url->setAbsolute(TRUE);

    // Get url components.
    $components = parse_url($url->toString());

    // Get formstart Config.
    $config = \Drupal::config('bhcc_form_start.settings');

    // Get entity.
    $entity = $item->getEntity();

    // Get Service if set.
    $service_entity = self::getServiceEntity($entity);

    // Set url to citizen ID.
    $drupal_form_site_verify_path = trim($config->get('citizen_id_drupal_form_site_verify_path'), " /\t\n\r\0\x0B");
    $redirect_url_end_form = $components['scheme'] . '://' . $components['host'] . '/' . $drupal_form_site_verify_path . '?destination=' . ($components['path'] ?? '');

    // Add the extra paremters.
    $extra_params = $url->getOption('extra_paremeters');
    if ($extra_params) {
      $redirect_url_end_form .= '&' . $extra_params;
    }

    // Extra paremeters for Citizen form.
    // Form name.
    $form_name = $entity->label();

    // If the form start button is on a paragraph, fetch the entity it is on.
    if ($entity instanceof ParagraphInterface) {
      $parent = $entity;
      while ($parent instanceof ParagraphInterface) {
        $parent = $parent->getParentEntity();
      }
      if ($parent instanceof EntityInterface) {
        $form_name = $parent->label();
      }
    }

    // Add service name.
    $service_name = ($service_entity instanceof NodeInterface ? $service_entity->label() : NULL);

    // Set source as Drupal.
    $source = 'Drupal';

    // Get the group ID if set.
    $group = $url->getOption('group');

    // Set up the query string.
    // Adding urlencoding even though Drupal will also encode,
    // This is because mendix handle the incoming destination parameter.
    $query = [
      'RedirectUrlEndForm' => urlencode($redirect_url_end_form),
      'FormName' => $form_name,
      'ServiceName' => $service_name,
      'Source' => $source,
    ];

    // As group is optional, add it seperatly if present.
    if ($group) {
      $query['Group'] = $group;
    }

    // Set URI.
    $return_url = $url->fromUri($citizen_form_url, [
      'query' => $query,
    ]);

    return $return_url;
  }

  /**
   * Get the service entity if one is set.
   *
   * Done statically so the dependency on bhcc_localgov_services_api
   * can be made optional.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the form start button is on (node / paragraph).
   *
   * @return \Drupal\node\NodeInterface|null
   *   The service entity, or NULL if not set.
   */
  public static function getServiceEntity(EntityInterface $entity) {
    $service_entity = NULL;
    if (\Drupal::hasService('bhcc_localgov_services_api.service_helper')) {

      /** @var \Drupal\bhcc_localgov_services_api\ServiceHelper $service_helper*/
      $service_helper = \Drupal::service('bhcc_localgov_services_api.service_helper');
      $service = NULL;

      // If node.
      if ($entity instanceof NodeInterface) {
        $service = $service_helper->serviceFromNode($entity);
      }
      // If paragraph, load based on the parent.
      elseif ($entity instanceof ParagraphInterface) {

        // Loop up through parents to find root parent entity.
        $parent = $entity;
        while ($parent instanceof ParagraphInterface) {
          $parent = $parent->getParentEntity();
        }
        if ($parent instanceof NodeInterface) {
          $service = $service_helper->serviceFromNode($parent);
        }
      }
      // Check the service is an instance of service helper.
      if ($service instanceof ServiceHelper) {
        $service_entity = $service->getServiceLanding()->getNode();
      }
    }

    return $service_entity;
  }

}
