<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AgreementModel;
use App\Models\ProposalModel;
use App\Services\AgreementService;
use App\Services\ProposalWorkflow;
use App\Services\ValidationFailed;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;
use Throwable;

/**
 * Surat Perintah Kerja (SPK) / agreement of an approved proposal (permission proposals.manage).
 */
class Agreements extends BaseController
{
    /**
     * Create / edit form, prefilled from the proposal, its contact person and the company profile.
     *
     * @param int $proposalId Proposal id.
     *
     * @return RedirectResponse|string
     */
    public function edit(int $proposalId): RedirectResponse|string
    {
        $this->guardProposal($proposalId);

        $proposal = $this->findOr404((new ProposalModel())->withDetails(), $proposalId);

        if ($proposal['status_code'] !== ProposalWorkflow::STORE_APPROVE) {
            return redirect()->to("proposals/{$proposalId}")->with('error', lang('Messages.spkOnlyApproved'));
        }

        $agreement = (new AgreementModel())->forProposal($proposalId);

        return view('agreements/form', [
            'proposal'  => $proposal,
            'agreement' => $agreement ?? (new AgreementService())->defaults($proposal),
            'isNew'     => $agreement === null,
        ]);
    }

    /**
     * Save (creates the SPK number on first save).
     *
     * @param int $proposalId Proposal id.
     *
     * @return RedirectResponse
     */
    public function save(int $proposalId): RedirectResponse
    {
        $this->guardProposal($proposalId);

        $this->findOr404(new ProposalModel(), $proposalId);

        try {
            (new AgreementService())->save($proposalId, $this->postFields(AgreementService::FIELDS), $this->userId());
        } catch (ValidationFailed $e) {
            return $this->backWithErrors($e->errors);
        } catch (RuntimeException $e) {
            return redirect()->to("proposals/{$proposalId}")->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Agreement save');
        }

        return redirect()->to("proposals/{$proposalId}")->with('message', lang('Messages.spkSaved'));
    }
}
