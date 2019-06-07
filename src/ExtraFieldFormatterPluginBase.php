<?php

namespace Drupal\efs;

use Drupal\Core\Entity\EntityDisplayBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\PluginSettingsBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\efs\Entity\ExtraFieldInterface;
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
   * The formatter settings.
   *
   * @var array
   */
  protected $settings;

  /**
   * {@inheritdoc}
   */
  public function view(array $build, EntityInterface $entity, EntityDisplayBase $display, string $view_mode, ExtraFieldInterface $extra_field) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(EntityDisplayFormBase $view_display, array $form, FormStateInterface $form_state, ExtraFieldInterface $extra_field, string $field) {
    $form = [];
    $form['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Weight'),
      '#default_value' => $this->getSetting('weight'),
    ];

    return $form;
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

}
