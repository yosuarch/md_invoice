<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityModel;
use App\Models\ContactPersonModel;
use App\Models\CustomerContactModel;
use App\Models\InvoiceModel;
use App\Models\ProposalModel;

/**
 * Data of the "Activities & follow-ups" panel (components/activity_panel) on the school, proposal and invoice pages:
 * the timeline plus the options of the log / plan forms (people of the school, its proposals and invoices, staff).
 */
final class ActivityPanel
{
    /**
     * Panel of a school (all its activities).
     *
     * @param int         $customerId School id.
     * @param AccessScope $scope      Current user's scope.
     *
     * @return array<string, mixed>
     */
    public static function forCustomer(int $customerId, AccessScope $scope): array
    {
        return self::build($customerId, $scope, [], "customers/{$customerId}#activities");
    }

    /**
     * Panel of a proposal (activities linked to it; new ones are linked too).
     *
     * @param array<string, mixed> $proposal Proposal row.
     * @param AccessScope          $scope    Current user's scope.
     *
     * @return array<string, mixed>
     */
    public static function forProposal(array $proposal, AccessScope $scope): array
    {
        return self::build((int) $proposal['customer_id'], $scope, [
            'proposal_id' => (int) $proposal['id'],
            'contact_id'  => self::personOfLink($proposal['contact_person_id'] ?? null),
        ], 'proposals/' . $proposal['id'] . '#activities');
    }

    /**
     * Panel of an invoice (activities linked to it, e.g. payment reminders).
     *
     * @param array<string, mixed> $invoice Invoice row.
     * @param AccessScope          $scope   Current user's scope.
     *
     * @return array<string, mixed>
     */
    public static function forInvoice(array $invoice, AccessScope $scope): array
    {
        return self::build((int) $invoice['customer_id'], $scope, [
            'invoice_id'  => (int) $invoice['id'],
            'proposal_id' => $invoice['proposal_id'] ? (int) $invoice['proposal_id'] : null,
        ], 'invoices/' . $invoice['id'] . '#activities');
    }

    /**
     * Build the panel.
     *
     * @param int                  $customerId School id.
     * @param AccessScope          $scope      Scope.
     * @param array<string, mixed> $preset     Preselected links (proposal_id, invoice_id, contact_id); also filters the timeline.
     * @param string               $returnTo   Path to come back to after saving.
     *
     * @return array<string, mixed>
     */
    private static function build(int $customerId, AccessScope $scope, array $preset, string $returnTo): array
    {
        $query = (new ActivityModel())->withDetails()->where('crm_activity.customer_id', $customerId);

        if (! empty($preset['invoice_id'])) {
            $query->where('crm_activity.invoice_id', $preset['invoice_id']);
        } elseif (! empty($preset['proposal_id'])) {
            $query->where('crm_activity.proposal_id', $preset['proposal_id']);
        }

        $people = [];

        foreach ((new CustomerContactModel())->forCustomer($customerId, true) as $c) {
            $role = CustomerContactModel::roleLabel($c);
            $people[(int) $c['contact_id']] = ContactPersonModel::displayName($c) . ($role !== '' ? ' (' . $role . ')' : '');
        }

        $proposals = [];

        if (user_can('proposals.view')) {
            foreach ($scope->proposals((new ProposalModel())->search(['customer' => (string) $customerId]))->findAll(30) as $p) {
                $proposals[(int) $p['id']] = $p['cpl_id'] . ' · ' . up($p['cpl_proposal_title']);
            }
        }

        $invoices = [];

        if (user_can('invoices.view')) {
            foreach ($scope->invoices((new InvoiceModel())->search(['customer' => (string) $customerId]))->findAll(30) as $i) {
                $invoices[(int) $i['id']] = InvoiceModel::displayNumber($i);
            }
        }

        return [
            'customerId' => $customerId,
            'activities' => $query->timelineOrder()->findAll(60),
            'people'     => $people,
            'proposals'  => $proposals,
            'invoices'   => $invoices,
            'users'      => UserService::activeOptions(),
            'preset'     => $preset,
            'returnTo'   => $returnTo,
        ];
    }

    /**
     * Person id of a school link (contact_person_id on proposals points to the link).
     *
     * @param int|string|null $linkId Link id.
     *
     * @return int|null
     */
    private static function personOfLink(int|string|null $linkId): ?int
    {
        if (! $linkId) {
            return null;
        }

        $link = (new CustomerContactModel())->withDeleted()->find((int) $linkId);

        return $link !== null ? (int) $link['contact_id'] : null;
    }
}
