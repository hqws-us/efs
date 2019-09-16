<?php

namespace Drupal\efs\Plugin\efs\Formatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityDisplayBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\efs\Entity\ExtraFieldInterface;
use Drupal\efs\ExtraFieldFormatterPluginBase;
use Drupal\field_ui\Form\EntityDisplayFormBase;

/**
 * Plugin that renders the title of a block.
 *
 * @ExtraFieldFormatter(
 *   id = "entity_label",
 *   label = @Translation("Entity label"),
 *   description = @Translation("Current entity label"),
 *   supported_contexts = {
 *     "display"
 *   }
 * )
 */
class EntityLabel extends ExtraFieldFormatterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, EntityInterface $entity, EntityDisplayBase $display, string $view_mode, ExtraFieldInterface $extra_field) {
    $config = $this->getSettings();

    $output = $entity->label();

    if (empty($output)) {
      return [];
    }

    $output = Html::escape($output);

    // Wrapper and class.
    if (!empty($config['wrapper'])) {
      $wrapper = Html::escape($config['wrapper']);
      $class = (!empty($config['class'])) ? ' class="' . Html::escape($config['class']) . '"' : '';
      $output = '<' . $wrapper . $class . '>' . $output . '</' . $wrapper . '>';
    }

    return [
      '#markup' => $output,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(EntityDisplayFormBase $view_display, array $form, FormStateInterface $form_state, ExtraFieldInterface $extra_field, string $field) {
    $config = $this->getSettings();

    $settings['wrapper'] = [
      '#type' => 'textfield',
      '#title' => 'Wrapper',
      '#default_value' => $config['wrapper'],
      '#description' => $this->t('Eg: h1, h2, p'),
    ];
    $settings['class'] = [
      '#type' => 'textfield',
      '#title' => 'Class',
      '#default_value' => $config['class'],
      '#description' => $this->t('Put a class on the wrapper. Eg: block-title'),
    ];

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(string $context) {
    $config = $this->getSettings();

    $summary = parent::settingsSummary($context);
    $summary[] = 'Wrapper: ' . $config['wrapper'];

    if (!empty($config['class'])) {
      $summary[] = 'Class: ' . $config['class'];
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultContextSettings(string $context) {
    $configuration = [
        'wrapper' => 'h2',
        'class' => '',
      ] + parent::defaultSettings();

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(string $entity_type_id, string $bundle) {
    return TRUE;
  }

}
