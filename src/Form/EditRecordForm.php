<?php

namespace Drupal\guestbook\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a Guestbook form.
 */
class EditRecordForm extends AddRecordForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'guestbook_edit_record';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL): array {
    $row = \Drupal::database()
      ->select('guestbook', 't')
      ->fields('t')
      ->condition('id', $id)
      ->execute()
      ->fetch();
    if (!$row) {
      throw new NotFoundHttpException();
    }
    $form = parent::buildForm($form, $form_state);
    $form['avatar']['#default_value'] = [$row->avatar_fid];
    $form['name']['#default_value'] = $row->name;
    $form['phone']['#default_value'] = $row->phone;
    $form['email']['#default_value'] = $row->email;
    $form['message']['#default_value'] = $row->message;
    $form['attachment']['#default_value'] = [$row->attachment_fid];
    $form['actions']['submit']['#value'] = $this->t('Save');
    unset($form['actions']['submit']['#ajax']);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $id = \Drupal::routeMatch()->getParameter('id');
      $dbConnection = \Drupal::database();
      $row = $dbConnection
        ->select('guestbook', 't')
        ->fields('t', ['attachment_fid', 'avatar_fid'])
        ->condition('id', $id)
        ->execute()
        ->fetch();

      $avatar_fid = intval(@$form_state->getValue('avatar')[0]);
      $prev_avatar_fid = $row->avatar_fid;
      if ($prev_avatar_fid != $avatar_fid) {
        if ($prev_avatar_fid) {
          $file = File::load($prev_avatar_fid);
          $file->setTemporary();
          $file->save();
        }
        if ($avatar_fid) {
          $file = File::load($avatar_fid);
          $file->setPermanent();
          $file->save();
        }
      }
      $attachment_fid = intval(@$form_state->getValue('attachment')[0]);
      $prev_attachment_fid = $row->attachment_fid;
      if ($prev_attachment_fid != $attachment_fid) {
        if ($prev_attachment_fid) {
          $file = File::load($prev_attachment_fid);
          $file->setTemporary();
          $file->save();
        }
        if ($attachment_fid) {
          $file = File::load($attachment_fid);
          $file->setPermanent();
          $file->save();
        }
      }

      $fields = [
        'name' => $form_state->getValue('name'),
        'email' => $form_state->getValue('email'),
        'phone' => $form_state->getValue('phone'),
        'message' => $form_state->getValue('message'),
        'avatar_fid' => $avatar_fid,
        'attachment_fid' => $attachment_fid,
      ];
      $dbConnection
        ->update('guestbook')
        ->condition('id', $id)
        ->fields($fields)
        ->execute();

      $this->messenger()->addStatus($this->t('The record has been edited.'));
      $form_state->setRedirectUrl(Url::fromRoute('guestbook.main'));
    }
    catch (\Exception $e) {
      $this->messenger()
        ->addError($this->t('An error occurred while editing.'));
    }
  }

}
