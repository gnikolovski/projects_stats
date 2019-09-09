<?php

namespace Drupal\projects_stats;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Class ProjectsStatsSlackService.
 *
 * @package Drupal\projects_stats
 */
class ProjectsStatsSlackService implements ProjectsStatsSlackServiceInterface {

  /**
   * The Immutable config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * ProjectsStatsSlackService constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The Immutable config.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(ConfigFactoryInterface $config_factory, MessengerInterface $messenger) {
    $this->config = $config_factory->get('projects_stats.settings');
    $this->messenger = $messenger;
  }

  /**
   * Send message to Slack.
   */
  public function sendMessage() {
    $webhook_url = $this->config->get('webhook_url');
    $client = new Client();
    $client->post($webhook_url, [
      'body' => json_encode($this->createMessage()),
    ]);
  }

  /**
   * Compose Slack message.
   */
  protected function createMessage() {
    $machine_names = $this->config->get('machine_names');
    $machine_names = array_map('trim', explode(',', $machine_names));

    foreach ($machine_names as $key => $machine_name) {
      if (is_numeric($machine_name)) {
        unset($machine_names[$key]);
        foreach (['project_distribution', 'project_module', 'project_theme'] as $project_type) {
          $machine_names_by_author = $this->getProjectsByAuthor($project_type, $machine_name);
          $machine_names = array_merge($machine_names, $machine_names_by_author);
        }
      }
    }
    $machine_names = array_unique($machine_names);

    $message = t('Downloads') . ':' . PHP_EOL;
    foreach ($machine_names as $machine_name) {
      $downloads_count = $this->getDownloadsCount($machine_name);
      if ($downloads_count == 'n/a') {
        continue;
      }
      $message .= $downloads_count . PHP_EOL;
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
      $res = $client->get($base_url . $machine_name, [
        'http_errors' => FALSE,
      ]);
      $body = $res->getBody();
      $decoded_body = json_decode($body, TRUE);
      if (!isset($decoded_body['list'][0]) || empty($decoded_body['list'][0]['field_download_count'])) {
        return 'n/a';
      }
      $downloads_count = '_' . $decoded_body['list'][0]['title'] . ': ' .
        $decoded_body['list'][0]['field_download_count'] . '_';
      return $downloads_count;
    }
    catch (RequestException $e) {
      $this->messenger->addError($e->getMessage());
      return 'n/a';
    }
  }

  /**
   * Get projects filtered by user ID.
   */
  public function getProjectsByAuthor($project_type, $author_uid) {
    $base_url = 'https://www.drupal.org/api-d7/node.json';
    $client = new Client();
    try {
      $res = $client->get($base_url . '?type=' . $project_type . '&author=' . $author_uid, [
        'http_errors' => FALSE,
      ]);
      $body = $res->getBody();
      $decoded_body = json_decode($body, TRUE);
      $projects = [];
      if (!isset($decoded_body['list'])) {
        return [];
      }
      foreach ($decoded_body['list'] as $item) {
        $projects[] = $item['field_project_machine_name'];
      }
      return $projects;
    }
    catch (RequestException $e) {
      return [];
    }
  }

}
