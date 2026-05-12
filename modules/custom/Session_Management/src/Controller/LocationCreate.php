<?php

namespace Drupal\session_management\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for location creation.
 */
class LocationCreate extends ControllerBase {

  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->formBuilder = $container->get('form_builder');
    return $instance;
  }

  /**
   * Renders the standalone location create page.
   */
  public function content(): array {
    return $this->formBuilder->getForm('Drupal\session_management\Form\LocationCreateForm');
  }

  /**
   * Returns the location create form inside an AJAX modal dialog.
   */
  public function modal(): AjaxResponse {
    $form = $this->formBuilder->getForm('Drupal\session_management\Form\LocationCreateForm');

    $response = new AjaxResponse();
    $response->addCommand(new OpenModalDialogCommand(
      $this->t('Create Location'),
      $form,
      [
        'width'  => '600px',
        'modal'  => TRUE,
        'resizable' => FALSE,
      ]
    ));

    return $response;
  }

}
