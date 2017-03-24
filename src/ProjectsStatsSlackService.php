<?php

namespace Drupal\projects_stats;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Class ProjectsStatsSlackService.
 *
 * @package Drupal\projects_stats
 */
class ProjectsStatsSlackService implements ProjectsStatsServiceInterface {

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('projects_stats.settings');
  }

  /**
   * Send message to Slack.
   */
  public function sendMessage() {
    $webhook_url = $this->config->get('webhook_url');
    $client = new Client();
    $response = $client->post($webhook_url, [
      'body' => json_encode($this->createMessage()),
    ]);
  }

  /**
   * Compose Slack message.
   */
  protected function createMessage() {
    $machine_names = $this->config->get('machine_names');
    $machine_names = explode(',', $machine_names);

    $message =  t('Downloads') . ':' . PHP_EOL;
    foreach ($machine_names as $machine_name) {
      $message .= $this->getDownloadsCount(trim($machine_name)) . PHP_EOL;
    }

    return [
      'text' => $message,
    ];
  }

  /**
   * Get data from drupal.org API endpoint.
   */
  private function getDownloadsCount($machine_name) {
    $base_url = 'https://www.drupal.org/api-d7/node.json?field_project_machine_name=';
    $client = new Client();
    try {
      $res = $client->get($base_url . $machine_name, ['http_errors' => FALSE]);
      $body = $res->getBody();
      $decoded_body = json_decode($body, TRUE);
      if (!isset($decoded_body['list'][0])) {
        return $this->t('n/a');
      }
      $downloads_count = '_' . $decoded_body['list'][0]['title'] . ': ' . $decoded_body['list'][0]['field_download_count'] . '_';
      return $downloads_count;
    }
    catch (RequestException $e) {
      drupal_set_message($e->getMessage());
      return $this->t('n/a');
    }
  }
}
