<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Model;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    // protected $session;

    /**
     * @param CLIRequest|IncomingRequest $request
     *
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        // $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        // $this->session = service('session');
    }

    /**
     * Trimmed POST values for the given fields (missing fields become '').
     *
     * @param list<string> $fields Field names.
     *
     * @return array<string, mixed>
     */
    protected function postFields(array $fields): array
    {
        $data = [];

        foreach ($fields as $field) {
            $value        = $this->request->getPost($field);
            $data[$field] = is_string($value) ? trim($value) : ($value ?? '');
        }

        return $data;
    }

    /**
     * Redirect back to the form with input and field errors.
     *
     * @param array<string, string> $errors field => message.
     *
     * @return RedirectResponse
     */
    protected function backWithErrors(array $errors): RedirectResponse
    {
        return redirect()->back()->withInput()->with('errors', $errors);
    }

    /**
     * Find a row or show the 404 page.
     *
     * @param Model           $model Model to search.
     * @param int|string|null $id    Primary key.
     *
     * @return array<string, mixed>
     *
     * @throws PageNotFoundException
     */
    protected function findOr404(Model $model, int|string|null $id): array
    {
        $row = $id === null ? null : $model->find($id);

        if (! is_array($row)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $row;
    }

    /**
     * Record-level access of the logged-in user (created once per request).
     *
     * @return \App\Services\AccessScope
     */
    protected function scope(): \App\Services\AccessScope
    {
        static $scope = null;

        return $scope ??= new \App\Services\AccessScope();
    }

    /**
     * 404 unless the proposal is visible to the user (see AccessScope).
     *
     * @param int $proposalId Proposal id.
     *
     * @return void
     *
     * @throws PageNotFoundException
     */
    protected function guardProposal(int $proposalId): void
    {
        if (! $this->scope()->canProposal($proposalId)) {
            throw PageNotFoundException::forPageNotFound();
        }
    }

    /**
     * 404 unless the invoice is visible to the user (see AccessScope).
     *
     * @param int $invoiceId Invoice id.
     *
     * @return void
     *
     * @throws PageNotFoundException
     */
    protected function guardInvoice(int $invoiceId): void
    {
        if (! $this->scope()->canInvoice($invoiceId)) {
            throw PageNotFoundException::forPageNotFound();
        }
    }

    /**
     * 404 unless the delivery note (via its invoice) is visible to the user.
     *
     * @param int $deliveryId Delivery note id.
     *
     * @return void
     *
     * @throws PageNotFoundException
     */
    protected function guardDelivery(int $deliveryId): void
    {
        $this->guardInvoice((int) ((new \App\Models\DeliveryNoteModel())->find($deliveryId)['invoice_id'] ?? 0));
    }

    /**
     * 404 unless the customer is visible to the user (see AccessScope).
     *
     * @param int $customerId Customer id.
     *
     * @return void
     *
     * @throws PageNotFoundException
     */
    protected function guardCustomer(int $customerId): void
    {
        if (! $this->scope()->canCustomer($customerId)) {
            throw PageNotFoundException::forPageNotFound();
        }
    }

    /**
     * Id of the logged-in user.
     *
     * @return int
     */
    protected function userId(): int
    {
        return (int) auth()->id();
    }

    /**
     * Log an unexpected error and return a generic flash message redirect.
     *
     * @param Throwable $e       The error.
     * @param string    $context What was being done, for the log.
     *
     * @return RedirectResponse
     */
    protected function failed(Throwable $e, string $context): RedirectResponse
    {
        log_message('error', '{context} failed: {message} in {file}:{line}', [
            'context' => $context,
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
        ]);

        return redirect()->back()->withInput()->with('error', lang('App.somethingWrong'));
    }
}
