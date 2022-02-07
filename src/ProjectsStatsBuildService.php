<?php

namespace Drupal\projects_stats;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use SimpleXMLElement;
use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Class ProjectsStatsBuildService.
 *
 * @package Drupal\projects_stats
 */
class ProjectsStatsBuildService implements ProjectsStatsBuildServiceInterface {

  use StringTranslationTrait;

  /**
   * The block configuration array.
   *
   * @var array
   */
  protected $configuration;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The Slack service.
   *
   * @var \Drupal\projects_stats\ProjectsStatsSlackServiceInterface
   */
  protected $slackService;

  /**
   * The cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * ProjectsStatsBuildService constructor.
   *
   * @param \Drupal\projects_stats\ProjectsStatsSlackServiceInterface $slack_service
   *   The Slack service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache.
   */
  public function __construct(ProjectsStatsSlackServiceInterface $slack_service, MessengerInterface $messenger, CacheBackendInterface $cache) {
    $this->slackService = $slack_service;
    $this->messenger = $messenger;
    $this->cache = $cache;
  }

  /**
   * {@inheritdoc}
   */
  public function generate($configuration) {
    $this->configuration = json_decode($configuration, TRUE);

    $machine_names = $this->configuration['machine_names'];
    $machine_names = array_map('trim', explode(',', $machine_names));

    return $this->doGenerate($this->configuration['display_type'], $machine_names);
  }

  /**
   * Generates table or list.
   *
   * @param string $type
   *   The type of result to generate (table or list).
   * @param array $machine_names
   *   The list of machine names.
   *
   * @return array
   *   The table of projects.
   */
  protected function doGenerate($type, array $machine_names) {
    $cid = 'projects_stats.' . $type;
    $cache = $this->cache->get($cid);

    if ($cache) {
      return $cache->data;
    }
    else {
      $method_name = 'generate' . ucfirst($type);
      $data = $this->{$method_name}($machine_names);

      if ($this->configuration['cache_age']) {
        $expire = time() + $this->configuration['cache_age'];
        $this->cache->set($cid, $data, $expire, ['projects_stats:config_form']);
      }

      return $data;
    }
  }

  /**
   * Generates table.
   *
   * @param array $machine_names
   *   The list of machine names.
   *
   * @return array
   *   The table of projects.
   */
  protected function generateTable(array $machine_names) {
    $description = $this->configuration['description'];
    $additional_columns = $this->configuration['additional_columns'];
    $sort_by = $this->configuration['sort_by'];
    $classes = $this->configuration['classes'];
    $target = $this->configuration['target'];

    $table_head = [$this->t('Title'), $this->t('Total usage count')];
    foreach ($additional_columns as $key => $value) {
      if ($value) {
        $key = str_replace('_', ' ', $key);
        $key = ucfirst($key);
        $key = $this->t('@key', ['@key' => $key]);
        $table_head[] = $key;
      }
    }

    foreach ($machine_names as $key => $machine_name) {
      if (is_numeric($machine_name)) {
        unset($machine_names[$key]);
        foreach (['project_distribution', 'project_module', 'project_theme'] as $project_type) {
          $machine_names_by_author = $this->slackService->getProjectsByAuthor($project_type, $machine_name);
          $machine_names = array_merge($machine_names, $machine_names_by_author);
        }
      }
    }
    $machine_names = array_unique($machine_names);

    $table_body = [];
    foreach ($machine_names as $machine_name) {
      $stats = $this->getStats(trim($machine_name));
      if (empty($stats['project_type']) || empty($stats['name'])) {
        continue;
      }

      $table_body_row = [
        'title' => $stats['name'],
        'url' => $stats['url'],
        'total_usage' => number_format($stats['total_usage'], 0, '.', ','),
        'total_usage_raw' => $stats['total_usage'],
      ];

      foreach ($additional_columns as $key => $value) {
        if ($value && isset($stats[$key])) {
          $table_body_row[$key] = $this->flattenValue($stats[$key]);
        }
      }

      $table_body[] = $table_body_row;
    }

    if ($sort_by != 'no') {
      usort($table_body, [$this, 'sortModulesList']);
    }

    return [
      '#theme' => 'projects_stats_table',
      '#classes' => ltrim($classes . ' block-projects-stats'),
      '#description' => $description,
      '#table_head' => $table_head,
      '#table_body' => $table_body,
      '#target' => $target == TRUE ? '_blank' : '_self',
    ];
  }

