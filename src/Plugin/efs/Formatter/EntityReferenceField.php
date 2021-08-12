<?php

namespace Drupal\efs\Plugin\efs\Formatter;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityDisplayBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterInterface;
use Drupal\Core\Field\FormatterPluginManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\efs\Entity\ExtraFieldInterface;
use Drupal\efs\ExtraFieldFormatterPluginBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field_ui\Form\EntityDisplayFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Details element.
 *
 * @ExtraFieldFormatter(
 *   id = "entityreference_field",
 *   label = @Translation("Entity reference field"),
 *   description = @Translation("Entity reference field"),
 *   supported_contexts = {
 *     "display"
 *   }
 * )
 */
class EntityReferenceField extends ExtraFieldFormatterPluginBase {

  /**
   * The formatter plugin manager.
   *
   * @var \Drupal\Core\Field\FormatterPluginManager
   */
  protected $formatter;

  /**
   * The language interface.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The selected referenced entity field.
   *
   * @var FieldDefinitionInterface
   */
  protected $fieldDefinition;

  /**
   * The module handler service.
   *
   * @var ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormatterPluginManager $formatter, LanguageManagerInterface $language_manager, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, EntityFieldManagerInterface $entity_field_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->formatter = $formatter;
    $this->languageManager = $language_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.field.formatter'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultContextSettings(string $context) {
    $defaults = [
        'field' => NULL,
        'referenced_entity_field' => NULL,
        'formatter' => NULL,
        'formatter_settings' => [
          'label' => 'above',
          'settings' => [],
          'third_party_settings' => [],
        ],
      ] + parent::defaultSettings();

    if ($context == 'form') {
      $defaults['required_fields'] = 1;
    }

    return $defaults;

  }

  /**
   * Get the possible formatters of field.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   The formatters of field.
   */
  public static function getFieldFormatters(array $form, FormStateInterface $form_state) {
    $field = $form_state->getValue('field_mirror_name');
    $select = $form['fields'][$field]['format']['format_settings']['settings']['formatter'];
    return $select;
  }

  /**
   * Get the possible formatters of field.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   The formatters of field.
   */
  public static function getEntityFields(array $form, FormStateInterface $form_state) {
    $field = $form_state->getValue('field_mirror_name');
    $select = $form['fields'][$field]['format']['format_settings']['settings']['referenced_entity_field'];
    return $select;
  }

  /**
   * Get the field formatter settings.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   The formatter of field settings.
   */
  public static function getFieldFormatterSettings(array $form, FormStateInterface $form_state) {
    $field = $form_state->getValue('field_mirror_name');
    $select = $form['fields'][$field]['format']['format_settings']['settings']['formatter_settings'];
    return $select;
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, EntityInterface $entity, EntityDisplayBase $display, string $view_mode, ExtraFieldInterface $extra_field) {
    $settings = $this->getSettings();
    $field_name = $settings['field'];
    $ref = $settings['referenced_entity_field'];
    $formatter = $settings['formatter'];
    $formatter_settings = $settings['formatter_settings'];

    $field_definitions = $display->get('fieldDefinitions');
    $field = $field_definitions[$field_name];
    $fd = $this->getFieldDefinition($field, $ref);
    if ($fd === NULL) {
      return [];
    }
    $items = $entity->get($settings['field']);
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    $elements = [];
    foreach ($items as $item) {
      if (!$item->entity->hasField($ref) || $item->entity->get($ref)
          ->isEmpty()) {
        continue;
      }

      //$ref_items = $items->entity->get($ref);
      $view_display = $this->getViewDisplay($item->entity->bundle(), $item->entity->getEntityTypeId(), $ref, $formatter, $formatter_settings);
      $elements[] = $view_display->build($item->entity);
    }

    return $elements;
  }

  public function getViewDisplay($target_bundle, $target_type, $field_name, $formatter, $formatter_settings) {
    $x= '';
    $display = EntityViewDisplay::create([
      'targetEntityType' => $target_type,
      'bundle' => $target_bundle,
      'status' => TRUE,
    ]);
    $display->setComponent($field_name, [
      'type' => $formatter,
      'settings' => !empty($formatter_settings['settings']) ? $formatter_settings['settings'] : [],
      'third_party_settings' => !empty($formatter_settings['third_party_settings']) ? $formatter_settings['third_party_settings'] : [],
      'label' => $formatter_settings['label'],
    ]);
    return $display;
  }


