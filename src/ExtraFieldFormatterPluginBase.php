<?php

namespace Drupal\efs;

use Drupal\Core\Entity\EntityDisplayBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\PluginSettingsBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\efs\Entity\ExtraFieldInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field_ui\Form\EntityDisplayFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for 'Extra field formatter' plugin implementations.
 *
 * @todo Provide a settings validation API to support layout-builder.
 *
 * @ingroup efs
 */
abstract class ExtraFieldFormatterPluginBase extends PluginSettingsBase implements ExtraFieldFormatterPluginInterface {

  /**
   * The formatter settings.
   *
   * @var array
   */
  protected $settings;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, EntityInterface $entity, EntityDisplayBase $display, string $view_mode, ExtraFieldInterface $extra_field) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(EntityDisplayFormBase $view_display, array $form, FormStateInterface $form_state, ExtraFieldInterface $extra_field, string $field) {
    $element['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Weight'),
      '#default_value' => $extra_field->get('weight'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return ['weight' => 0];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(string $context) {
    $summary = [];
    $definition = $this->getPluginDefinition();
    $summary[] = $this->t('Plugin type: %plugin', ['%plugin' => $definition['label']]);
    $summary[] = $this->t('Weight: %weight', ['%weight' => $this->getSetting('weight')]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultContextSettings(string $context) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(string $entity_type_id, string $bundle) {
    return TRUE;
  }

  /**
   * Get the view_display field definitions as options for show in a select.
   *
   * @param \Drupal\Core\Entity\EntityDisplayBase $display
   *   The display entity object.
   * @param string $type
   *   The field type filter.
   *
   * @return array
   *   The select options.
   */
  protected function getFieldDefinitionsAsOptions(EntityDisplayBase $display, $type = NULL) {
    $fields = $display->getEntity()->get('fieldDefinitions');
    $options = [];
    foreach ($fields as $field) {
      if ($field instanceof FieldConfig) {
        if (($type !== NULL && $field->getType() === $type) || $type === NULL) {
          $options[$field->get('field_name')] = $field->label();
        }
      }
    }
    return $options;
  }

}
