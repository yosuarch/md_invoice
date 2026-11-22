<?php

declare(strict_types=1);

namespace App\Models;

/**
 * client_proposal_item: proposal lines. Title, unit, type, price and tax rate are copied from the product
 * when the line is added; amounts are computed by App\Services\DocumentTotals.
 */
class ProposalItemModel extends BaseModel
{
    protected $table         = 'client_proposal_item';
    protected array $lowercase = ['item_title'];
    protected $allowedFields = [
        'proposal_id', 'product_id', 'variant_id', 'item_title', 'item_description', 'unit_code', 'item_type', 'cpi_quantity', 'unit_price',
        'discount_amount', 'tax_rate', 'tax_code', 'price_base', 'unit_price_incl', 'discount_incl', 'tax_amount', 'line_total',
    ];

    /**
     * Lines of a proposal in entry order.
     *
     * @param int $proposalId Proposal id.
     *
     * @return list<array<string, mixed>>
     */
    public function forProposal(int $proposalId): array
    {
        return $this->select('client_proposal_item.*, COALESCE(product_variant.pv_sku, product_list.pl_sku) AS pl_sku')
            ->join('product_list', 'product_list.id = client_proposal_item.product_id', 'left')
            ->join('product_variant', 'product_variant.id = client_proposal_item.variant_id', 'left')
            ->where('proposal_id', $proposalId)
            ->orderBy('client_proposal_item.id')
            ->findAll();
    }
}
