<?php

namespace Drupal\kontainer\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Defines Kontainer URL validation for CDN URLs.
 *
 * @Constraint(
 *   id = "KontainerLink",
 *   label = @Translation("The Link must be from Kontainer URL set in config.", context = "Validation"),
 * )
 */
class KontainerLinkConstraint extends Constraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public string $message = "The path '@uri' is invalid. The link must be from '@config_url'.";

}
