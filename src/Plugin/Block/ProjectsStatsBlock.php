<?php

namespace Drupal\projects_stats\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;

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
    $sort_by = $this->configuration['sort_by'];
    $cache_age = $this->configuration['cache_age'];
    $classes = $this->configuration['classes'];
    $target = $this->configuration['target'];
    $machine_names = explode(',', $machine_names);
    $stats = [];
    foreach ($machine_names as $machine_name) {
      $downloads = $this->performRequest(trim($machine_name));
      $stats[] = [
        'title' => ucfirst(str_replace('_', ' ', trim($machine_name))),
        'url' => Url::fromUri('https://www.drupal.org/project/' . trim($machine_name)),
        'downloads' => number_format($downloads, 0, '.', ','),
        'downloads_raw' => $downloads,
      ];
    }

    if ($sort_by != 'no') {
      usort($stats, [$this, 'sortModulesList']);
    }

    return [
      '#theme' => 'projects_stats',
      '#stats' => $stats,
      '#target' => $target == TRUE ? '_blank' : '_self',
      '#classes' => $classes,
      '#cache' => ['max-age' => $cache_age],
    ];
  }

  /**
   * Get data from drupal.org API endpoint.
   */
  private function performRequest($machine_names) {
    $base_url = 'https://www.drupal.org/api-d7/node.json?field_project_machine_name=';
    $client = new Client();
    try {
      $res = $client->get($base_url . $machine_names, ['http_errors' => FALSE]);
      $body = $res->getBody();
      $decoded_body = json_decode($body, TRUE);
      if (!isset($decoded_body['list'][0])) {
        return 0;
      }
      $download_count = $decoded_body['list'][0]['field_download_count'];
      $download_count = isset($download_count) ? $download_count : 0;
      return $download_count;
    }
    catch (RequestException $e) {
      drupal_set_message($e);
      return 0;
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