  public function getReferencedEntityFields(string $field_name, array $fields) {
    /** @var FieldDefinitionInterface $field */
    $field = $fields[$field_name];
    $handler_settings = $field->getSetting('handler_settings');
    $storage_definition = $field->getFieldStorageDefinition();
    $target_type = $storage_definition->getSetting('target_type');
    if (empty($handler_settings['target_bundles'])) {
      $bundles = \Drupal::service('entity_type.bundle.info')
        ->getBundleInfo($target_type);
      $target_bundles = array_keys($bundles);
    } else {
      $target_bundles = $handler_settings['target_bundles'];
    }
    $result = [];
    foreach ($target_bundles as $bundle) {
      $definitions = $this->entityFieldManager->getFieldDefinitions($target_type, $bundle);
      foreach ($definitions as $key => $f) {
        if ($f instanceof BaseFieldDefinition) {
          $result[$key] = $f->getLabel();
        }
        else {
          $result[$key] = $f->label();
        }
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(EntityDisplayFormBase $view_display, array $form, FormStateInterface $form_state, ExtraFieldInterface $extra_field, string $field) {
    $form = parent::settingsForm($view_display, $form, $form_state, $extra_field, $field);

    /** @var \Drupal\Core\Entity\EntityDisplayBase $display */
    $display = $view_display->getEntity();
    $fields = $display->get('fieldDefinitions');
    $fields_options = $this->getFieldOptions($fields);
    $settings = $this->getSettings();
    $form_state->setValue('field_mirror_name', $field);

    $form['field'] = [
      '#title' => $this->t('Field'),
      '#type' => 'select',
      '#options' => $fields_options,
      '#default_value' => !empty($settings['field']) ? $settings['field'] : NULL,
      '#ajax' => [
        'callback' => [get_class($this), 'getEntityFields'],
        'wrapper' => 'field_erf_referenced_entity_field',
      ],
      '#empty_value' => '',
      '#empty_option' => $this->t('Select one field'),
    ];

    $values = $form_state->getValues();
    $field_name = !empty($values['fields'][$field]['settings_edit_form']['settings']['field']) ? $values['fields'][$field]['settings_edit_form']['settings']['field'] : $settings['field'];
    $referenced_entity_fields = !empty($field_name) ? $this->getReferencedEntityFields($field_name, $fields) : [];
    $form['referenced_entity_field'] = [
      '#title' => $this->t('Referenced entity Field'),
      '#type' => 'select',
      '#options' => $referenced_entity_fields,
      '#default_value' => !empty($settings['referenced_entity_field']) ? $settings['referenced_entity_field'] : NULL,
      '#prefix' => '<div id="field_erf_referenced_entity_field">',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => [get_class($this), 'getFieldFormatters'],
        'wrapper' => 'field_mirror_field_formatters',
      ],
      '#states' => [
        'visible' => [
          'select[name="fields[' . $field . '][settings_edit_form][settings][field]"]' => ['!value' => ''],
        ],
      ],
      '#empty_value' => '',
      '#empty_option' => $this->t('Select one field'),
    ];


    $ref = !empty($values['fields'][$field]['settings_edit_form']['settings']['referenced_entity_field']) ? $values['fields'][$field]['settings_edit_form']['settings']['referenced_entity_field'] : $settings['referenced_entity_field'];
    if (!empty($ref)) {
      $this->setFieldDefinition($fields[$field_name], $ref);
      $formatters = $this->getFieldSelectedFormatters($ref);
    }
    else {
      $formatters = [];
    }
    $form['formatter'] = [
      '#type' => 'select',
      '#title' => $this->t('Formatter'),
      '#options' => $formatters,
      '#default_value' => !empty($settings['formatter']) ? $settings['formatter'] : NULL,
      '#prefix' => '<div id="field_mirror_field_formatters">',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => [get_class($this), 'getFieldFormatterSettings'],
        'wrapper' => 'field_mirror_field_formatter_settings',
      ],
      '#states' => [
        'visible' => [
          'select[name="fields[' . $field . '][settings_edit_form][settings][referenced_entity_field]"]' => ['!value' => ''],
        ],
      ],
      '#empty_value' => '',
      '#empty_option' => $this->t('Select one formatter'),
    ];

    $formatter = !empty($values['fields'][$field]['settings_edit_form']['settings']['formatter']) ? $values['fields'][$field]['settings_edit_form']['settings']['formatter'] : $settings['formatter'];
    $formatter_settings = !empty($formatter) ? $this->getFieldSelectedFormatterSettings($formatter, $settings['formatter_settings'], $form, $form_state) : [];
    $form['formatter_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Settings'),
      '#tree' => TRUE,
      '#open' => TRUE,
      'label' => [
        '#type' => 'select',
        '#title' => $this->t('Label'),
        '#options' => [
          'above' => $this->t('Above'),
          'inline' => $this->t('Inline'),
          'hidden' => $this->t('Hidden'),
          'visually_hidden' => $this->t('Visually hidden'),
        ],
        '#default_value' => !empty($settings['formatter_settings']['label']) ? $settings['formatter_settings']['label'] : NULL,
      ],
      'settings' => $formatter_settings['settings'],
      'third_party_settings' => $formatter_settings['third_party_settings'],
      '#prefix' => '<div id="field_mirror_field_formatter_settings">',
      '#suffix' => '</div>',
      '#states' => [
        'visible' => [
          'select[name="fields[' . $field . '][settings_edit_form][settings][formatter]"]' => ['!value' => ''],
        ],
      ],
    ];

    return $form;
  }

