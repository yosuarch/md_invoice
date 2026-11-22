<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\CustomerModel;
use App\Models\DeliveryNoteModel;
use App\Models\InvoiceModel;
use App\Models\ProposalModel;
use Throwable;

/**
 * Quick search in the top bar: proposals, invoices, delivery notes and customers matching a number or name,
 * limited to what the user may see (AccessScope + module permissions).
 * htmx requests from the search box get only the result list; a normal request (Enter) gets a full page.
 */
class Search extends BaseController
{
    /** Results per group. */
    private const LIMIT = 5;

    /**
     * GET search?q=
     *
     * @return string
     */
    public function index(): string
    {
        $q       = mb_substr(trim((string) $this->request->getGet('q')), 0, 100);
        $results = mb_strlen($q) >= 2 ? $this->find($q) : [];
        $partial = $this->request->hasHeader('HX-Request') && ! $this->request->hasHeader('HX-Boosted');

        return view($partial ? 'search/results' : 'search/index', ['q' => $q, 'results' => $results]);
    }

    /**
     * @param string $q Search text (min. 2 characters).
     *
     * @return list<array{label: string, icon: string, url: string, items: list<array{title: string, detail: string, url: string}>}>
     */
    private function find(string $q): array
    {
        $user   = auth()->user();
        $scope  = $this->scope();
        $groups = [];

        try {
            if ($user->can('proposals.view')) {
                $rows = $scope->proposals((new ProposalModel())->search(['q' => $q]))->findAll(self::LIMIT);
                $groups[] = $this->group(lang('Nav.proposals'), 'file-earmark-text', 'proposals', $q, array_map(static fn (array $r): array => [
                    'title'  => $r['cpl_id'] . ' · ' . $r['cpl_proposal_title'],
                    'detail' => $r['customer_name'] . ' · ' . format_idr($r['grand_total'], 0),
                    'url'    => site_url('proposals/' . $r['id']),
                ], $rows));
            }

            if ($user->can('invoices.view')) {
                $rows = $scope->invoices((new InvoiceModel())->search(['q' => $q]))->findAll(self::LIMIT);
                $groups[] = $this->group(lang('Nav.invoices'), 'receipt', 'invoices', $q, array_map(static fn (array $r): array => [
                    'title'  => InvoiceModel::displayNumber($r),
                    'detail' => $r['customer_name'] . ' · ' . format_idr($r['grand_total'], 0),
                    'url'    => site_url('invoices/' . $r['id']),
                ], $rows));
            }

            if ($user->can('deliveries.view')) {
                $rows = $scope->invoices((new DeliveryNoteModel())->search(['q' => $q]))->findAll(self::LIMIT);
                $groups[] = $this->group(lang('Nav.deliveries'), 'truck', 'deliveries', $q, array_map(static fn (array $r): array => [
                    'title'  => $r['delivery_number'],
                    'detail' => ($r['customer_name'] ?? '') . ' · ' . ($r['invoice_number'] ?? ''),
                    'url'    => site_url('deliveries/' . $r['id']),
                ], $rows));
            }

            if ($user->can('customers.view')) {
                $rows = $scope->customers((new CustomerModel())->search(['q' => $q]))->findAll(self::LIMIT);
                $groups[] = $this->group(lang('Nav.customers'), 'building', 'customers', $q, array_map(static fn (array $r): array => [
                    'title'  => $r['cl_organization_name'],
                    'detail' => implode(' · ', array_filter([$r['school_level_name'] ?? null, $r['npsn'] ? 'NPSN ' . $r['npsn'] : null])),
                    'url'    => site_url('customers/' . $r['id']),
                ], $rows));
            }

            if ($user->can('customers.view')) {
                $rows = $scope->contacts((new \App\Models\ContactPersonModel())->search($q))->findAll(self::LIMIT);
                $groups[] = $this->group(lang('Nav.contacts'), 'person-lines-fill', 'contacts', $q, array_map(static fn (array $r): array => [
                    'title'  => \App\Models\ContactPersonModel::displayName($r),
                    'detail' => implode(' · ', array_filter([$r['cp_phone'], implode(', ', array_map(static fn (array $s): string => $s['name'], \App\Models\ContactPersonModel::parseSchools($r['schools'])))])),
                    'url'    => site_url('contacts/' . $r['id']),
                ], $rows));
            }
        } catch (Throwable $e) {
            log_message('error', 'Quick search failed: {message}', ['message' => $e->getMessage()]);
        }

        return array_values(array_filter($groups, static fn (array $g): bool => $g['items'] !== []));
    }

    /**
     * @param list<array{title: string, detail: string, url: string}> $items
     *
     * @return array{label: string, icon: string, url: string, items: list<array{title: string, detail: string, url: string}>}
     */
    private function group(string $label, string $icon, string $module, string $q, array $items): array
    {
        return ['label' => $label, 'icon' => $icon, 'url' => site_url($module) . '?q=' . rawurlencode($q), 'items' => $items];
    }
}
