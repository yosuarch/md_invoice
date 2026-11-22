<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\CustomerModel;
use App\Models\DeliveryEventModel;
use App\Models\DeliveryNoteItemModel;
use App\Models\DeliveryNoteModel;
use App\Models\InvoiceModel;
use App\Models\ShipmentModel;
use App\Services\DeliveryService;
use App\Services\ValidationFailed;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;
use Throwable;

/**
 * Delivery notes (permission deliveries.view / deliveries.manage).
 */
class Deliveries extends BaseController
{
    private DeliveryNoteModel $model;
    private DeliveryService $service;

    public function __construct()
    {
        $this->model   = new DeliveryNoteModel();
        $this->service = new DeliveryService();
    }

    /**
     * List with search and status filter.
     *
     * @return string
     */
    public function index(): string
    {
        $filters = [
            'q'        => trim((string) $this->request->getGet('q')),
            'status'   => (string) $this->request->getGet('status'),
            'customer' => (string) (int) $this->request->getGet('customer') ?: '',
        ];

        return view('deliveries/index', [
            'notes'    => $this->scope()->invoices($this->model->search($filters))->paginate(20),
            'pager'    => $this->model->pager,
            'filters'  => $filters,
            'customer' => $filters['customer'] !== '' ? (new CustomerModel())->find((int) $filters['customer']) : null,
        ]);
    }

    /**
     * Delivery note detail with actions (mark delivered, edit, cancel).
     *
     * @param int $id Delivery note id.
     *
     * @return string
     */
    public function show(int $id): string
    {
        $this->guardDelivery($id);

        $note = $this->findOr404($this->model->withDetails(), $id);

        return view('deliveries/show', [
            'note'      => $note,
            'items'     => (new DeliveryNoteItemModel())->forNote($id),
            'events'    => (new DeliveryEventModel())->forNote($id),
            'flow'      => (new \App\Services\OrderFlow())->forInvoice((int) $note['invoice_id']),
            'canManage' => user_can('deliveries.manage'),
        ]);
    }

    /**
     * New delivery note for an invoice (goods lines with remaining quantities).
     *
     * @param int $invoiceId Invoice id.
     *
     * @return RedirectResponse|string
     */
    public function new(int $invoiceId): RedirectResponse|string
    {
        $this->guardInvoice($invoiceId);

        $invoice = $this->findOr404((new InvoiceModel())->withDetails(), $invoiceId);

        if (! in_array($invoice['status_code'], DeliveryService::DELIVERABLE, true)) {
            return redirect()->to("invoices/{$invoiceId}")->with('error', lang('Messages.deliveryOnlyIssuedShort'));
        }

        $lines = $this->service->deliverableLines($invoiceId);

        if (array_filter($lines, static fn (array $l): bool => bccomp((string) $l['remaining'], '0', 3) > 0) === []) {
            return redirect()->to("invoices/{$invoiceId}")->with('error', lang('Messages.allShipped'));
        }

        return view('deliveries/form', [
            'invoice'   => $invoice,
            'note'      => [
                'ship_date'        => date('Y-m-d'),
                'shipment_id'      => $invoice['shipment_id'],
                'shipping_address' => $this->service->defaultAddress((int) $invoice['customer_id']),
            ],
            'lines'     => $lines,
            'shipments' => $this->shipmentOptions(),
        ]);
    }

    /**
     * Store a delivery note.
     *
     * @param int $invoiceId Invoice id.
     *
     * @return RedirectResponse
     */
    public function create(int $invoiceId): RedirectResponse
    {
        $this->guardInvoice($invoiceId);

        $this->findOr404(new InvoiceModel(), $invoiceId);
        $lines = $this->request->getPost('lines');

        try {
            $id = $this->service->create($invoiceId, $this->postFields(DeliveryService::FIELDS), is_array($lines) ? $lines : [], $this->userId());
        } catch (ValidationFailed $e) {
            return $this->backWithErrors($e->errors);
        } catch (RuntimeException $e) {
            return redirect()->to("invoices/{$invoiceId}")->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Delivery note create');
        }

        return redirect()->to("deliveries/{$id}")->with('message', lang('Messages.deliveryCreated', [$this->model->find($id)['delivery_number']]));
    }

