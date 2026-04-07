<?php

namespace Drupal\hello_world\Controller;

use Drupal\Core\Controller\ControllerBase;

class AdminController extends ControllerBase {
    
    public function page(): array {
        return [
            '#type' => 'inline_template',
            '#template' => '
                <div class="admin-home">
                    
                    <p>Dit is de admin pagina. Hier kan je zaken zoals sessies beheren, gebruikers bekijken en inschrijvingen opvolgen.</p>

                    <div class="admin-cards">

                        <div class="admin-card">
                            <nav>
                                <ul>
                                    <li><a href="/admin/hello-world/sessies">Sessies</a></li>
                                    <li><a href="/admin/hello-world/gebruikers">Gebruikers</a></li>
                                    <li><a href="/admin/hello-world/inschrijvingen">Inschrijvingen</a></li>
                                    <li><a href="/admin/hello-world/feedback">Feedback</a></li>
                                </ul>
                            </nav>
                        </div>

                        <div class="admin-card">
                            <h2> Bedrijven </h2>
                        </div>

                        <div class="admin-card">
                            <h2> Bezoekers </h2>
                        </div>

                        <div class="admin-card">
                            <h2> Sessies </h2>
                        </div>

                        <div class="admin-card">
                            <h2> Feedback </h2>
                        </div>

                        <div class="admin-instellingen">
                            <a href="/hello-admin/instellingen">⚙️ Instellingen</a>
                        </div>

                    </div>
                </div>
            ',
            '#context' => [],
        ];
    }

}