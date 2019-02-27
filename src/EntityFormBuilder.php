<?php

namespace Drupal\efs;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Builds entity forms.
 */
class EntityFormBuilder implements EntityFormBuilderInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity form builder service.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $entityFormBuilder;

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $stringTranslation;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new EntityFormBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   *   The entity form builder.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FormBuilderInterface $form_builder, ClassResolverInterface $class_resolver, EntityFormBuilderInterface $entity_form_builder, TranslationInterface $string_translation, ModuleHandlerInterface $module_handler) {

    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
    $this->classResolver = $class_resolver;
    $this->formBuilder = $form_builder;
    $this->stringTranslation = $string_translation;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(EntityInterface $entity, $operation = 'default', array $form_state_additions = [], string $form_display_class = '\Drupal\Core\Entity\ContentEntityForm') {
    $form_object = $this->getFormObject($entity, $operation, $form_state_additions, $form_display_class);

    $form_object->setEntity($entity);
    $form_state = (new FormState())->setFormState($form_state_additions);
    $form = $this->formBuilder->buildForm($form_object, $form_state);

    return $form;
  }

  public function getFormObject($operation = 'default', string $form_display_class = '\Drupal\Core\Entity\ContentEntityForm') {
    $form_object_instance = $this->classResolver->getInstanceFromDefinition($form_display_class);

    $form_object = $form_object_instance
      ->setStringTranslation($this->stringTranslation)
      ->setModuleHandler($this->moduleHandler)
      ->setEntityTypeManager($this->entityTypeManager)
      ->setOperation($operation)
      // The entity manager cannot be injected due to a circular dependency.
      // @todo Remove this set call in https://www.drupal.org/node/2603542.
      ->setEntityManager(\Drupal::entityManager());

    return $form_object;
  }

}
