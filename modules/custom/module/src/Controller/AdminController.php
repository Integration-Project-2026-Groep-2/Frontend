<?php

namespace Drupal\hello_world\Controller;

use Drupal\Core\Controller\ControllerBase;

class AdminController extends ControllerBase {
    
    public function page(): array {
        $vOption = $this->getSortOption('v', 'name_asc');
        $cOption = $this->getSortOption('c', 'name_asc');

        [$vField, $vDir] = $this->mapSortOption($vOption);
        [$cField, $cDir] = $this->mapSortOption($cOption);

        $visitors = $this->getVisitors($vField, $vDir);
        $companies = $this->getCompanies($cField, $cDir);

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
                            <h2>Bedrijven</h2>

                            <form method="get" style="margin-bottom:10px;">
                                <input type="hidden" name="v_sort" value="{{ v_option }}">
                                <select name="c_sort">
                                    <option value="name_asc" {{ c_option == "name_asc" ? "selected" : "" }}>Alphabetical A → Z</option>
                                    <option value="name_desc" {{ c_option == "name_desc" ? "selected" : "" }}>Alphabetical Z → A</option>
                                    <option value="created_asc" {{ c_option == "created_asc" ? "selected" : "" }}>Account creation ↑</option>
                                    <option value="created_desc" {{ c_option == "created_desc" ? "selected" : "" }}>Account creation ↓</option>
                                    <option value="access_asc" {{ c_option == "access_asc" ? "selected" : "" }}>Last online ↑</option>
                                    <option value="access_desc" {{ c_option == "access_desc" ? "selected" : "" }}>Last online ↓</option>
                                </select>
                                <button type="submit">Sort</button>
                            </form>

                            {% if companies is not empty %}
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {% for company in companies %}
                                            <tr>
                                                <td>{{ company.name }}</td>
                                                <td>{{ company.mail }}</td>
                                            </tr>
                                        {% endfor %}
                                    </tbody>
                                </table>
                            {% else %}
                                <p>Geen bedrijven gevonden.</p>
                            {% endif %}
                        </div>

                        <div class="admin-card">
                            <h2>Bezoekers</h2>

                            <form method="get" style="margin-bottom:10px;">
                                <input type="hidden" name="c_sort" value="{{ c_option }}">
                                <select name="v_sort">
                                    <option value="name_asc" {{ v_option == "name_asc" ? "selected" : "" }}>Alphabetical A → Z</option>
                                    <option value="name_desc" {{ v_option == "name_desc" ? "selected" : "" }}>Alphabetical Z → A</option>
                                    <option value="created_asc" {{ v_option == "created_asc" ? "selected" : "" }}>Account creation ↑</option>
                                    <option value="created_desc" {{ v_option == "created_desc" ? "selected" : "" }}>Account creation ↓</option>
                                    <option value="access_asc" {{ v_option == "access_asc" ? "selected" : "" }}>Last online ↑</option>
                                    <option value="access_desc" {{ v_option == "access_desc" ? "selected" : "" }}>Last online ↓</option>
                                </select>
                                <button type="submit">Sort</button>
                            </form>

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
                'companies' => $companies,
                'v_option' => $vOption,
                'c_option' => $cOption,
            ],
            '#cache' => ['max-age' => 0],
        ];
    }

    private function getVisitors(string $sortField = 'name', string $sortDir = 'ASC'): array {
        $uids = $this->entityTypeManager()->getStorage('user')->getQuery()
            ->accessCheck(FALSE)
            ->condition('roles', 'visitor')
            ->sort($sortField, strtoupper($sortDir))
            ->range(0, 50)
            ->execute();

        if (empty($uids)) return [];

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

    private function getCompanies(string $sortField = 'name', string $sortDir = 'ASC'): array {
        $uids = $this->entityTypeManager()->getStorage('user')->getQuery()
            ->accessCheck(FALSE)
            ->condition('roles', 'company')
            ->sort($sortField, strtoupper($sortDir))
            ->range(0, 50)
            ->execute();

        if (empty($uids)) return [];

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

    private function getSortOption(string $prefix, string $default): string {
        $allowed = ['name_asc', 'name_desc', 'created_asc', 'created_desc', 'access_asc', 'access_desc'];
        $value = (string) \Drupal::request()->query->get($prefix . '_sort', $default);
        return in_array($value, $allowed, TRUE) ? $value : $default;
    }

    private function mapSortOption(string $option): array {
        return match ($option) {
            'name_desc' => ['name', 'DESC'],
            'created_asc' => ['created', 'ASC'],
            'created_desc' => ['created', 'DESC'],
            'access_asc' => ['access', 'ASC'],
            'access_desc' => ['access', 'DESC'],
            default => ['name', 'ASC'],
        };
    }
}