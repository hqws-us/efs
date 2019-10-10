<?php

namespace Drupal\efs\Plugin\efs\Formatter;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityDisplayBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Token;
use Drupal\efs\Entity\ExtraFieldInterface;
use Drupal\efs\ExtraFieldFormatterPluginBase;
use Drupal\field_ui\Form\EntityDisplayFormBase;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin that renders the desired View display.
 *
 * @ExtraFieldFormatter(
 *   id = "view",
 *   label = @Translation("View"),
 *   description = @Translation("Render view."),
 *   supported_contexts = {
 *     "form",
 *     "display"
 *   }
 * )
 */
class View extends ExtraFieldFormatterPluginBase {

  /**
   * The Token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Token $token) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(string $entity_type_id, string $bundle) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultContextSettings(string $context) {
    $defaults = [
        'view' => '',
        'arguments' => [],
        'hide_empty' => FALSE,
        'check_access' => FALSE,
      ] + parent::defaultSettings();

    if ($context == 'form') {
      $defaults['required_fields'] = 1;
    }
    return $defaults;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(EntityDisplayFormBase $view_display, array $form, FormStateInterface $form_state, ExtraFieldInterface $extra_field, string $field) {
    $element = parent::settingsForm($view_display, $form, $form_state, $extra_field, $field);

    $options = [];
    foreach (Views::getEnabledViews() as $view) {
      foreach ($view->get('display') as $display) {
        $options[$view->get('label')]["{$view->get('id')}::{$display['id']}"] = new FormattableMarkup('@view_label - @view_display', [
          '@view_label' => $view->get('label'),
          '@view_display' => $display['display_title'],
        ]);
      }
    }

    if (!$options) {
      $element['help'] = ['#markup' => $this->t('No available Views were found.')];
      return $form;
    }

    $element['view'] = [
      '#title' => $this->t('View'),
      '#description' => $this->t('Select the view that will be displayed instead of the field value.'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('view'),
      '#options' => $options,
    ];

    $element['hide_empty'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide empty views'),
      '#description' => $this->t('Do not display the field if the view is empty.'),
      '#default_value' => $this->getSetting('hide_empty'),
    ];

    $element['check_access'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Check access to the View'),
      '#default_value' => $this->getSetting('check_access'),
    ];

    $items = $this->getSetting('arguments') ?? [];
    $element['arguments_wrapper'] = [
      '#tree' => FALSE,
      '#type' => 'fieldset',
      '#title' => $this->t('Arguments'),
      '#prefix' => '<div id="arguments-wrapper-fieldset">',
      '#suffix' => '</div>',
      '#description' => $this->t('Tokens can be used OR the field machine name which value you want to use as an argument. If you want to use a certain property of the field then use "::" after field name and write down the name of the component after it. Example: "field_revision_reference::target_revision_id".'),
    ];

    // Show the token help relevant to this pattern type.
    $element['arguments_wrapper']['token_help'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => [$extra_field->get('entity_type')],
    ];

    if (!$form_state->has([$field, 'arguments'])) {
      $form_state->set([
        $field,
        'arguments',
      ], count($this->getSetting('arguments')));
    }
    $arguments_field = $form_state->get([$field, 'arguments']);
    for ($i = 0; $i < $arguments_field; $i++) {
      $items = array_values($items);
      $element['arguments'][$i] = [
        '#group' => 'arguments_wrapper',
        '#type' => 'textfield',
        '#title' => $this->t('Argument #@arg', ['@arg' => $i]),
        '#default_value' => $items[$i],
        '#tree' => TRUE,
      ];
    }

    $element['actions_wrapper'] = [
      '#group' => 'arguments_wrapper',
      '#type' => 'container',
      'actions' => ['#type' => 'actions'],
    ];

    $element['actions_wrapper']['actions']['add_item'] = [
      '#type' => 'submit',
      '#value' => t('Add'),
      '#submit' => [[$this, 'addArgumentSubmit']],
      '#extra_field_name' => $field,
      '#ajax' => [
        'callback' => [$this, 'addArgumentAjaxSubmit'],
        'wrapper' => 'arguments-wrapper-fieldset',
      ],
      '#tree' => TRUE,
      '#parents' => [
        'fields',
        $field,
        'settings_edit_form',
        'actions',
        'add_item',
      ],
    ];

    if ($arguments_field) {
      $element['actions_wrapper']['actions']['remove_item'] = [
        '#type' => 'submit',
        '#value' => t('Remove'),
        '#submit' => [[$this, 'removeArgumentSubmit']],
        '#extra_field_name' => $field,
        '#ajax' => [
          'callback' => [$this, 'addArgumentAjaxSubmit'],
          'wrapper' => 'arguments-wrapper-fieldset',
        ],
        '#tree' => TRUE,
        '#parents' => [
          'fields',
          $field,
          'settings_edit_form',
          'actions',
          'remove_item',
        ],
      ];
    }

    return $element;
  }

  /**
   * Submit handler - add View argument.
   *
   * @param array $form
   *   The mode form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function addArgumentSubmit(array &$form, FormStateInterface $form_state) {
    $extra_field_name = $form_state->getTriggeringElement()['#extra_field_name'];

    $argument_field = $form_state->get([$extra_field_name, 'arguments']);
    $add_button = $argument_field + 1;
    $form_state->set([$extra_field_name, 'arguments'], $add_button);
    $form_state->setRebuild();
  }

  /**
   * Ajax submit handler.
   *
   * @param array $form
   *   The mode form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   Extra Field settings sub-form.
   */
  public function addArgumentAjaxSubmit(array &$form, FormStateInterface $form_state) {
    $extra_field_name = $form_state->getTriggeringElement()['#extra_field_name'];

    // The form passed here is the entire form, not the sub-form that is passed
    // to non-AJAX callback.
    return $form['fields'][$extra_field_name]['format']['format_settings']['settings']['arguments_wrapper'];
  }

