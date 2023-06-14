<?php

namespace Drupal\bhcc_form_start\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class Form start group entity add / edit form.
 */
class FormStartGroupEntityForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form_start_group = $this->entity;

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Group name'),
      '#default_value' => $form_start_group->label(),
      '#description' => $this->t('A unique name for this Form start group.'),
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $form_start_group->id(),
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#machine_name' => [
        'exists' => [
          'Drupal\bhcc_form_start\Entity\FormStartGroupEntity',
          'load',
        ],
        'source' => ['name'],
      ],
      '#description' => $this->t('A unique machine-readable name for this Form start group. It must only contain lowercase letters, numbers, and underscores.'),
      '#disabled' => !$form_start_group->isNew(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $form_start_group = $this->entity;

    // Set the ID if this is a new entity.
    if ($form_start_group->isNew()) {
      $form_start_group->setId($form_start_group->id());
    }

    $status = $form_start_group->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created form start group %label.', [
          '%label' => $form_start_group->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved form start group %label.', [
          '%label' => $form_start_group->label(),
        ]));
    }
    $form_state->setRedirectUrl($form_start_group->toUrl('collection'));
  }

}
