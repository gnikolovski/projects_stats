<?php

namespace Drupal\Tests\projects_stats\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the user interface.
 *
 * @group projects_stats
 */
class ProjectsStatsUITest extends BrowserTestBase {

  use StringTranslationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'projects_stats',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalLogin($this->drupalCreateUser(['administer projects stats']));
  }

  /**
   * Tests form structure.
   */
  public function testFormStructure() {
    $this->drupalGet('admin/config/services/projects-stats');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->titleEquals('Projects Stats | Drupal');
    $this->assertSession()->checkboxNotChecked('edit-send-stats-to-slack');
    $this->assertSession()->buttonExists($this->t('Save configuration'));
  }

  /**
   * Tests form access.
   */
  public function testFormAccess() {
    $this->drupalLogout();
    $this->drupalGet('admin/config/services/projects-stats');
    $this->assertSession()->statusCodeEquals(403);
  }

}
