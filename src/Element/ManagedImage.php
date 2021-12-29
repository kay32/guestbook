<?php

namespace Drupal\guestbook\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Element\ManagedFile;

/**
 * @FormElement("managed_image")
 */
class ManagedImage extends ManagedFile {

  /**
   * {@inheritdoc}
   */
  public static function processManagedFile(&$element, FormStateInterface $form_state, &$complete_form) {
    $element['#upload_validators'] += [
      'file_validate_is_image' => [],
    ];
    $element['#description'] = [
      '#theme' => 'file_upload_help',
      '#description' => isset($element['#description']) ? $element['#description'] : NULL,
      '#upload_validators' => $element['#upload_validators'],
    ];
    return parent::processManagedFile($element, $form_state, $complete_form);
  }

  /**
   * {@inheritdoc}
   */
  public static function preRenderManagedFile($element): array {
    $element = parent::preRenderManagedFile($element);

    if (!empty($element['#files'])) {
      foreach ($element['#files'] as $delta => $file) {
        /** @var \Drupal\file\Entity\File $file */
        $element['file_' . $delta] = [
            'preview' => [
              '#type' => 'container',
              '#weight' => -10,
              '#attributes' => [
                'class' => [
                  'image-preview',
                ],
              ],
              'image' => [
                '#theme' => 'image_style',
                '#style_name' => $element['#image_style'],
                '#uri' => $file->getFileUri(),
              ],
            ],
          ] + $element['file_' . $delta];
      }
    }

    return $element;
  }

}
