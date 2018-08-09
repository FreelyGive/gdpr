<?php

namespace Drupal\Tests\gdpr_tasks\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests GDPR tasks and UI.
 *
 * @group gdpr
 */
class GdprTasksUITest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'gdpr',
    'gdpr_fields',
    'gdpr_tasks',
    'anonymizer',
    'file',
  ];

  /**
   * Testing admin user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->adminUser = $this->createUser([], NULL, TRUE);
    // @TODO update page permission requirements.
    $this->adminUser->addRole('administrator');
    $this->adminUser->save();
  }

  /**
   * Test GDPR tasks list page and task pages for various scenarios.
   */
  public function testViewFieldsList() {
    // Check the site has installed successfully.
    $this->drupalGet('');
    $this->assertSession()->statusCodeEquals(200);

    // Check that prior to logging in, we can't access the fields list.
    $this->drupalGet('admin/gdpr/tasks');
    $this->assertSession()->statusCodeEquals(403);

    // Gain access to the fields list.
    $this->drupalLogin($this->adminUser);

    // Scenario 1 - There are no tasks.
    $this->drupalGet('admin/gdpr/tasks');
    $session = $this->assertSession();
    $session->statusCodeEquals(200);
    $session->elementTextContains('css', '.empty.message', 'There is no open Task yet');

    // Create a new removal task.
    $id = $this->createTask('gdpr_remove', 'requested');

    // Scenario 2 - Requested removal task shows up in requested table.
    $this->drupalGet('admin/gdpr/tasks');
    $session = $this->assertSession();
    $session->elementTextContains('css', 'table.tasks-requested td a', "Task $id");

    // Scenario 3 - Removal task with no configured fields.
    $this->drupalGet("admin/gdpr/tasks/$id");
    $session = $this->assertSession();
    $session->statusCodeEquals(200);
    $session->elementTextContains('css', 'details#task-data table .empty.message', "There are no GDPR fields.");

    // Add some field configuration.
    $this->addGdprFieldConfig();

    // Scenario 4 - Removal task with configured user fields.
    $this->drupalGet("admin/gdpr/tasks/$id");
    $session = $this->assertSession();
    $session->elementTextContains('css', 'details#task-data table tr td + td', $this->adminUser->getUsername());
    $session->elementTextContains('css', 'details#task-data table tr + tr td + td', $this->adminUser->getUsername() . '@example.com');

    // Create a new export task.
    $id = $this->createTask('gdpr_sar', 'requested', TRUE);

    // Scenario 5 - Requested export task does not show up in requested table.
    $this->drupalGet('admin/gdpr/tasks');
    $session = $this->assertSession();
    $session->elementTextContains('css', '.empty.message', 'There are no tasks to be reviewed yet.');

    // Process the queued tasks.
    $this->processQueue();

    // Scenario 6 - Requested export task shows up in needs review table.
    $this->drupalGet('admin/gdpr/tasks');
    $session = $this->assertSession();
    $session->elementTextContains('css', 'table.tasks-reviewing td a', "Task $id");

    // Scenario 7 - Export task with configured user fields.
    $this->drupalGet("admin/gdpr/tasks/$id");
    $session = $this->assertSession();
    $session->elementTextContains('css', 'details#task-data table tr td + td', $this->adminUser->getUsername());
    $session->elementTextContains('css', 'details#task-data table tr + tr td + td', $this->adminUser->getUsername() . '@example.com');
  }

  /**
   * Create a new task.
   *
   * @param string $type
   *   The task type.
   * @param string $status
   *   The task status.
   * @param bool $queue_task
   *   Whether to queue a task for processing.
   *
   * @return int|false
   *   The id of the created task or false if not successful.
   */
  protected function createTask($type, $status, $queue_task = FALSE) {
    $values = [
      'type' => $type,
      'user_id' => $this->adminUser->id(),
      'status' => $status,
    ];
    $new_task = $this->container->get('entity_type.manager')->getStorage('gdpr_task')
      ->create($values);
    $new_task->save();

    if ($queue_task && $type === 'gdpr_sar') {
      $queue = $this->container->get('queue')->get('gdpr_tasks_process_gdpr_sar');
      $queue->createQueue();
      $queue->createItem($new_task->id());
    }

    return $new_task->id();
  }

  /**
   * Process and tasks that have been queued.
   */
  protected function processQueue() {
    /* @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = $this->container->get('queue')->get('gdpr_tasks_process_gdpr_sar');
    /* @var \Drupal\gdpr_tasks\Plugin\QueueWorker\GdprTasksSarWorker $worker */
    $worker = \Drupal::service('plugin.manager.queue_worker')->createInstance('gdpr_tasks_process_gdpr_sar', array());

    // Processes our queued items.
    while ($item = $queue->claimItem()) {
      $worker->processItem($item->data);
    }
  }

  /**
   * Import user GDPR field config.
   */
  protected function addGdprFieldConfig() {
    /* @var \Drupal\Core\Config\Config $config */
    $config = $this->container->get('config.factory')->getEditable('gdpr_fields.gdpr_fields_config.user');
    $config_data = json_decode('{"langcode":"en","status":true,"dependencies":[],"id":"user","filenames":{"user":"user"},"bundles":{"user":{"mail":{"bundle":"user","name":"mail","entity_type_id":"user","rtf":"anonymize","rta":"inc","enabled":true,"anonymizer":"email_anonymizer","notes":"","relationship":0,"sars_filename":"user"},"name":{"bundle":"user","name":"name","entity_type_id":"user","rtf":"anonymize","rta":"inc","enabled":true,"anonymizer":"username_anonymizer","notes":"","relationship":0,"sars_filename":"user"}}}}', TRUE);

    foreach ($config_data as $key => $data) {
      $config->set($key, $data);
    }

    $config->save();
  }

}