  /**
   * Generates list.
   *
   * @param array $machine_names
   *   The list of machine names.
   *
   * @return array
   *   The list of projects.
   */
  protected function generateList(array $machine_names) {
    $show_total_usage = $this->configuration['show_total_usage'];
    $description = $this->configuration['description'];
    $classes = $this->configuration['classes'];
    $target = $this->configuration['target'];

    foreach ($machine_names as $key => $machine_name) {
      if (is_numeric($machine_name)) {
        unset($machine_names[$key]);
        foreach (['project_distribution', 'project_module', 'project_theme'] as $project_type) {
          $machine_names_by_author = $this->slackService->getProjectsByAuthor($project_type, $machine_name);
          $machine_names = array_merge($machine_names, $machine_names_by_author);
        }
      }
    }
    $machine_names = array_unique($machine_names);

    $all_projects = [];
    foreach ($machine_names as $machine_name) {
      $stats = $this->getStats(trim($machine_name));
      if (empty($stats['project_type']) || empty($stats['name'])) {
        continue;
      }
      $project_type = str_replace('project_', '', $stats['project_type']) . 's';
      $all_projects[ucfirst($project_type)][] = $stats;
    }

    return [
      '#theme' => 'projects_stats_list',
      '#classes' => ltrim($classes . ' block-projects-stats'),
      '#description' => $description,
      '#all_projects' => $all_projects,
      '#show_total_usage' => $show_total_usage,
      '#target' => $target == TRUE ? '_blank' : '_self',
      '#attached' => [
        'library' => [
          'projects_stats/projects_stats',
        ],
        'drupalSettings' => [
          'collapsibleList' => $this->configuration['collapsible_list'],
        ],
      ],
    ];
  }

  /**
   * Get data from drupal.org API endpoint.
   */
  protected function getStats($machine_name) {
    $base_url = 'https://www.drupal.org/api-d7/node.json?field_project_machine_name=';
    $client = new Client();
    try {
      $res = $client->get($base_url . $machine_name, ['http_errors' => FALSE]);
      $body = $res->getBody();
      $decoded_body = json_decode($body, TRUE);
      if (!isset($decoded_body['list'][0])) {
        return [
          'project_type' => '',
          'name' => '',
          'url' => '',
          'total_usage' => 'n/a',
          'usage_per_version' => 'n/a',
          'created' => $this->t('n/a'),
          'changed' => $this->t('n/a'),
          'last_version' => $this->t('n/a'),
        ];
      }
      $project_type = $decoded_body['list'][0]['type'];
      $name = $decoded_body['list'][0]['title'];
      $total_usage = 0;
      foreach ($decoded_body['list'][0]['project_usage'] as $count) {
        $total_usage += $count;
      }
      $usage_per_version = $decoded_body['list'][0]['project_usage'];
      $created = $decoded_body['list'][0]['created'];
      $version_data = $this->getLastVersion($machine_name);
      $changed = $version_data['changed'];
      $last_version = $version_data['last_version'];
      $stats = [
        'project_type' => $project_type,
        'name' => $name,
        'url' => Url::fromUri('https://www.drupal.org/project/' . trim($machine_name)),
        'total_usage' => $total_usage,
        'usage_per_version' => $usage_per_version,
        'created' => date('d-m-Y', $created),
        'changed' => $changed,
        'last_version' => $last_version,
      ];
      return $stats;
    }
    catch (RequestException $e) {
      $this->messenger->addWarning($e->getMessage());
      $stats = [
        'project_type' => '',
        'name' => '',
        'url' => '',
        'total_usage' => 'n/a',
        'usage_per_version' => 'n/a',
        'created' => $this->t('n/a'),
        'changed' => $this->t('n/a'),
        'last_version' => $this->t('n/a'),
      ];
      return $stats;
    }
  }

  /**
   * Get release data from drupal.org API endpoint.
   */
  protected function getLastVersion($machine_name) {
    $client = new Client();
    try {
      $res = $client->get("https://updates.drupal.org/release-history/$machine_name/all", ['http_errors' => FALSE]);
      $xml = $res->getBody()->getContents();
      $versions = new SimpleXMLElement($xml);
      $last_version = isset($versions->releases->release->version) ? $versions->releases->release->version->__toString() : 'n/a';
      $changed = isset($versions->releases->release->date) ? date('d-m-Y', $versions->releases->release->date->__toString()) : 'n/a';
      return [
        'last_version' => $last_version,
        'changed' => $changed,
      ];
    }
    catch (RequestException $e) {
      $this->messenger->addWarning($e->getMessage());
      return [
        'last_version' => $this->t('n/a'),
        'changed' => $this->t('n/a'),
      ];
    }
  }

  /**
   * Sort projects.
   */
  protected function sortModulesList($a, $b) {
    $sort_by = $this->configuration['sort_by'];
    if ($sort_by == 'count') {
      return $a['total_usage_count_raw'] < $b['total_usage_count_raw'];
    }
    else {
      return strcmp($a['title'][0], $b['title'][0]);
    }
  }

  /**
   * Flattens array.
   */
  protected function flattenValue($data) {
    if (!is_array($data)) {
      return $data;
    }

    $flat = [];

    foreach ($data as $key => $value) {
      $flat[] = $key . ': ' . $value;
    }

    return implode(', ', $flat);
  }

}
