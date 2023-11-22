<?php

namespace Drupal\kontainer\Plugin\EntityUsage\Track;

use Drupal\entity_usage\Plugin\EntityUsage\Track\EntityReference;

/**
 * Tracks usage of entities related in kontainer_entity_reference fields.
 *
 * @EntityUsageTrack(
 *   id = "kontainer_entity_reference",
 *   label = @Translation("Kontainer Entity Reference"),
 *   description = @Translation("Tracks relationships created with 'Kontainer Entity Reference' fields."),
 *   field_types = {"kontainer_media_reference"},
 * )
 */
class KontainerEntityReference extends EntityReference {}
