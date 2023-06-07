<?php

namespace Drupal\Tests\bhcc_form_start\Functional;

use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\link\LinkItemInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for the form start button.
 */
class FormStartDisplayTest extends BrowserTestBase {

  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'path',
    'node',
    'link',
    'bhcc_form_start',
  ];

  /**
   * A user with the correct permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Test the Form start button field formatter.
   */
  public function testFormStartButton() {

    // Create the Admin user.
    $this->adminUser = $this->drupalCreateUser([
      'administer nodes',
      'bypass node access',
    ]);
    $this->drupalLogin($this->adminUser);

    // Set up a content type with a link,
    // setting the display to form start button.
    $this->createContentType(['type' => 'form_start']);

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'form_start_button',
      'entity_type' => 'node',
      'type' => 'link',
    ]);

    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'form_start',
      'settings' => [
        'title' => DRUPAL_DISABLED,
        'link_type' => LinkItemInterface::LINK_GENERIC,
      ],
    ]);

    $field->save();

    $display = $this->container->get('entity_type.manager')
      ->getStorage('entity_view_display')
      ->load('node.form_start.default');
    $link_component = [
      'type' => 'bhcc_form_start_field_formatter',
      'label' => 'hidden',
      'settings' => [
        'trim_length' => 80,
        'url_only' => FALSE,
        'url_plain' => FALSE,
        'rel' => '',
        'target' => '',
      ],
      'third_party_settings' => [],
      'weight' => 102,
      'region' => 'content',
    ];
    $display->setComponent('form_start_button', $link_component);
    $display->save();

    // Test form links.
    $form_links = [
      // Standard link.
      [
        'title' => 'Start ' . $this->randomMachineName(8),
        'uri' => 'https://example.com/form/' . $this->randomMachineName(8),
      ],
      // Link to form site.
      [
        'title' => 'Start ' . $this->randomMachineName(8),
        'uri' => 'https://forms.brighton-hove.gov.uk/form/' . $this->randomMachineName(8),
      ],
      // Link to form site with citizenID.
      [
        'title' => 'Start ' . $this->randomMachineName(8),
        'uri' => 'https://forms.brighton-hove.gov.uk/form/' . $this->randomMachineName(8),
        'options' => [
          'use_citizenid' => 1,
        ],
      ],
      // Link to form site with privacy notice.
      [
        'title' => 'Start ' . $this->randomMachineName(8),
        'uri' => 'https://example.com/form/' . $this->randomMachineName(8),
        'options' => [
          'use_privacy_notice' => 1,
          'message_to_display_with_privacy_notice' => [
            'value' => $this->randomMachineName(256),
            'format' => 'plain_text',
          ],
        ],
      ],
      // Empty link.
      // @see https://github.com/bhccwebmaster/bhcclocalgov/issues/1159
      [],
    ];

    // Create and test each form start button.
    foreach ($form_links as $form_link) {
      $node[] = $this->createNode([
        'title' => 'Form start - ' . $this->randomMachineName(16),
        'type' => 'form_start',
        'form_start_button' => $form_link,
        'status' => NodeInterface::PUBLISHED,
      ]);
    }

    // Test the presense of a form start button.
    $this->drupalGet($node[0]->toUrl()->toString());
    $this->assertSession()->linkExists($form_links[0]['title'], 0);
    $this->assertSession()->linkByHrefExists($form_links[0]['uri'], 0);

    // Test the presense of a form start button.
    $this->drupalGet($node[1]->toUrl()->toString());
    $this->assertSession()->linkExists($form_links[1]['title'], 0);
    $this->assertSession()->linkByHrefExists($form_links[1]['uri'], 0);

    // Test form url is rewritten for citizenid.
    $this->drupalGet($node[2]->toUrl()->toString());
    $this->assertSession()->linkExists($form_links[2]['title'], 0);
    $this->assertSession()->linkByHrefNotExists($form_links[2]['uri'], 0);

    // Rewrite citizenid url.
    $components = parse_url($form_links[2]['uri']);
    $citizenform_url = 'https://citizenform.brighton-hove.gov.uk/link/CitizenFormGeneric';
    $citizenid_verify_url = $components['scheme'] . '://' . $components['host'] . '/' . 'citizenid-verify';
    $form_path = $components['path'];
    $citizenid_href = Url::fromUri($citizenform_url, [
      'query' => [
        'RedirectUrlEndForm' => $citizenid_verify_url . '?destination=' . $form_path,
      ],
    ])->toString();
    $this->assertSession()->linkByHrefExists($citizenid_href, 0);

    // Test the presense of a form start button and privacy notice.
    $this->drupalGet($node[3]->toUrl()->toString());
    $this->assertSession()->linkExists($form_links[3]['title']);
    $this->assertSession()->linkByHrefExists($form_links[3]['uri'], 0);
    $this->assertSession()->pageTextContains($form_links[3]['options']['message_to_display_with_privacy_notice']['value']);
    $this->assertSession()->fieldEnabled('I have read and understand');

    // Test empty form start button does not cause WSOD.
    $this->drupalGet($node[4]->toUrl()->toString());
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);
  }

  /**
   * Test the state form.
   */
  public function testStateForm() {

    $node = \Drupal::entityTypeManager()->getStorage('node')->load('node_url');

    // Create the Admin user.
    $this->adminUser = $this->drupalCreateUser([
      'administer nodes',
      'bypass node access',
    ]);

    // Check that the form start button is on.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/services/form-start');
    $message = 'Default message -' . $this->randomMachineName(32);
    $this->submitForm([
      'forms_status' => 1,
      'message_to_display_when_form_off[value]' => $message,
    ], 'op');
    $this->drupalLogout();
    $node_url = $node->toUrl()->toString();
    $this->drupalGet($node_url);
    $this->assertSession()->pageTextContains($message);

    // Check that the form start button is on.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/services/form-start');
    $message = 'Default message -' . $this->randomMachineName(32);
    $this->submitForm([
      'forms_status' => 0,
      'message_to_display_when_form_off[value]' => $message,
    ], 'op');
    $this->drupalLogout();
    $node_url = $node->toUrl()->toString();
    $this->drupalGet($node_url);
    $this->assertSession()->pageTextNotContains($message);

  }

}
