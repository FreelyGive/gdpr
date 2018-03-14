<?php

namespace Drupal\gdpr_consent\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\gdpr_consent\Entity\ConsentAgreement;
use Drupal\gdpr_consent\Entity\ConsentAgreementInterface;

/**
 * Class ConsentAgreementController.
 *
 *  Returns responses for Consent Agreement routes.
 */
class ConsentAgreementController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Displays a Consent Agreement  revision.
   *
   * @param int $consent_agreement_revision
   *   The Consent Agreement  revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($gdpr_consent_agreement_revision) {
    $gdpr_consent_agreement = $this->entityManager()
      ->getStorage('gdpr_consent_agreement')
      ->loadRevision($gdpr_consent_agreement_revision);
    $view_builder = $this->entityManager()
      ->getViewBuilder('gdpr_consent_agreement');

    return $view_builder->view($gdpr_consent_agreement);
  }

  /**
   * Page title callback for a Consent Agreement  revision.
   *
   * @param int $gdpr_consent_agreement_revision
   *   The Consent Agreement  revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($gdpr_consent_agreement_revision) {
    $gdpr_consent_agreement = $this->entityManager()
      ->getStorage('gdpr_consent_agreement')
      ->loadRevision($gdpr_consent_agreement_revision);
    return $this->t('Revision of %title from %date', [
      '%title' => $gdpr_consent_agreement->label(),
      '%date' => format_date($gdpr_consent_agreement->getRevisionCreationTime()),
    ]);
  }

  /**
   * Generates an overview table of older revisions of a Consent Agreement .
   *
   * @param \Drupal\gdpr_consent\Entity\ConsentAgreement $agreement
   *   A Consent Agreement  object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview($gdpr_consent_agreement) {
    $agreement = ConsentAgreement::load($gdpr_consent_agreement);
    $account = $this->currentUser();
    $storage = $this->entityManager()->getStorage('gdpr_consent_agreement');

    $build['#title'] = $this->t('Revisions for %title', ['%title' => $agreement->label()]);
    $header = [$this->t('Revision'), $this->t('Operations')];

    $revert_permission = (($account->hasPermission("revert all consent agreement revisions") || $account->hasPermission('administer consent agreement entities')));
    $delete_permission = (($account->hasPermission("delete all consent agreement revisions") || $account->hasPermission('administer consent agreement entities')));

    $rows = [];

    $vids = $storage->revisionIds($agreement);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\gdpr_consent\Entity\ConsentAgreement $revision */
      $revision = $storage->loadRevision($vid);

      $username = [
        '#theme' => 'username',
        '#account' => $revision->getRevisionUser(),
      ];

      // Use revision link to link to revisions that are not active.
      $date = \Drupal::service('date.formatter')
        ->format($revision->getRevisionCreationTime(), 'short');
      if ($vid != $agreement->getRevisionId()) {
        $link = $this->l($date, new Url('entity.gdpr_consent_agreement.revision', [
          'gdpr_consent_agreement' => $agreement->id(),
          'gdpr_consent_agreement_revision' => $vid,
        ]));
      }
      else {
        $link = $agreement->link($date);
      }

      $row = [];
      $column = [
        'data' => [
          '#type' => 'inline_template',
          '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
          '#context' => [
            'date' => $link,
            'username' => \Drupal::service('renderer')->renderPlain($username),
            'message' => [
              '#markup' => $revision->getRevisionLogMessage(),
              '#allowed_tags' => Xss::getHtmlTagList(),
            ],
          ],
        ],
      ];
      $row[] = $column;

      if ($latest_revision) {
        $row[] = [
          'data' => [
            '#prefix' => '<em>',
            '#markup' => $this->t('Current revision'),
            '#suffix' => '</em>',
          ],
        ];
        foreach ($row as &$current) {
          $current['class'] = ['revision-current'];
        }
        $latest_revision = FALSE;
      }
      else {
        $links = [];
        if ($revert_permission) {
          $links['revert'] = [
            'title' => $this->t('Revert'),
            'url' => Url::fromRoute('entity.gdpr_consent_agreement.revision_revert', [
                'gdpr_consent_agreement' => $agreement->id(),
                'gdpr_consent_agreement_revision' => $vid,
              ]),
          ];
        }

        if ($delete_permission) {
          $links['delete'] = [
            'title' => $this->t('Delete'),
            'url' => Url::fromRoute('entity.gdpr_consent_agreement.revision_delete', [
              'gdpr_consent_agreement' => $agreement->id(),
              'gdpr_consent_agreement_revision' => $vid,
            ]),
          ];
        }

        $row[] = [
          'data' => [
            '#type' => 'operations',
            '#links' => $links,
          ],
        ];
      }

      $rows[] = $row;
    }

    $build['gdpr_consent_agreement_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
