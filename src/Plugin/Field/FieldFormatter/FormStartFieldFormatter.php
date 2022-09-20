<?php

namespace Drupal\bhcc_form_start\Plugin\Field\FieldFormatter;

use Drupal\Core\Template\Attribute;
use Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter;
use Drupal\Core\Url;
use Drupal\link\LinkItemInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;

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

    // Handle private notice display.
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

    }

    // Does the user need to confirm that they have read a privacy statement
    // by clicking a checkbox?
    if ($use_privacy_notice) {

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

      $new_element[$delta]['privacy'][] = [
        '#type' => 'markup',
        '#markup' => $message_to_display_with_privacy_notice,
      ];

      // Privacy notice checkbox.
      $new_element[$delta]['privacy_checkbox'] = [
        '#type' => 'checkbox',
        //'#title' => $this->t('Please confirm that you have read the privacy statement'),
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

    // Check if the url matches one in which forms are switched off.
    $state = \Drupal::service('state');
    $forms_status = $state->get(self::STATE_PREFIX . 'forms_status');

    // Deal with page cache (anon users won't see the change without this).
    $element['#cache']['tags'][] = 'bhcc_form_start:status';
    \Drupal::service('page_cache_kill_switch')->trigger();

    // If forms are set to up just return the element unaltered.
    // Check explicitly if the forms are disabled, as this could be NULL
    // if the form status is not defined.
    if ($forms_status !== '0') {
      return $element;
    }

    // Form urls to check.
    // @TODO replace these with config / state versions.
    $form_site_urls = [
      'forms.brighton-hove.gov.uk',
      'formsstg.brighton-hove.gov.uk',
      'formsdev.brighton-hove.gov.uk',
      'citizenform.brighton-hove.gov.uk',
      'workplace.brighton-hove.gov.uk/form/'
    ];

    // Loop through each item to check if it needs to be replaced.
    $set_disabled_msg = FALSE;
    foreach ($items as $delta => $item) {

      // Get the original form url (to account for citizenID).
      $url = $item->getUrl() ?: NULL;
      if ($url) {

        // If the host matches a forms site, and a message has not been set,
        // replace the form button with the forms down message.
        // If a message has already been set on a previous delta,
        // just remove the form button.
        $url->setAbsolute(TRUE);
        $form_url_components = parse_url($url->toString());
        $form_url_base = $form_url_components['host'] . $form_url_components['path'];

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
          } else {
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
    $elementValue = $item->getValue();
    if ($url->getOption('use_citizenid') && $citizen_id_url) {
      $return_url = $this->buildCitizenFormUrl($citizen_id_url, $url, $item);
    } elseif ($url->getOption('extra_paremeters')) {
      $query_options = explode('&', $url->getOption('extra_paremeters'));
      $query = [];
      foreach ($query_options as $param_pair) {
        $param = explode('=', $param_pair);
        $query[$param[0]] = $param[1] ?? NULL;
      }
      $return_url = $url;
      $return_url->setOption('query', $query);
    } else {
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
   * Build CitizenID Form URL
   *
   * @param  string $citizen_form_url
   *   Citizen form url to use as base url.
   * @param  \Drupal\Core\Url $url
   *   Current Url object to transform.
   * @param  \Drupal\link\LinkItemInterface $item
   *   Link field item.
   * @return \Drupal\Core\Url
   *   Url modified to direct through the citizen ID form service.
   */
  protected function buildCitizenFormUrl(string $citizen_form_url, Url $url, LinkItemInterface $item):Url {
    $url->setAbsolute(TRUE);

    // Get url components.
    $components = parse_url($url->toString());

    // Get formstart Config
    $config = \Drupal::config('bhcc_form_start.settings');

    // Get entity
    $entity = $item->getEntity();

    // Get Service, need to check the field exists first.
    $serviceEntities = $entity->hasField('field_service') ? $entity->get('field_service')->referencedEntities() : NULL;

    // Set url to citizen ID.
    $drupalFormSiteVerifyPath = trim($config->get('citizen_id_drupal_form_site_verify_path'), " /\t\n\r\0\x0B");
    $redirectUrlEndForm = $components['scheme'] . '://' . $components['host'] . '/' . $drupalFormSiteVerifyPath . '?destination=' . $components['path'];

    // Add the extra paremters.
    $extraParams = $url->getOption('extra_paremeters');
    if ($extraParams) {
      $redirectUrlEndForm .= '&' . $extraParams;
    }

    // Extra paremeters for Citizen form.
    $formName = $entity->label();
    $serviceName = (is_array($serviceEntities) && !empty($serviceEntities) ? reset($serviceEntities)->label() : NULL);
    $source = 'Drupal';

    // Get the group ID if set.
    $group = $url->getOption('group');

    // Set up the query string.
    $query = [
      'RedirectUrlEndForm' => $redirectUrlEndForm,
      'FormName' => $formName,
      'ServiceName' => $serviceName,
      'Source' => $source,
    ];

    // As group is optional, add it seperatly if present.
    if ($group) {
      $query['Group'] = $group;
    }

    // Set URI
    $return_url = $url->fromUri($citizen_form_url, [
      'query' => $query,
    ]);

    return $return_url;
  }
}
