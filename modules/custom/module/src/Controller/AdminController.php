<?php

namespace Drupal\hello_world\Controller;

use Drupal\Core\Controller\ControllerBase;

class AdminController extends ControllerBase {
    
    public function page(): array {
        return [
            '#type' => 'inline_template',
            '#template' => '
                <p>Dit is de admin pagina. Hier kan je zaken zoals sessies beheren, gebruikers bekijken en inschrijvingen opvolgen.</p>

                <nav>
                    <ul>
                        <li><a href="/admin/hello-world/sessies">Sessies</a></li>
                        <li><a href="/admin/hello-world/gebruikers">Gebruikers</a></li>
                        <li><a href="/admin/hello-world/inschrijvingen">Inschrijvingen</a></li>
                        <li><a href="/admin/hello-world/feedback">Feedback</a></li>
                    </ul>
                </nav>
            ',
            '#context' => [],
        ];
    }

}