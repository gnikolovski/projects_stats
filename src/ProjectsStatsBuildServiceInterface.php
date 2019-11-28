<?php

namespace Drupal\projects_stats;

/**
 * Interface ProjectsStatsBuildServiceInterface.
 *
 * @package Drupal\projects_stats
 */
interface ProjectsStatsBuildServiceInterface {

  /**
   * Generates content.
   *
   * @param string $configuration
   *   The block configuration.
   *
   * @return array
   *   The generated content.
   */
  public function generate($configuration);

}
