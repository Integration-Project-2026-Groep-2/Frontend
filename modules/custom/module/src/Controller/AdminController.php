<?php

namespace Drupal\hello_world\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AdminController extends ControllerBase {

    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack) {
        $this->requestStack = $requestStack;
    }

    public static function create(ContainerInterface $container): static {
        return new static(
            $container->get('request_stack')
        );
    }

    public function page(): array {
        $vOption = $this->getSortOption('v', 'name_asc');
        $cOption = $this->getSortOption('c', 'name_asc');
        $vQuery = $this->getSearchTerm('v');
        $cQuery = $this->getSearchTerm('c');

        [$vField, $vDir] = $this->mapSortOption($vOption);
        [$cField, $cDir] = $this->mapSortOption($cOption);

        $visitors = $this->getUserByRole('visitor', $vField, $vDir, $vQuery);
        $companies = $this->getUserByRole('company', $cField, $cDir, $cQuery);

        return [
            '#type' => 'inline_template',
            '#template' => '
                <div class="admin-home">
                    
                    <p>Dit is de admin pagina. Hier kan je zaken zoals sessies beheren, gebruikers bekijken en inschrijvingen opvolgen.</p>

                    <div class="admin-cards">

                        <div class="admin-card">
                            <h2>Bedrijven</h2>

                            <form method="get" style="margin-bottom:10px;">
                                <input type="hidden" name="v_sort" value="{{ v_option }}">
                                <input type="hidden" name="v_q" value="{{ v_query }}">
                                <select name="c_sort">
                                    <option value="name_asc" {{ c_option == "name_asc" ? "selected" : "" }}>Alphabetical A → Z</option>
                                    <option value="name_desc" {{ c_option == "name_desc" ? "selected" : "" }}>Alphabetical Z → A</option>
                                    <option value="created_asc" {{ c_option == "created_asc" ? "selected" : "" }}>Account creation ↑</option>
                                    <option value="created_desc" {{ c_option == "created_desc" ? "selected" : "" }}>Account creation ↓</option>
                                    <option value="access_asc" {{ c_option == "access_asc" ? "selected" : "" }}>Last online ↑</option>
                                    <option value="access_desc" {{ c_option == "access_desc" ? "selected" : "" }}>Last online ↓</option>
                                </select>
                                <input type="text" name="c_q" value="{{ c_query }}" placeholder="Search name">
                                <button type="submit">Sort/Search</button>
                            </form>

                            <div style="max-height: 420px; overflow-y: auto; border: 1px solid #ddd;">
                                {% if companies is not empty %}
                                    <table style="width: 100%; border-collapse: separate; border-spacing: 0;">
                                        <thead>
                                            <tr>
                                                <th style="position: sticky; top: 0; background: #fff; z-index: 1;">Name</th>
                                                <th style="position: sticky; top: 0; background: #fff; z-index: 1;">Email</th>
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
                        </div>

                        <div class="admin-card">
                            <h2>Bezoekers</h2>

                            <form method="get" style="margin-bottom:10px;">
                                <input type="hidden" name="c_sort" value="{{ c_option }}">
                                <input type="hidden" name="c_q" value="{{ c_query }}">
                                <select name="v_sort">
                                    <option value="name_asc" {{ v_option == "name_asc" ? "selected" : "" }}>Alphabetical A → Z</option>
                                    <option value="name_desc" {{ v_option == "name_desc" ? "selected" : "" }}>Alphabetical Z → A</option>
                                    <option value="created_asc" {{ v_option == "created_asc" ? "selected" : "" }}>Account creation ↑</option>
                                    <option value="created_desc" {{ v_option == "created_desc" ? "selected" : "" }}>Account creation ↓</option>
                                    <option value="access_asc" {{ v_option == "access_asc" ? "selected" : "" }}>Last online ↑</option>
                                    <option value="access_desc" {{ v_option == "access_desc" ? "selected" : "" }}>Last online ↓</option>
                                </select>
                                <input type="text" name="v_q" value="{{ v_query }}" placeholder="Search name">
                                <button type="submit">Sort/Search</button>
                            </form>

                            <div style="max-height: 420px; overflow-y: auto; border: 1px solid #ddd;">
                                {% if visitors is not empty %}
                                    <table style="width: 100%; border-collapse: separate; border-spacing: 0;">
                                        <thead>
                                            <tr>
                                                <th style="position: sticky; top: 0; background: #fff; z-index: 1;">Name</th>
                                                <th style="position: sticky; top: 0; background: #fff; z-index: 1;">Email</th>
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
                        </div>

                        <div class="admin-card">
                            <h2> Sessies </h2>
                        </div>

                        <div class="admin-card">
                            <h2> Feedback </h2>
                            <a href="#">Bekijk feedback</a>
                        </div>

                        <div class="admin-instellingen">
                            <a href="#">⚙️ Instellingen</a>
                        </div>

                    </div>
                </div>
            ',
            '#context' => [
                'visitors' => $visitors,
                'companies' => $companies,
                'v_option' => $vOption,
                'c_option' => $cOption,
                'v_query' => $vQuery,
                'c_query' => $cQuery,
            ],
            '#cache' => [
                'max-age' => Cache::PERMANENT,
                'contexts' => [
                    'url.query_args:v_sort',
                    'url.query_args:v_q',
                    'url.query_args:c_sort',
                    'url.query_args:c_q',
                ],
                'tags' => ['user_list'],
            ],
        ];
    }

    private function getUserByRole(string $role, string $sortField = 'name', string $sortDir = 'ASC', string $search = ''): array {
        $query = $this->entityTypeManager()->getStorage('user')->getQuery()
            ->accessCheck(TRUE)
            ->condition('roles', $role)
            ->sort($sortField, strtoupper($sortDir))
            ->range(0, 50);

        if ($search !== '') {
            $escaped = Database::getConnection()->escapeLike($search);
            $query->condition('name', '%' . $escaped . '%', 'LIKE');
        }

        $uids = $query->execute();
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

    private function getSearchTerm(string $prefix): string {
        $request = $this->requestStack->getCurrentRequest();
        $value = trim((string) $request->query->get($prefix . '_q', ''));
        return mb_substr($value, 0, 100);
    }

    private function getSortOption(string $prefix, string $default): string {
        $allowed = ['name_asc', 'name_desc', 'created_asc', 'created_desc', 'access_asc', 'access_desc'];
        $request = $this->requestStack->getCurrentRequest();
        $value = (string) $request->query->get($prefix . '_sort', $default);

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