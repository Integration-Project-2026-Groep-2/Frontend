<?php
namespace Drupal\hello_world\Controller;

use Drupal\Core\Controller\ControllerBase;

class RegisterVisitorController extends ControllerBase {

  public function registerVisitor(): array {
    return \Drupal::formBuilder()->getForm('Drupal\hello_world\Form\RegisterVisitorForm');
  }
}