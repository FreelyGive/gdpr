<?php

/**
 * The Task entity class.
 */
class GDPRExportLog extends Entity implements GDPRExportLogInterface {

  /**
   * The internal numeric id of the task.
   *
   * @var integer
   */
  public $id;

  /**
   * The status of the task.
   *
   * @var string
   */
  public $export;

  /**
   * The users id that requested this task.
   *
   * @var integer
   */
  public $exported_by;

  /**
   * The users id that processed this task.
   *
   * @var integer
   */
  public $removed_by;

  /**
   * The status of the task.
   *
   * @var string
   */
  public $status;

  /**
   * The Unix timestamp when the task was created.
   *
   * @var integer
   */
  public $created;

  /**
   * The Unix timestamp when the task was most recently saved.
   *
   * @var integer
   */
  public $changed;

  /**
   * The Unix timestamp when the task was completed.
   *
   * @var integer
   */
  public $removed;

  /**
   * @var array|null
   */
  public $uids = NULL;

  /**
   * {@inheritdoc}
   */
  protected $defaultLabel = TRUE;

  /**
   * {@inheritdoc}
   */
  public function __construct($values = array()) {
    parent::__construct($values, 'gdpr_export_log');

    if (!is_array($this->uids)) {
      $this->init();
    }
  }

  public function init() {
    $uids = db_select('gdpr_export_log_uid', 'u')
      ->fields('u')
      ->condition('export_id', $this->identifier())
      ->execute()
      ->fetchAllAssoc('uid', PDO::FETCH_ASSOC);

    $this->setUsers($uids);
  }

  /**
   * {@inheritdoc}
   */
  protected function defaultLabel() {
    $view = views_get_view($this->export);
    return "Log {$this->id} - {$view->get_human_name()}";
  }

  /**
   * @return array
   */
  protected function defaultUserData() {
    return array(
      'export_id' => $this->identifier(),
    );
  }

  /**
   * @return array
   */
  public function getUsers() {
    return $this->uids;
  }

  /**
   * @param array $uids
   * @return $this
   */
  public function setUsers(array $uids) {
    $user_data = array();

    foreach ($uids as $uid => $data) {
      if (is_array($data)) {
        if (!isset($data['uid'])) {
          $data['uid'] = $uid;
        }
      }
      else {
        $data = array('uid' => $uid);
      }
      $data += $this->defaultUserData();
      $user_data[$data['uid']] = $data;
    }

    $this->uids = $user_data;
    return $this;
  }

}
