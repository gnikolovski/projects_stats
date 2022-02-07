<?php

namespace Drupal\projects_stats;

/**
 * Defines an interface for Projects Stats build service.
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
