<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Pdf;
use App\Models\ProposalModel;
use App\Services\OrderBundle;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Combined order report of a finished order (App\Services\OrderBundle): pick the documents and attachments,
 * then get one PDF. Route filter: proposals.view; record access via guardProposal().
 */
class OrderBundles extends BaseController
{
    /**
     * Picker page.
     *
     * @param int $id Proposal id.
     *
     * @return string|RedirectResponse
     */
    public function form(int $id): string|RedirectResponse
    {
        $this->guardProposal($id);
        $proposal = $this->findOr404(new ProposalModel(), $id);
        $options  = (new OrderBundle($this->scope()))->options($id);

        if (! $options['complete']) {
            return redirect()->to(site_url('proposals/' . $id))->with('error', lang('Bundle.notComplete'));
        }

        return view('order_bundles/form', ['proposal' => $proposal, 'items' => $options['items']]);
    }

    /**
     * Build and send the combined PDF (GET: read-only, so it can open in a new tab like the other PDFs).
     *
     * @param int $id Proposal id.
     *
     * @return ResponseInterface
     */
    public function build(int $id): ResponseInterface
    {
        $this->guardProposal($id);

        $keys = $this->request->getGet('parts');

        try {
            $bundle = (new OrderBundle($this->scope()))->build($id, is_array($keys) ? array_values($keys) : []);
        } catch (RuntimeException $e) {
            return redirect()->to(site_url("proposals/{$id}/bundle"))->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Order bundle');
        }

        return Pdf::respond($this->response, $bundle['bytes'], $bundle['filename'], (string) $this->request->getGet('download') === '1');
    }
}
