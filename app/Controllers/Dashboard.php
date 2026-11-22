<?php

namespace App\Controllers;

use App\Models\DeliveryNoteModel;
use App\Models\InvoiceModel;
use App\Models\PaymentModel;
use App\Models\ProposalModel;
use Throwable;

/**
 * Staff dashboard (landing page after login): open proposals, unpaid and past-due invoices,
 * cash received this month, and the unpaid invoices that are due first.
 */
class Dashboard extends BaseController
{
    /**
     * Show summary cards and invoices due soon (each part only with the matching permission).
     *
     * @return string Rendered view.
     */
    public function index(): string
    {
        try {
            $invoices = new InvoiceModel();
            $data     = [
                'stats'      => $this->getStats($invoices),
                'dueSoon'    => user_can('invoices.view') ? $invoices->dueSoon(8, $this->scopeSql()) : null,
                'pipeline'   => $this->getPipeline(),
                'followUps'  => user_can('crm.view') ? $this->scope()->activities((new \App\Models\ActivityModel())->withDetails())
                    ->where('crm_activity.assigned_to', $this->userId())->where('crm_activity.done_at', null)
                    ->where('crm_activity.due_on <=', date('Y-m-d', strtotime('+7 days')))->orderBy('crm_activity.due_on')->findAll(8) : null,
            ];
        } catch (Throwable $e) {
            log_message('error', 'Dashboard failed: {message}', ['message' => $e->getMessage()]);

            $data = [
                'stats'      => [],
                'dueSoon'    => null,
                'pipeline'   => [],
                'followUps'  => null,
                'error'      => lang('Messages.dashboardFailed'),
            ];
        }

        return view('dashboard/index', $data);
    }

    /**
     * Order pipeline (work queues across modules): proposals to invoice, invoices with goods to ship,
     * deliveries on the way, refunds due. Only the queues the user may open.
     *
     * @return list<array{label: string, value: int, icon: string, color: string, note: string, url: string}>
     */
    private function getPipeline(): array
    {
        $cards = [];

        if (user_can('proposals.view')) {
            $cards[] = ['label' => lang('Dashboard.toInvoice'), 'value' => $this->scope()->proposals((new ProposalModel())->search(['status' => 'to_invoice']))->countAllResults(), 'icon' => 'check2-circle', 'color' => 'primary', 'note' => lang('Dashboard.toInvoiceNote'), 'url' => site_url('proposals?status=to_invoice')];
        }

        if (user_can('invoices.view')) {
            $cards[] = ['label' => lang('Dashboard.toShip'), 'value' => $this->scope()->invoices((new InvoiceModel())->search(['status' => 'to_ship']))->countAllResults(), 'icon' => 'box-seam', 'color' => 'info', 'note' => lang('Dashboard.toShipNote'), 'url' => site_url('invoices?status=to_ship')];
        }

        if (user_can('deliveries.view')) {
            $cards[] = ['label' => lang('Dashboard.deliveriesOpen'), 'value' => $this->scope()->invoices((new DeliveryNoteModel())->search(['status' => 'open']))->countAllResults(), 'icon' => 'truck', 'color' => 'primary', 'note' => lang('Dashboard.deliveriesOpenNote'), 'url' => site_url('deliveries?status=open')];
        }

        if (user_can('invoices.view')) {
            $cards[] = ['label' => lang('Dashboard.refundsDue'), 'value' => $this->scope()->invoices((new InvoiceModel())->search(['status' => 'refund_due']))->countAllResults(), 'icon' => 'arrow-counterclockwise', 'color' => 'warning', 'note' => lang('Dashboard.refundsDueNote'), 'url' => site_url('invoices?status=refund_due')];
        }

        return $cards;
    }

    /**
     * Invoice scope condition for raw queries, or null when the user sees everything.
     *
     * @return string|null
     */
    private function scopeSql(): ?string
    {
        return $this->scope()->seesAll() ? null : $this->scope()->invoiceSql();
    }

    /**
     * Build the stat cards the user may see.
     *
     * @param InvoiceModel $invoices Invoice model.
     *
     * @return list<array{label: string, value: int|string, icon: string, color: string, note?: string, url?: string}>
     */
    private function getStats(InvoiceModel $invoices): array
    {
        $stats = [];

        if (user_can('proposals.view')) {
            $stats[] = [
                'label' => lang('Dashboard.openProposals'),
                'value' => $this->scope()->proposals((new ProposalModel())->search(['status' => 'open']))->countAllResults(),
                'icon'  => 'file-earmark-text',
                'color' => 'primary',
                'url'   => site_url('proposals?status=open'),
            ];
        }

        if (user_can('invoices.view')) {
            $unpaid  = $invoices->unpaidSummary(false, $this->scopeSql());
            $pastDue = $invoices->unpaidSummary(true, $this->scopeSql());

            $stats[] = [
                'label' => lang('Dashboard.unpaidInvoices'),
                'value' => $unpaid['count'],
                'note'  => lang('Dashboard.amountOpen', [format_idr($unpaid['balance'], 0)]),
                'icon'  => 'receipt',
                'color' => 'warning',
                'url'   => site_url('invoices?status=unpaid'),
            ];
            $stats[] = [
                'label' => lang('Dashboard.pastDueInvoices'),
                'value' => $pastDue['count'],
                'note'  => lang('Dashboard.amountOpen', [format_idr($pastDue['balance'], 0)]),
                'icon'  => 'exclamation-octagon',
                'color' => 'danger',
                'url'   => site_url('invoices?status=past_due'),
            ];
        }

        if (user_can('payments.view')) {
            $month   = date('Y-m');
            $stats[] = [
                'label' => lang('Dashboard.receivedThisMonth'),
                'value' => format_idr((new PaymentModel())->receivedInMonth($month), 0),
                'note'  => lang('Dashboard.receivedNote'),
                'icon'  => 'cash-coin',
                'color' => 'success',
                'url'   => site_url('payments?month=' . $month),
            ];
        }

        return $stats;
    }
}