  /**
   * Submit handler - remove View argument.
   *
   * @param array $form
   *   The mode form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function removeArgumentSubmit(array &$form, FormStateInterface $form_state) {
    $extra_field_name = $form_state->getTriggeringElement()['#extra_field_name'];

    $arguments_field = $form_state->get([$extra_field_name, 'arguments']);
    if ($arguments_field) {
      $remove_button = $arguments_field - 1;
      $form_state->set([$extra_field_name, 'arguments'], $remove_button);
    }
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(string $context) {
    $summary = parent::settingsSummary($context);

    $summary[] = $this->t('View: %components', ['%components' => $this->getSetting('view')]);

    if ($this->getSetting('arguments')) {
      $arguments_summary = [];
      foreach ($this->getSetting('arguments') as $arg_key => $arg_value) {
        $arguments_summary[] = $arg_value;
      }
      $summary[] = $this->t('Arguments: %components', ['%components' => implode(', ', $arguments_summary)]);
    }

    $summary[] = $this->t('Hide empty: %setting', ['%setting' => $this->getSetting('hide_empty') ? 'Yes' : 'No']);
    $summary[] = $this->t('Check access: %setting', ['%setting' => $this->getSetting('check_access') ? 'Yes' : 'No']);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, EntityInterface $entity, EntityDisplayBase $display, string $view_mode, ExtraFieldInterface $extra_field) {
    $element = parent::view($build, $entity, $display, $view_mode, $extra_field);
    $settings = $this->getSettings();

    if (isset($settings['view']) && !empty($settings['view']) && FALSE !== strpos($settings['view'], '::')) {
      list($view_id, $view_display) = explode('::', $settings['view'], 2);
    }
    else {
      return $element;
    }

    // Check access to the View.
    if (!empty($settings['check_access'])) {
      $view = Views::getView($view_id);
      if (!$view || !$view->access($view_display)) {
        return $element;
      }
    }

    $arguments = $this->getArguments($entity, $extra_field);

    // If empty views are hidden, execute view to count result.
    if (!empty($settings['hide_empty'])) {
      $view = $view ?? Views::getView($view_id);
      if (!$view || !$view->access($view_display)) {
        return $element;
      }

      $view->setArguments($arguments);
      $view->setDisplay($view_display);
      $view->preExecute();
      $view->execute();

      if (empty($view->result)) {
        return $element;
      }
    }

    $element = [
      '#attributes' => ['class' => [Html::cleanCssIdentifier($extra_field->get('field_name'))]],
      '#type' => 'view',
      '#name' => $view_id,
      '#display_id' => $view_display,
      '#arguments' => $arguments,
    ];
    return $element;
  }

  /**
   * Returns the arguments to send to the views.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Parent entity.
   * @param \Drupal\efs\Entity\ExtraFieldInterface $extra_field
   *   The extra field entity.
   *
   * @return array
   *   Views arguments array.
   */
  protected function getArguments(EntityInterface $entity, ExtraFieldInterface $extra_field) {
    $arguments = [];
    $args = $this->getSetting('arguments');
    if (!empty($args) && is_array($args)) {
      foreach ($args as $key => $argument) {
        $parts = explode('::', $argument);
        $field_name = array_shift($parts);
        $field_component = array_shift($parts);

        if (isset($entity->entityKeys[$key])) {
          $arguments[$key] = $entity->entityKeys[$key];
        }
        elseif ($entity->hasField($field_name)) {
          /** @var \Drupal\Core\Field\FieldItemListInterface $field_item */
          $field_item = $entity->{$field_name};
          $property_name = $field_component ?: $field_item->getItemDefinition()
            ->getMainPropertyName();
          $arguments[$key] = implode('+', (array) array_column($field_item->getValue(), $property_name));
        }
        else {
          // Probably, this argument is token.
          $arguments[$key] = $this->token->replace($argument, [$extra_field->get('entity_type') => $entity], ['clear' => TRUE]);
        }
      }
    }
    return array_values($arguments);
  }

}
