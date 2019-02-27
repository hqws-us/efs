<?php

namespace Drupal\efs\Routing;

use Drupal\Core\ParamConverter\ParamConverterInterface;

/**
 * Parameter converter for upcasting fieldgroup config ids to fieldgroup object.
 */
class ExtraFieldConverter implements ParamConverterInterface {

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, \Symfony\Component\Routing\Route $route) {
    return isset($definition['type']) && $definition['type'] == 'efs';
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $identifiers = explode('.', $value);
    if (count($identifiers) != 5) {
      return;
    }

    return [];//efs_load_efs($identifiers[4], $identifiers[0], $identifiers[1], $identifiers[2], $identifiers[3]);
  }


}
