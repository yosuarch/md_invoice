<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Proposal (quotation) status workflow, by client_proposal_status.code:
 *
 *   DRAFT ──> PROPOSE ──> UNDER_REVIEW ──> STORE_APPROVE (admin)
 *               │  ^──────────┘ (revised proposal sent)
 *               └──────────────────────> STORE_APPROVE (admin)
 *   any open status ──> CANCEL_BY_CLIENT / CANCEL_BY_STORE ──> DRAFT (reopen)
 *   STORE_APPROVE ──> UNDER_REVIEW (admin, revoke; only while no invoice uses it)
 *
 * Items and prices can be edited in DRAFT and UNDER_REVIEW (negotiation) only.
 */
final class ProposalWorkflow
{
    public const DRAFT            = 'DRAFT';
    public const PROPOSE          = 'PROPOSE';
    public const UNDER_REVIEW     = 'UNDER_REVIEW';
    public const STORE_APPROVE    = 'STORE_APPROVE';
    public const CANCEL_BY_CLIENT = 'CANCEL_BY_CLIENT';
    public const CANCEL_BY_STORE  = 'CANCEL_BY_STORE';

    /**
     * Display labels per status code.
     *
     * @var array<string, string>
     */
    public const LABELS = [
        self::DRAFT            => 'Draft',
        self::PROPOSE          => 'Proposed',
        self::UNDER_REVIEW     => 'Under review',
        self::STORE_APPROVE    => 'Approved',
        self::CANCEL_BY_CLIENT => 'Cancelled by client',
        self::CANCEL_BY_STORE  => 'Cancelled by store',
    ];

    /**
     * Statuses in which header and items can be edited.
     *
     * @var list<string>
     */
    public const EDITABLE = [self::DRAFT, self::UNDER_REVIEW];

    /**
     * Statuses that still count as "open" (not approved or cancelled).
     *
     * @var list<string>
     */
    public const OPEN = [self::DRAFT, self::PROPOSE, self::UNDER_REVIEW];

    /**
     * Allowed transitions: from => [to => action definition].
     * permission: Shield permission needed; note: 'required' | 'optional'; needsItems: at least one item line.
     *
     * @var array<string, array<string, array{label: string, icon: string, class: string, permission: string, note: string, help: string, needsItems?: bool}>>
     */
    private const TRANSITIONS = [
        self::DRAFT => [
            self::PROPOSE         => ['label' => 'Mark as sent', 'icon' => 'send', 'class' => 'btn-primary', 'permission' => 'proposals.manage', 'note' => 'optional', 'help' => 'The proposal was sent to the customer. It cannot be edited until negotiation starts.', 'needsItems' => true],
            self::CANCEL_BY_STORE => ['label' => 'Cancel', 'icon' => 'x-circle', 'class' => 'btn-outline-danger', 'permission' => 'proposals.manage', 'note' => 'required', 'help' => 'Cancel this draft. It can be reopened later.'],
        ],
        self::PROPOSE => [
            self::UNDER_REVIEW     => ['label' => 'Customer requests changes', 'icon' => 'chat-left-text', 'class' => 'btn-outline-primary', 'permission' => 'proposals.manage', 'note' => 'required', 'noteLabel' => 'What did the customer ask for?', 'help' => 'Record the customer\'s request (e.g. lower price, other quantity). The proposal opens for editing; send the revised version when it is ready.'],
            self::STORE_APPROVE    => ['label' => 'Approve', 'icon' => 'check2-circle', 'class' => 'btn-success', 'permission' => 'proposals.approve', 'note' => 'optional', 'help' => 'Approve the proposal. It is locked and can be turned into an invoice.', 'needsItems' => true],
            self::CANCEL_BY_CLIENT => ['label' => 'Rejected by client', 'icon' => 'person-x', 'class' => 'btn-outline-danger', 'permission' => 'proposals.manage', 'note' => 'required', 'help' => 'The customer declined or cancelled the proposal.'],
            self::CANCEL_BY_STORE  => ['label' => 'Cancel', 'icon' => 'x-circle', 'class' => 'btn-outline-danger', 'permission' => 'proposals.manage', 'note' => 'required', 'help' => 'We withdraw the proposal.'],
        ],
        self::UNDER_REVIEW => [
            self::PROPOSE          => ['label' => 'Send revision', 'icon' => 'send', 'class' => 'btn-primary', 'permission' => 'proposals.manage', 'note' => 'optional', 'noteLabel' => 'What changed? (optional)', 'help' => 'The revised proposal was sent to the customer. It is saved as a new revision and locked again.', 'needsItems' => true],
            self::STORE_APPROVE    => ['label' => 'Approve', 'icon' => 'check2-circle', 'class' => 'btn-success', 'permission' => 'proposals.approve', 'note' => 'optional', 'help' => 'Approve the proposal. It is locked and can be turned into an invoice.', 'needsItems' => true],
            self::CANCEL_BY_CLIENT => ['label' => 'Rejected by client', 'icon' => 'person-x', 'class' => 'btn-outline-danger', 'permission' => 'proposals.manage', 'note' => 'required', 'help' => 'The customer declined or cancelled the proposal.'],
            self::CANCEL_BY_STORE  => ['label' => 'Cancel', 'icon' => 'x-circle', 'class' => 'btn-outline-danger', 'permission' => 'proposals.manage', 'note' => 'required', 'help' => 'We withdraw the proposal.'],
        ],
        self::STORE_APPROVE => [
            self::UNDER_REVIEW => ['label' => 'Revoke approval', 'icon' => 'arrow-counterclockwise', 'class' => 'btn-outline-secondary', 'permission' => 'proposals.approve', 'note' => 'required', 'help' => 'Back to negotiation. Only possible while no invoice was created from this proposal.'],
        ],
        self::CANCEL_BY_CLIENT => [
            self::DRAFT => ['label' => 'Reopen as draft', 'icon' => 'arrow-counterclockwise', 'class' => 'btn-outline-primary', 'permission' => 'proposals.manage', 'note' => 'optional', 'help' => 'Continue working on this proposal.'],
        ],
        self::CANCEL_BY_STORE => [
            self::DRAFT => ['label' => 'Reopen as draft', 'icon' => 'arrow-counterclockwise', 'class' => 'btn-outline-primary', 'permission' => 'proposals.manage', 'note' => 'optional', 'help' => 'Continue working on this proposal.'],
        ],
    ];

