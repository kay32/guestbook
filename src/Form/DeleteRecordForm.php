<?php

namespace Drupal\guestbook\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a confirmation form before deleting records.
 */
class DeleteRecordForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'guestbook_delete_record';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to delete this record?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('guestbook.main');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $dbConnection = \Drupal::database();
      $id = \Drupal::routeMatch()->getParameter('id');
      $row = $dbConnection
        ->select('guestbook', 't')
        ->fields('t', ['avatar_fid', 'attachment_fid'])
        ->condition('id', $id)
        ->execute()
        ->fetch();
      foreach ($row as $fid) {
        if ($fid) {
          $file = File::load($fid);
          $file->setTemporary();
          $file->save();
        }
      }
      $dbConnection
        ->delete('guestbook')
        ->condition('id', $id)
        ->execute();
      $this->messenger()->addStatus($this->t('The record has been deleted.'));
    }
    catch
    (\Exception $e) {
      $this->messenger()
        ->addStatus($this->t('An error occurred while deleting.'));
    }
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
