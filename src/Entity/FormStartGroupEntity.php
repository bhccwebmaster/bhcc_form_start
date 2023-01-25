<?php

namespace Drupal\bhcc_form_start\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the mta-sts.txt record entity.
 *
 * @ConfigEntityType(
 *   id = "bhcc_form_start_group",
 *   label = @Translation("Form start group"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\bhcc_form_start\FormStartGroupEntityListBuilder",
 *     "form" = {
 *       "add" = "Drupal\bhcc_form_start\Form\FormStartGroupEntityForm",
 *       "edit" = "Drupal\bhcc_form_start\Form\FormStartGroupEntityForm",
 *       "delete" = "Drupal\bhcc_form_start\Form\FormStartGroupEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\bhcc_form_start\FormStartGroupEntityHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "bbcc_form_start_group",
 *   admin_permission = "manage form start groups",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "name",
 *     "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/services/form-start/groups/group/{bhcc_form_start_group}",
 *     "add-form" = "/admin/config/services/form-start/groups/group/add",
 *     "edit-form" = "/admin/config/services/form-start/groups/group/{bhcc_form_start_group}/edit",
 *     "delete-form" = "/admin/config/services/form-start/groups/group/{bhcc_form_start_group}/delete",
 *     "collection" = "/admin/config/services/form-start/groups"
 *   }
 * )
 */
class FormStartGroupEntity extends ConfigEntityBase implements FormStartGroupEntityInterface {

  /**
   * Form start group ID.
   *
   * @var string
   */
  protected $id;

  /**
   * Form start group name.
   *
   * @var string
   */
  protected $name;

  /**
   * {@inheritdoc}
   */
  public function setId($id) {
    $this->id = $id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function setLabel(String $name) {
    $this->name = $label;
    return $this;
  }

}
