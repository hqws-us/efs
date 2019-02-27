<?php

namespace Drupal\efs\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a ExtraFieldDisplay item annotation object.
 *
 * @see \Drupal\efs\ExtraFieldFormatterPluginManager
 *
 * @Annotation
 */
class ExtraFieldFormatter extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A short description of the formatter type.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $description;

  /**
   * An array of contexts the formatter supports (form / view).
   *
   * @var array
   */
  public $supported_contexts = array();

}
