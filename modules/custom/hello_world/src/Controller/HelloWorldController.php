<?php

namespace Drupal\hello_world\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns a Hello World page.
 */
class HelloWorldController extends ControllerBase {

	/**
	 * Returns a simple Hello World render array.
	 */
	public function hello(): array {
		return [
			'#type' => 'markup',
			'#markup' => $this->t('Hello, World! 👋'),
		];
	}

}