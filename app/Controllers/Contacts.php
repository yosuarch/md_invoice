<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ActivityModel;
use App\Models\ContactPersonModel;
use App\Models\CustomerContactModel;
use App\Services\ContactService;
use App\Services\ValidationFailed;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * People (contact_person) shared by schools: list, page with their schools and activities, edit, JSON search for
 * the "existing person" picker. Visibility: people linked to schools the user may see (AccessScope).
 */
class Contacts extends BaseController
{
    /**
     * Searchable list.
     *
     * @return string
     */
    public function index(): string
    {
        $q     = trim((string) $this->request->getGet('q'));
        $model = new ContactPersonModel();

        return view('contacts/index', [
            'people' => $this->scope()->contacts($model->search($q))->paginate(25),
            'pager'  => $model->pager,
            'search' => $q,
        ]);
    }

    /**
     * Person page: details, schools (roles), activities.
     *
     * @param int $id Person id.
     *
     * @return string
     */
    public function show(int $id): string
    {
        $person = $this->findPerson($id);
        $links  = array_values(array_filter((new CustomerContactModel())->forPerson($id), fn (array $l): bool => $this->scope()->canCustomer((int) $l['customer_id'])));

        return view('contacts/show', [
            'person'     => $person,
            'links'      => $links,
            'activities' => user_can('crm.view')
                ? $this->scope()->activities((new ActivityModel())->withDetails()->where('crm_activity.contact_id', $id))->timelineOrder()->findAll(50)
                : null,
        ]);
    }

    /**
     * Edit form of the person.
     *
     * @param int $id Person id.
     *
     * @return string
     */
    public function edit(int $id): string
    {
        return view('contacts/form', ['person' => $this->findPerson($id), 'links' => (new CustomerContactModel())->forPerson($id)]);
    }

    /**
     * Save the person (all schools see the change).
     *
     * @param int $id Person id.
     *
     * @return RedirectResponse
     */
    public function update(int $id): RedirectResponse
    {
        $this->findPerson($id);

        try {
            (new ContactService())->savePerson($id, $this->postFields([...ContactService::PERSON_FIELDS, 'is_active']));
        } catch (ValidationFailed $e) {
            return $this->backWithErrors($e->errors);
        } catch (Throwable $e) {
            return $this->failed($e, 'Contact update');
        }

        return redirect()->to('contacts/' . $id)->with('message', lang('Contacts.updated'));
    }

    /**
     * JSON search for the "existing person" picker (?q=): name, phone or email.
     *
     * @return ResponseInterface
     */
    public function search(): ResponseInterface
    {
        try {
            $q    = trim((string) $this->request->getGet('q'));
            $rows = $q === '' ? [] : $this->scope()->contacts((new ContactPersonModel())->search($q))->where('contact_person.is_active', 1)->findAll(15);

            return $this->response->setJSON(['results' => array_map(static fn (array $p): array => [
                'id'      => (int) $p['id'],
                'name'    => ContactPersonModel::displayName($p),
                'phone'   => (string) $p['cp_phone'],
                'email'   => (string) $p['cp_email'],
                'schools' => implode(', ', array_map(static fn (array $s): string => up($s['name']), ContactPersonModel::parseSchools($p['schools']))),
            ], $rows)]);
        } catch (Throwable $e) {
            log_message('error', 'Contact search failed: {message}', ['message' => $e->getMessage()]);

            return $this->response->setStatusCode(500)->setJSON(['error' => lang('Js.searchFailed')]);
        }
    }

    /**
     * Visible person or 404.
     *
     * @param int $id Person id.
     *
     * @return array<string, mixed>
     */
    private function findPerson(int $id): array
    {
        $person = (new ContactPersonModel())->find($id);

        if ($person === null || ! $this->scope()->canContact($id)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $person;
    }
}
