<?php

namespace Drupal\hello_world\Controller;

use Drupal\Core\Controller\ControllerBase;

class AdminController extends ControllerBase {
    
    public function page(): array {
        $visitors = $this->getVisitors();

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
                            {% if visitors is not empty %}
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {% for visitor in visitors %}
                                            <tr>
                                                <td>{{ visitor.name }}</td>
                                                <td>{{ visitor.mail }}</td>
                                            </tr>
                                        {% endfor %}
                                    </tbody>
                                </table>
                            {% else %}
                                <p>Geen bezoekers gevonden.</p>
                            {% endif %}
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
            '#context' => [
                'visitors' => $visitors,
            ],
            '#cache' => [
                'max-age' => 0,
            ],
        ];
    }

    private function getVisitors(): array {
        $uids = $this->entityTypeManager()
            ->getStorage('user')
            ->getQuery()
            ->accessCheck(FALSE)
            ->condition('roles', 'visitor')
            ->sort('created', 'DESC')
            ->range(0, 10)
            ->execute();

        if (empty($uids)) {
            return [];
        }

        $users = $this->entityTypeManager()->getStorage('user')->loadMultiple($uids);
        $result = [];

        foreach ($users as $user) {
            $result[] = [
                'name' => $user->getAccountName(),
                'mail' => $user->getEmail(),
            ];
        }

        return $result;
    }

}