    /**
     * Edit the header of an open delivery note.
     *
     * @param int $id Delivery note id.
     *
     * @return RedirectResponse|string
     */
    public function edit(int $id): RedirectResponse|string
    {
        $this->guardDelivery($id);

        $note = $this->findOr404($this->model->withDetails(), $id);

        if (! DeliveryNoteModel::isOpen($note)) {
            return redirect()->to("deliveries/{$id}")->with('error', lang('Messages.deliveryEditOpenOnly'));
        }

        return view('deliveries/form', [
            'invoice'   => $this->findOr404((new InvoiceModel())->withDetails(), $note['invoice_id']),
            'note'      => $note,
            'lines'     => (new DeliveryNoteItemModel())->forNote($id),
            'shipments' => $this->shipmentOptions(),
        ]);
    }

    /**
     * Save the header of an open delivery note.
     *
     * @param int $id Delivery note id.
     *
     * @return RedirectResponse
     */
    public function update(int $id): RedirectResponse
    {
        $this->guardDelivery($id);

        $this->findOr404($this->model, $id);

        try {
            $this->service->update($id, $this->postFields(DeliveryService::FIELDS));
        } catch (ValidationFailed $e) {
            return $this->backWithErrors($e->errors);
        } catch (RuntimeException $e) {
            return redirect()->to("deliveries/{$id}")->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Delivery note update');
        }

        return redirect()->to("deliveries/{$id}")->with('message', lang('Messages.deliverySaved'));
    }

    /**
     * Confirm delivery (date + receiver).
     *
     * @param int $id Delivery note id.
     *
     * @return RedirectResponse
     */
    public function delivered(int $id): RedirectResponse
    {
        $this->guardDelivery($id);

        $this->findOr404($this->model, $id);

        try {
            $this->service->markDelivered($id, (string) $this->request->getPost('delivered_date'), (string) $this->request->getPost('received_by_name'), $this->userId());
        } catch (ValidationFailed $e) {
            return redirect()->to("deliveries/{$id}")->with('error', implode(' ', $e->errors));
        } catch (RuntimeException $e) {
            return redirect()->to("deliveries/{$id}")->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Delivery confirmation');
        }

        return redirect()->to("deliveries/{$id}")->with('message', lang('Messages.deliveryConfirmed'));
    }

    /**
     * Goods left the warehouse (status SHIPPED), with optional courier / tracking number.
     *
     * @param int $id Delivery note id.
     *
     * @return RedirectResponse
     */
    public function shipped(int $id): RedirectResponse
    {
        $this->guardDelivery($id);

        return $this->runAction($id, lang('Messages.markedShipped'), fn () => $this->service->markShipped(
            $id,
            $this->postFields(['shipped_date', 'courier_name', 'tracking_number', 'location', 'note']),
            $this->userId(),
        ));
    }

    /**
     * Add a tracking update to the timeline.
     *
     * @param int $id Delivery note id.
     *
     * @return RedirectResponse
     */
    public function track(int $id): RedirectResponse
    {
        $this->guardDelivery($id);

        return $this->runAction($id, lang('Messages.trackingAdded'), fn () => $this->service->addUpdate(
            $id,
            $this->postFields(['event_date', 'location', 'note']),
            $this->userId(),
        ));
    }

    /**
     * Run a modal action and redirect back to the delivery note with a message.
     *
     * @param int      $id      Delivery note id.
     * @param string   $success Success message.
     * @param callable $action  The service call.
     *
     * @return RedirectResponse
     */
    private function runAction(int $id, string $success, callable $action): RedirectResponse
    {
        $this->findOr404($this->model, $id);

        try {
            $action();
        } catch (ValidationFailed $e) {
            return redirect()->to("deliveries/{$id}")->with('error', implode(' ', $e->errors));
        } catch (RuntimeException $e) {
            return redirect()->to("deliveries/{$id}")->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Delivery note action');
        }

        return redirect()->to("deliveries/{$id}")->with('message', $success);
    }

    /**
     * Cancel an open delivery note (reason required; the record is kept).
     *
     * @param int $id Delivery note id.
     *
     * @return RedirectResponse
     */
    public function cancel(int $id): RedirectResponse
    {
        $this->guardDelivery($id);

        $this->findOr404($this->model, $id);

        try {
            $this->service->cancel($id, (string) $this->request->getPost('cancel_reason'), $this->userId());
        } catch (ValidationFailed $e) {
            return redirect()->to("deliveries/{$id}")->with('error', implode(' ', $e->errors));
        } catch (RuntimeException $e) {
            return redirect()->to("deliveries/{$id}")->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Delivery note cancel');
        }

        return redirect()->to("deliveries/{$id}")->with('message', lang('Messages.deliveryCancelled'));
    }

    /**
     * Shipment options with their codes (the form shows the courier fields for THIRD_PARTY).
     *
     * @return list<array<string, mixed>>
     */
    private function shipmentOptions(): array
    {
        return (new ShipmentModel())->orderBy('id')->findAll();
    }
}
