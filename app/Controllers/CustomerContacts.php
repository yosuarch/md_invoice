<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ContactRoleModel;
use App\Models\CustomerContactModel;
use App\Models\CustomerModel;
use App\Services\ContactService;
use App\Services\ValidationFailed;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;
use Throwable;

/**
 * Contacts of a school (permission contacts.manage, school visible to the user): link an existing person or a new
 * one, edit the link (role, job title, primary, left on) and the person, remove the link.
 */
class CustomerContacts extends BaseController
{
    private ContactService $service;

    public function __construct()
    {
        $this->service = new ContactService();
    }

    /**
     * Form to add a contact (existing person or new one).
     *
     * @param int $customerId School id.
     *
     * @return string
     */
    public function new(int $customerId): string
    {
        return $this->form($customerId, []);
    }

    /**
     * Link the contact.
     *
     * @param int $customerId School id.
     *
     * @return RedirectResponse
     */
    public function create(int $customerId): RedirectResponse
    {
        $this->guardCustomer($customerId);
        $existing = (int) $this->request->getPost('contact_id');

        try {
            $this->service->addToCustomer(
                $customerId,
                $existing > 0 && $this->scope()->canContact($existing) ? $existing : null,
                $this->postFields(ContactService::PERSON_FIELDS),
                $this->postFields(ContactService::LINK_FIELDS),
            );
        } catch (ValidationFailed $e) {
            return $this->backWithErrors($e->errors);
        } catch (Throwable $e) {
            return $this->failed($e, 'Contact add');
        }

        return redirect()->to("customers/{$customerId}#contacts")->with('message', lang('Customers.contactAdded'));
    }

    /**
     * Edit form of a link and its person.
     *
     * @param int $customerId School id.
     * @param int $id         Link id.
     *
     * @return string
     */
    public function edit(int $customerId, int $id): string
    {
        return $this->form($customerId, $this->findLink($customerId, $id));
    }

    /**
     * Save a link and its person.
     *
     * @param int $customerId School id.
     * @param int $id         Link id.
     *
     * @return RedirectResponse
     */
    public function update(int $customerId, int $id): RedirectResponse
    {
        $this->findLink($customerId, $id);

        try {
            $this->service->updateLink($customerId, $id, $this->postFields(ContactService::LINK_FIELDS), $this->postFields(ContactService::PERSON_FIELDS));
        } catch (ValidationFailed $e) {
            return $this->backWithErrors($e->errors);
        } catch (Throwable $e) {
            return $this->failed($e, 'Contact save');
        }

        return redirect()->to("customers/{$customerId}#contacts")->with('message', lang('Customers.contactUpdated'));
    }

    /**
     * Remove a person from a school.
     *
     * @param int $customerId School id.
     * @param int $id         Link id.
     *
     * @return RedirectResponse
     */
    public function delete(int $customerId, int $id): RedirectResponse
    {
        $this->findLink($customerId, $id);

        try {
            $this->service->removeLink($customerId, $id);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Contact delete');
        }

        return redirect()->to("customers/{$customerId}#contacts")->with('message', lang('Customers.contactDeleted'));
    }

    /**
     * Render the form.
     *
     * @param int                  $customerId School id.
     * @param array<string, mixed> $link       Link with person fields (empty on create).
     *
     * @return string
     */
    private function form(int $customerId, array $link): string
    {
        $this->guardCustomer($customerId);

        return view('customers/contact_form', [
            'customer' => $this->findOr404(new CustomerModel(), $customerId),
            'contact'  => $link,
            'roles'    => (new ContactRoleModel())->options(),
            'others'   => $link !== [] ? (new CustomerContactModel())->forPerson((int) $link['contact_id']) : [],
        ]);
    }

    /**
     * Link of the school with the person's fields, or 404.
     *
     * @param int $customerId School id.
     * @param int $id         Link id.
     *
     * @return array<string, mixed>
     */
    private function findLink(int $customerId, int $id): array
    {
        $this->guardCustomer($customerId);
        $link = (new CustomerContactModel())->withPerson()->where('customer_contact_person.customer_id', $customerId)->where('customer_contact_person.id', $id)->first();

        if ($link === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        // Form field names of the person (cp_*).
        return $link + [
            'cp_first_name' => $link['ccp_first_name'],
            'cp_last_name'  => $link['ccp_last_name'],
            'cp_phone'      => $link['ccp_phone_number'],
            'cp_email'      => $link['ccp_email'],
        ];
    }
}
