<?php

namespace Drupal\guestbook\Form;

use Drupal\Component\Utility\EmailValidator;
use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Element;
use Drupal\file\Entity\File;
use Drupal\guestbook\Controller\GuestbookController;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a guestbook form for adding new records.
 */
class AddRecordForm extends FormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $dbConnection;

  /**
   * Class constructor.
   */
  public function __construct(Connection $dbConnection, MessengerInterface $messenger) {
    $this->dbConnection = $dbConnection;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): AddRecordForm {
    return new static(
      $container->get('database'),
      $container->get('messenger'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'guestbook_add_record';
  }

  /**
   * Returns a form selector based on a form ID.
   */
  public function getFormSelector(): string {
    return '.' . Html::cleanCssIdentifier($this->getFormId());
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['avatar'] = [
      '#type' => 'managed_image',
      '#title' => $this->t('Your avatar:'),
      '#accept' => 'image/jpeg, image/png',
      '#image_style' => 'guestbook_scale_crop_64_64',
      '#upload_location' => 'public://guestbook/avatars',
      '#upload_validators' => [
        'file_validate_extensions' => ['jpg jpeg png'],
        'file_validate_size' => [2097152],
      ],
    ];
    $form['name'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#maxlength' => 100,
      '#title' => $this->t('Your name:'),
    ];
    $form['phone'] = [
      '#type' => 'tel',
      '#required' => TRUE,
      '#maxlength' => 9,
      '#title' => $this->t('Your phone:'),
      '#placeholder' => $this->t('99 123 4568'),
    ];
    $form['email'] = [
      '#type' => 'email',
      '#required' => TRUE,
      '#size' => 320,
      '#element_validate' => [],
      '#title' => $this->t('Your email:'),
      '#placeholder' => $this->t('someone@example.com'),
    ];
    $form['message'] = [
      '#type' => 'textarea',
      '#required' => TRUE,
      '#maxlength' => 1000,
      '#title' => $this->t('Message:'),
    ];
    $form['attachment'] = [
      '#type' => 'managed_image',
      '#title' => $this->t('Attachment:'),
      '#accept' => 'image/jpeg, image/png',
      '#image_style' => 'guestbook_scale_200',
      '#upload_location' => 'public://guestbook/attachments',
      '#upload_validators' => [
        'file_validate_extensions' => ['jpg jpeg png'],
        'file_validate_size' => [5242880],
      ],
    ];
    $form['#attached']['library'] = ['guestbook/add_record_form'];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
      '#ajax' => [
        'callback' => '::submitAjaxCallback',
        'event' => 'click',
        'progress' => 'none',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $name_length = strlen($form_state->getValue('name'));
    if ($name_length < 2) {
      $form_state->setErrorByName('name', $this->t('Minimum name length 2 characters.'));
    }
    elseif ($name_length > 100) {
      $form_state->setErrorByName('name', $this->t('Maximum name length 100 characters.'));
    }
    if (!filter_var($form_state->getValue('email'), FILTER_VALIDATE_EMAIL)) {
      $error_message = $this->t('Please enter a valid email address.');
      $form_state->setErrorByName('email', $error_message);
    }
    if (!preg_match('/^\d{9}$/', $form_state->getValue('phone'))) {
      $error_message = $this->t('Please enter a valid phone number.');
      $form_state->setErrorByName('email', $error_message);
    }
    $message_length = strlen($form_state->getValue('message'));
    if ($message_length < 2) {
      $form_state->setErrorByName('message', $this->t('Minimum message length 2 characters.'));
    }
    elseif ($message_length > 1000) {
      $form_state->setErrorByName('message', $this->t('Maximum message length 1000 characters.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $avatar_fid = intval($form_state->getValue('avatar')[0]);
      if ($avatar_fid) {
        $file = File::load($avatar_fid);
        $file->setPermanent();
        $file->save();
      }
      $attachment_fid = intval($form_state->getValue('attachment')[0]);
      if ($attachment_fid) {
        $file = File::load($attachment_fid);
        $file->setPermanent();
        $file->save();
      }
      $fields = [
        'name' => $form_state->getValue('name'),
        'email' => $form_state->getValue('email'),
        'phone' => $form_state->getValue('phone'),
        'message' => $form_state->getValue('message'),
        'avatar_fid' => $avatar_fid,
        'attachment_fid' => $attachment_fid,
        'created' => \Drupal::time()->getRequestTime(),
      ];
      $this->dbConnection
        ->insert('guestbook')
        ->fields($fields)
        ->execute();
      $this->messenger->addStatus($this->t('The record has been added.'));
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('An error occurred while adding a record.'));
    }
  }

  /**
   * AJAX form submit handler.
   */
  public function submitAjaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $form_selector = $this->getFormSelector();
    $errors = $form_state->getErrors();
    foreach (Element::children($form) as $item_name) {
      $item_class = Html::cleanCssIdentifier("form-item-$item_name");
      $item_selector = "$form_selector .$item_class";
      $response->addCommand(new RemoveCommand("$item_selector > .error-message"));

      $error_message = $errors[$item_name];
      if ($error_message) {
        $error_message = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $error_message,
          '#attributes' => ['class' => ['error-message']],
        ];
        $response->addCommand(new AppendCommand($item_selector, $error_message));
      }
    }

    if (!$errors) {
      $controller = GuestbookController::create(\Drupal::getContainer());
      $response->addCommand(new HtmlCommand('.guestbook-list', $controller->buildRecords()));
      if (!array_key_exists('error', $this->messenger->all())) {
        $response->addCommand(new InvokeCommand($form_selector, 'resetForm'));
      }
    }
    $this->messenger->deleteAll();
    return $response;
  }

}