    /**
     * Label for a status code.
     *
     * @param string|null $code Status code.
     *
     * @return string
     */
    public static function label(?string $code): string
    {
        return code_label('proposalStatus', $code, self::LABELS[$code ?? ''] ?? (string) $code);
    }

    /**
     * Whether items/header may be edited in this status.
     *
     * @param string $code Status code.
     *
     * @return bool
     */
    public static function isEditable(string $code): bool
    {
        return in_array($code, self::EDITABLE, true);
    }

    /**
     * Message shown when someone tries to edit a locked proposal.
     *
     * @param string $code Current status code.
     *
     * @return string
     */
    public static function lockedMessage(string $code): string
    {
        return $code === self::PROPOSE
            ? lang('Proposals.lockedSent', [self::localize(self::PROPOSE, self::UNDER_REVIEW, self::TRANSITIONS[self::PROPOSE][self::UNDER_REVIEW])['label']])
            : lang('Proposals.lockedStatus', [self::label($code)]);
    }

    /**
     * Transition definition, or null if not allowed.
     *
     * @param string $from Current status code.
     * @param string $to   Target status code.
     *
     * @return array{label: string, icon: string, class: string, permission: string, note: string, help: string, needsItems?: bool}|null
     */
    public static function transition(string $from, string $to): ?array
    {
        return isset(self::TRANSITIONS[$from][$to]) ? self::localize($from, $to, self::TRANSITIONS[$from][$to]) : null;
    }

    /**
     * Transitions from $from that pass the permission check.
     *
     * @param string                $from Current status code.
     * @param callable(string):bool $can  Permission checker, e.g. fn ($p) => $user->can($p).
     *
     * @return array<string, array{label: string, icon: string, class: string, permission: string, note: string, help: string, needsItems?: bool}> to => definition
     */
    public static function available(string $from, callable $can): array
    {
        $result = [];

        foreach (self::TRANSITIONS[$from] ?? [] as $to => $t) {
            if ($can($t['permission'])) {
                $result[$to] = self::localize($from, $to, $t);
            }
        }

        return $result;
    }

    /**
     * Transition texts (label, help, note label) in the screen language (Labels.proposalAction_<from>_<to>...).
     *
     * @param string               $from Current status.
     * @param string               $to   Target status.
     * @param array<string, mixed> $t    Definition with English texts.
     *
     * @return array<string, mixed>
     */
    private static function localize(string $from, string $to, array $t): array
    {
        $t['label'] = code_label('proposalAction', $from . '_' . $to, $t['label']);
        $t['help']  = code_label('proposalAction', $from . '_' . $to . '_help', $t['help']);

        if (isset($t['noteLabel'])) {
            $t['noteLabel'] = code_label('proposalAction', $from . '_' . $to . '_note', $t['noteLabel']);
        }

        return $t;
    }
}
