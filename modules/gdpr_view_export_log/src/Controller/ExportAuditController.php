<?php

namespace Drupal\gdpr_view_export_log\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\gdpr_view_export_log\Entity\ExportAudit;
use Drupal\user\Entity\User;

class ExportAuditController extends ControllerBase {

  public function viewUsers($id) {
    $audit = ExportAudit::load($id);
    $ids = [];

    foreach ($audit->get('user_ids') as $field) {
      $ids[] = $field->value;
    }

    $users = User::loadMultiple($ids);

    $output = [
      'back' => [
        '#type' => 'link',
        '#url' => Url::fromRoute('entity.gdpr_view_export_audit.collection'),
        '#title' => $this->t('Back to export list'),
      ],

      'table' => [
        '#type' => 'table',
        '#header' => ['User', ''],
        '#empty' => 'Could not locate any users in this export.',
      ],
    ];


    foreach ($users as $user_id => $user) {
      $output['table'][$user_id]['username'] = [
        '#theme' => 'username',
        '#account' => $user,
      ];

      $links['revert'] =

      $output['table'][$user_id]['operations'] = [
        '#type' => 'operations',
        '#links' => [
          'remove' => [
            'title' => $this->t('Remove'),
            'url' => Url::fromRoute('gdpr_view_export_log.delete_user', [
              'id' => $id,
              'user_id' => $user_id,
            ]),
          ],
        ],
      ];
    }

    return $output;

  }
}