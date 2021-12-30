<?php

namespace Drupal\guestbook\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\guestbook\Form\AddRecordForm;
use PDO;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Guestbook routes.
 */
class GuestbookController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $dbConnection;

  /**
   * The extension list.
   *
   * @var \Drupal\Core\Extension\ExtensionList
   */
  protected ExtensionList $extensionList;

  /**
   * Class constructor.
   */
  public function __construct(FormBuilderInterface $formBuilder, Connection $dbConnection, ExtensionList $extensionList) {
    $this->formBuilder = $formBuilder;
    $this->dbConnection = $dbConnection;
    $this->extensionList = $extensionList;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): GuestbookController {
    return new static(
      $container->get('form_builder'),
      $container->get('database'),
      $container->get('extension.list.module'),
    );
  }

  /**
   * Builds the response.
   */
  public function build(): array {

    $build['content'] = [
      '#theme' => 'guestbook',
      '#form' => $this->formBuilder->getForm(AddRecordForm::class),
      '#records' => $this->buildRecords(),
      '#attached' => ['library' => ['guestbook/guestbook']],
    ];

    return $build;
  }

  /**
   * Returns a render array of guestbook records.
   */
  public function buildRecords(): array {
    $rows = $this->dbConnection
      ->select('guestbook', 't')
      ->fields('t')
      ->orderBy('created', 'DESC')
      ->execute()
      ->fetchAll(PDO::FETCH_ASSOC);
    $records = [];
    foreach ($rows as $row) {
      if ($row['avatar_fid']) {
        $row['avatar'] = [
          '#theme' => 'image_style',
          '#style_name' => 'guestbook_scale_crop_64_64',
          '#uri' => File::load($row['avatar_fid'])->getFileUri(),
          '#alt' => strtolower($row['name']),
        ];
      }
      else {
        $row['avatar'] = $this->extensionList->getPath('guestbook') . '/images/avatar.png';
      }
      if ($row['attachment_fid']) {
        $attachment_file = File::load($row['attachment_fid']);
        $row['attachment'] = [
          '#type' => 'link',
          '#url' => Url::fromUri($attachment_file->createFileUrl(FALSE)),
          '#title' => [
            '#theme' => 'image_style',
            '#style_name' => 'guestbook_scale_200',
            '#uri' => $attachment_file->getFileUri(),
          ],
          '#attributes' => ['target' => '_blank'],
        ];
      }
      $records[] = [
        '#theme' => 'guestbook_record',
        '#content' => $row,
      ];
    }
    return $records;
  }

}
