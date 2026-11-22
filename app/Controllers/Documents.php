<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Pdf;
use App\Services\PrintDocuments;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * PDF documents (shown inline; ?download=1 downloads). Route filters check the module's view permission.
 */
class Documents extends BaseController
{
    /**
     * Surat Penawaran Harga.
     *
     * @param int $id Proposal id.
     *
     * @return ResponseInterface
     */
    public function proposal(int $id): ResponseInterface
    {
        $this->guardProposal($id);

        return $this->render(static fn (PrintDocuments $p): array => $p->proposal($id));
    }

    /**
     * Surat Perintah Kerja (agreement) of a proposal.
     *
     * @param int $id Proposal id.
     *
     * @return ResponseInterface
     */
    public function agreement(int $id): ResponseInterface
    {
        $this->guardProposal($id);

        return $this->render(static fn (PrintDocuments $p): array => $p->agreement($id));
    }

    /**
     * Invoice.
     *
     * @param int $id Invoice id.
     *
     * @return ResponseInterface
     */
    public function invoice(int $id): ResponseInterface
    {
        $this->guardInvoice($id);

        return $this->render(static fn (PrintDocuments $p): array => $p->invoice($id));
    }

    /**
     * Kwitansi of a payment.
     *
     * @param int $id Payment id.
     *
     * @return ResponseInterface
     */
    public function receipt(int $id): ResponseInterface
    {
        $this->guardInvoice((int) ((new \App\Models\PaymentModel())->find($id)['invoice_id'] ?? 0));

        return $this->render(static fn (PrintDocuments $p): array => $p->receipt($id));
    }

    /**
     * Surat Jalan.
     *
     * @param int $id Delivery note id.
     *
     * @return ResponseInterface
     */
    public function deliveryNote(int $id): ResponseInterface
    {
        $this->guardInvoice((int) ((new \App\Models\DeliveryNoteModel())->find($id)['invoice_id'] ?? 0));

        return $this->render(static fn (PrintDocuments $p): array => $p->deliveryNote($id));
    }

    /**
     * Berita Acara Serah Terima of a delivery note.
     *
     * @param int $id Delivery note id.
     *
     * @return ResponseInterface
     */
    public function handover(int $id): ResponseInterface
    {
        $this->guardInvoice((int) ((new \App\Models\DeliveryNoteModel())->find($id)['invoice_id'] ?? 0));

        return $this->render(static fn (PrintDocuments $p): array => $p->handover($id));
    }

    /**
     * Build and send a document.
     *
     * @param callable(PrintDocuments): array{view: string, data: array<string, mixed>, filename: string, footer: string} $build
     *
     * @return ResponseInterface
     */
    private function render(callable $build): ResponseInterface
    {
        try {
            $doc   = $build(new PrintDocuments());
            $bytes = Pdf::render($doc['view'], $doc['data'], $doc['footer']);
        } catch (PageNotFoundException $e) {
            throw $e;
        } catch (Throwable $e) {
            log_message('error', 'PDF failed: {message} in {file}:{line}', ['message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);

            return $this->response->setStatusCode(500)->setBody(lang('Messages.pdfFailed'));
        }

        return Pdf::respond($this->response, $bytes, $doc['filename'], (string) $this->request->getGet('download') === '1');
    }
}
