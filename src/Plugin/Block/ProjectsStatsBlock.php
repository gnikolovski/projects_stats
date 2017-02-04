<?php

namespace Drupal\projects_stats\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use SimpleXMLElement;

/**
 * Provides a 'ProjectsStatsBlock' block.
 *
 * @Block(
 *  id = "projects_stats",
 *  admin_label = @Translation("Projects stats"),
 *  category = @Translation("Custom"),
 * )
 */
class ProjectsStatsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Construct.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'machine_names' => '',
      'additional_columns' => [],
      'sort_by' => 'count',
      'cache_age' => 21600,
      'classes' => '',
      'target' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['machine_names'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Modules/themes machine names'),
      '#description' => $this->t('Specify modules/themes by using their machine names. Separate multiple values by a comma.'),
      '#default_value' => $this->configuration['machine_names'],
      '#required' => TRUE,
    ];
    $form['additional_columns'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Additional columns'),
      '#options' => [
        'created' => $this->t('Created date'),
        'changed' => $this->t('Last modified date'),
        'last_version' => $this->t('Last released version'),
      ],
      '#default_value' => $this->configuration['additional_columns'],
    ];
    $form['sort_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort by'),
      '#options' => [
        'count' => $this->t('Download count'),
        'name' => $this->t('Name'),
        'no' => $this->t('No sort'),
      ],
      '#default_value' => $this->configuration['sort_by'],
    ];
    $form['cache_age'] = [
      '#type' => 'select',
      '#title' => $this->t('Block cache age'),
      '#options' => [
        21600 => $this->t('6 hours'),
        43200 => $this->t('12 hours'),
        86400 => $this->t('24 hours'),
        0 => $this->t('No cache'),
      ],
      '#default_value' => $this->configuration['cache_age'],
    ];
    $form['classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Table classes'),
      '#default_value' => $this->configuration['classes'],
      '#description' => $this->t('Specify CSS classes for table. Separate multiple classes with empty space.'),
    ];
    $form['target'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open modules links in the new tab'),
      '#default_value' => $this->configuration['target'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['machine_names'] = $form_state->getValue('machine_names');
    $this->configuration['additional_columns'] = $form_state->getValue('additional_columns');
    $this->configuration['sort_by'] = $form_state->getValue('sort_by');
    $this->configuration['cache_age'] = $form_state->getValue('cache_age');
    $this->configuration['classes'] = $form_state->getValue('classes');
    $this->configuration['target'] = $form_state->getValue('target');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $machine_names = $this->configuration['machine_names'];
    $machine_names = explode(',', $machine_names);
    $additional_columns = $this->configuration['additional_columns'];
    $sort_by = $this->configuration['sort_by'];
    $cache_age = $this->configuration['cache_age'];
    $classes = $this->configuration['classes'];
    $target = $this->configuration['target'];

    $table_head = [$this->t('Title'), $this->t('Downloads')];
    foreach ($additional_columns as $key => $value) {
      if ($value !== 0) {
        $key = str_replace('_', ' ', $key);
        $key = ucfirst($key);
        $key = $this->t($key);
        $table_head[] = $value;
      }
    }

    $table_body = [];
    foreach ($machine_names as $machine_name) {
      $stats = $this->getStats(trim($machine_name));
      $table_body[] = [
        'title' => ucfirst(str_replace('_', ' ', trim($machine_name))),
        'url' => Url::fromUri('https://www.drupal.org/project/' . trim($machine_name)),
        'downloads' => number_format($stats['download_count'], 0, '.', ','),
        'downloads_raw' => $stats['download_count'],
        'created' => $additional_columns['created'] !== 0 ? $stats['created'] : NULL,
        'changed' => $additional_columns['changed'] !== 0 ? $stats['changed'] : NULL,
        'last_version' => $additional_columns['last_version'] !== 0 ? $stats['last_version'] : NULL,
      ];
    }

    if ($sort_by != 'no') {
      usort($table_body, [$this, 'sortModulesList']);
    }

    return [
      '#theme' => 'projects_stats',
      '#table_head' => $table_head,
      '#table_body' => $table_body,
      '#target' => $target == TRUE ? '_blank' : '_self',
      '#classes' => $classes,
      '#cache' => ['max-age' => $cache_age],
    ];
  }

  /**
   * Get data from drupal.org API endpoint.
   */
  private function getStats($machine_name) {
    $base_url = 'https://www.drupal.org/api-d7/node.json?field_project_machine_name=';
    $client = new Client();
    try {
      $res = $client->get($base_url . $machine_name, ['http_errors' => FALSE]);
      $body = $res->getBody();
      $decoded_body = json_decode($body, TRUE);
      if (!isset($decoded_body['list'][0])) {
        return 0;
      }
      $download_count = $decoded_body['list'][0]['field_download_count'];
      $created = $decoded_body['list'][0]['created'];
      $version_data = $this->getLastVersion($machine_name);
      $changed = $version_data['changed'];
      $last_version = $version_data['last_version'];
      $stats = [
        'download_count' => $download_count,
        'created' => date('d-m-Y', $created),
        'changed' => $changed,
        'last_version' => $last_version,
      ];
      return $stats;
    }
    catch (RequestException $e) {
      drupal_set_message($e);
      $stats = [
        'download_count' => 0,
        'created' => 'n/a',
        'changed' => 'n/a',
        'last_version' => 'n/a',
      ];
      return $stats;
    }
  }

  /**
   * Get release data from drupal.org API endpoint.
   */
  private function getLastVersion($machine_name) {
    $client = new Client();
    try {
      $res = $client->get("https://updates.drupal.org/release-history/$machine_name/all", ['http_errors' => FALSE]);
      $xml = $res->getBody()->getContents();
      $versions = new SimpleXMLElement($xml);
      $last_version = isset($versions->releases->release->version) ? $versions->releases->release->version : 'n/a';
      $changed = isset($versions->releases->release->date) ? date('d-m-Y', $versions->releases->release->date->__toString()) : 'n/a';
      return ['last_version' => $last_version, 'changed' => $changed];
    }
    catch (RequestException $e) {
      drupal_set_message($e);
      return ['last_version' => 'n/a', 'changed' => 'n/a'];
    }
  }

  /**
   * Sort projects.
   */
  private function sortModulesList($a, $b) {
    $sort_by = $this->configuration['sort_by'];
    if ($sort_by == 'count') {
      return $a['downloads_raw'] < $b['downloads_raw'];
    }
    else {
      return strcmp($a['title'][0], $b['title'][0]);
    }
  }

}
