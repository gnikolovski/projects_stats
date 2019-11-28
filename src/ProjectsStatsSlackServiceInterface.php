<?php

namespace Drupal\projects_stats;

/**
 * Interface ProjectsStatsSlackServiceInterface.
 *
 * @package Drupal\projects_stats
 */
interface ProjectsStatsSlackServiceInterface {

  /**
   * Sends message.
   */
  public function sendMessage();

}
