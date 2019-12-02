<?php

namespace Drupal\projects_stats\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableDependencyTrait;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\projects_stats\ProjectsStatsBuildService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'ProjectsStatsBlock' block.
 *
 * @Block(
 *  id = "projects_stats",
 *  admin_label = @Translation("Projects Stats"),
 *  category = @Translation("Web services"),
 * )
 */
class ProjectsStatsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use CacheableDependencyTrait;

  /**
   * The Projects Stats build service.
   *
   * @var \Drupal\projects_stats\ProjectsStatsBuildService
   */
  protected $projectsStatsBuildService;

  /**
   * The cache tags invalidator service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * Constructs a new FieldBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\projects_stats\ProjectsStatsBuildService $projects_stats_build_service
   *   The Projects Stats build service.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ProjectsStatsBuildService $projects_stats_build_service, CacheTagsInvalidatorInterface $cache_tags_invalidator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->projectsStatsBuildService = $projects_stats_build_service;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('projects_stats.build_service'),
      $container->get('cache_tags.invalidator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'display_type' => 'table',
      'machine_names' => '',
      'description' => '',
      'additional_columns' => [],
      'sort_by' => 'count',
      'show_downloads' => TRUE,
      'collapsible_list' => FALSE,
      'cache_age' => 86400,
      'classes' => '',
      'target' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['display_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Display type'),
      '#options' => [
        'table' => $this->t('Table'),
        'list' => $this->t('List'),
      ],
      '#default_value' => $this->configuration['display_type'],
    ];

    $form['machine_names'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Project machine names'),
      '#description' => $this->t('Specify modules/themes/distributions by using their machine names. You can also enter user ID to fetch all projects associated with that user. Separate multiple values by a comma.'),
      '#default_value' => $this->configuration['machine_names'],
      '#required' => TRUE,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Description text is displayed above the projects list.'),
      '#default_value' => $this->configuration['description'],
    ];

    $form['additional_columns'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Additional columns'),
      '#options' => [
        'project_usage' => $this->t('Usage'),
        'created' => $this->t('Created date'),
        'changed' => $this->t('Last modified date'),
        'last_version' => $this->t('Last released version'),
      ],
      '#default_value' => $this->configuration['additional_columns'],
      '#states' => [
        'visible' => [
          [
            ':input[name="settings[display_type]"]' => ['value' => 'table'],
          ],
        ],
      ],
    ];

    $form['show_downloads'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show download count'),
      '#default_value' => $this->configuration['show_downloads'],
      '#states' => [
        'visible' => [
          [
            ':input[name="settings[display_type]"]' => ['value' => 'list'],
          ],
        ],
      ],
    ];

    $form['collapsible_list'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Make list collapsible'),
      '#default_value' => $this->configuration['collapsible_list'],
      '#states' => [
        'visible' => [
          [
            ':input[name="settings[display_type]"]' => ['value' => 'list'],
          ],
        ],
      ],
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
      '#states' => [
        'visible' => [
          [
            ':input[name="settings[display_type]"]' => ['value' => 'table'],
          ],
        ],
      ],
    ];

    $form['cache_age'] = [
      '#type' => 'select',
      '#title' => $this->t('Block cache age'),
      '#options' => [
        21600 => $this->t('6 hours'),
        43200 => $this->t('12 hours'),
        86400 => $this->t('24 hours'),
        172800 => $this->t('2 days'),
        432000 => $this->t('5 days'),
        604800 => $this->t('7 days'),
        864000 => $this->t('10 days'),
        1209600 => $this->t('14 days'),
        0 => $this->t('No cache'),
      ],
      '#default_value' => $this->configuration['cache_age'],
    ];

    $form['classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Table/list classes'),
      '#default_value' => $this->configuration['classes'],
      '#description' => $this->t('Specify CSS classes for table/list. Separate multiple classes with empty space.'),
    ];

    $form['target'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open project links in the new tab'),
      '#default_value' => $this->configuration['target'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['display_type'] = $form_state->getValue('display_type');
    $this->configuration['machine_names'] = $form_state->getValue('machine_names');
    $this->configuration['description'] = $form_state->getValue('description');
    $this->configuration['additional_columns'] = $form_state->getValue('additional_columns');
    $this->configuration['sort_by'] = $form_state->getValue('sort_by');
    $this->configuration['show_downloads'] = $form_state->getValue('show_downloads');
    $this->configuration['collapsible_list'] = $form_state->getValue('collapsible_list');
    $this->configuration['cache_age'] = $form_state->getValue('cache_age');
    $this->configuration['classes'] = $form_state->getValue('classes');
    $this->configuration['target'] = $form_state->getValue('target');

    $this->cacheTagsInvalidator->invalidateTags(['projects_stats:config_form']);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return $this->projectsStatsBuildService
      ->generate(json_encode($this->configuration));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