  public function setFieldDefinition(FieldDefinitionInterface $field, string $referenced_entity_field) {
    $handler_settings = $field->getSetting('handler_settings');
    $storage_definition = $field->getFieldStorageDefinition();
    $target_type = $storage_definition->getSetting('target_type');
    if (empty($handler_settings['target_bundles'])) {
      $bundles = \Drupal::service('entity_type.bundle.info')
        ->getBundleInfo($target_type);
      $target_bundles = array_keys($bundles);
    } else {
      $target_bundles = $handler_settings['target_bundles'];
    }
    foreach ($target_bundles as $bundle) {
      $definitions = $this->entityFieldManager->getFieldDefinitions($target_type, $bundle);
      if (!empty($definitions[$referenced_entity_field])) {
        $this->fieldDefinition = $definitions[$referenced_entity_field];
        return $this->fieldDefinition;
      }
    }
    return NULL;
  }

  public function getFieldDefinition(FieldDefinitionInterface $field = NULL, string $referenced_entity_field = NULL) {
    if ($field !== NULL && $referenced_entity_field !== NULL) {
      return $this->setFieldDefinition($field, $referenced_entity_field);
    }
    return $this->fieldDefinition;
  }

  /**
   * Get the field options.
   *
   * @param array $fields
   *   The array of fields.
   *
   * @return array
   *   The select options.
   */
  protected function getFieldOptions(array $fields) {
    $options = [];
    foreach ($fields as $field_mame => $field) {
      if ($field instanceof FieldDefinitionInterface && ($field->getType() === 'entity_reference' || $field->getType() === 'dynamic_entity_reference')) {
        $options[$field_mame] = $field->getLabel();
      }
    }
    return $options;
  }

  /**
   * Get the field selected formatter options.
   *
   * @param string $field_name
   *   The field name.
   * @param array $fields
   *   The list of fields.
   *
   * @return array
   *   The selected formatter options.
   */
  protected function getFieldSelectedFormatters(string $field_name) {
    $field_definition = $this->getFieldDefinition();
    $options = [];
    $field_type = $field_definition->getType();
    $definitions = $this->formatter->getDefinitions();
    foreach ($definitions as $id => $def) {
      if (in_array($field_type, $def['field_types'])) {
        $options[$id] = $def['label'];
      }
    }
    return $options;
  }

  /**
   * Get the settings of selected formatter.
   *
   * @param string $plugin_id
   *   The plugin id.
   * @param array $settings
   *   The settings of field.
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   The settings of selected formatter.
   */
  protected function getFieldSelectedFormatterSettings(string $plugin_id, array $settings, array $form, FormStateInterface $form_state) {
    $field = $this->getFieldDefinition();
    if ($field === NULL) {
      return [];
    }
    $configuration = [
      'field_definition' => $field,
      'third_party_settings' => !empty($settings['third_party_settings']) ? $settings['third_party_settings'] : [],
      'settings' => !empty($settings['settings']) ? $settings['settings'] : [],
      'label' => $settings['label'],
      'view_mode' => '_custom',
    ];
    /** @var \Drupal\Core\Field\FormatterInterface $plugin */
    $plugin = $this->formatter->createInstance($plugin_id, $configuration);
    $settings = $plugin->settingsForm($form, $form_state);
    $third_party_settings = $this->thirdPartySettingsForm($plugin, $field, $form, $form_state);
    return [
      'settings' => $settings,
      'third_party_settings' => $third_party_settings,
    ];
  }

  protected function thirdPartySettingsForm(FormatterInterface $plugin, FieldDefinitionInterface $field_definition, array $form, FormStateInterface $form_state) {
    $settings_form = [];
    // Invoke hook_field_formatter_third_party_settings_form(), keying resulting
    // subforms by module name.
    foreach ($this->moduleHandler->getImplementations('field_formatter_third_party_settings_form') as $module) {
      $settings_form[$module] = $this->moduleHandler->invoke($module, 'field_formatter_third_party_settings_form', [
        $plugin,
        $field_definition,
        '_custom',
        $form,
        $form_state,
      ]);
    }
    return $settings_form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(string $context) {
    $summary = parent::settingsSummary($context);
    if ($this->getSetting('field')) {
      $field = $this->getSetting('field');
      $ref = $this->getSetting('referenced_entity_field');
      $formatter = $this->getSetting('formatter');
      $summary[] = $this->t('Field: %components', ['%components' => $field]);
      $summary[] = $this->t('Referenced entity field: %components', ['%components' => $ref]);
      $summary[] = $this->t('Field formatter: %components', ['%components' => $formatter]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(string $entity_type_id, string $bundle) {
    return TRUE;
  }

}
