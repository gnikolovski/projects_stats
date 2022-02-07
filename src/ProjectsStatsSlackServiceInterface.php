<?php

namespace Drupal\projects_stats;

/**
 * Defines an interface for Projects Stats Slack service.
 *
 * @package Drupal\projects_stats
 */
interface ProjectsStatsSlackServiceInterface {

  /**
   * Sends message.
   */
  public function sendMessage();

}
