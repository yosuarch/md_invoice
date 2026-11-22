<?php

/**
 * Labels of stored codes (statuses, types, kinds), read with code_label('group', 'CODE').
 * Key format: <group>_<CODE>. Every key must exist in app/Language/id/Labels.php too.
 */
return [
    // Proposal statuses
    'proposalStatus_DRAFT'            => 'Draft',
    'proposalStatus_PROPOSE'          => 'Proposed',
    'proposalStatus_UNDER_REVIEW'     => 'Under review',
    'proposalStatus_STORE_APPROVE'    => 'Approved',
    'proposalStatus_CANCEL_BY_CLIENT' => 'Cancelled by client',
    'proposalStatus_CANCEL_BY_STORE'  => 'Cancelled by store',

    // Proposal actions (from_to)
    'proposalAction_DRAFT_PROPOSE'                 => 'Mark as sent',
    'proposalAction_DRAFT_PROPOSE_help'            => 'The proposal was sent to the customer. It cannot be edited until negotiation starts.',
    'proposalAction_DRAFT_CANCEL_BY_STORE'         => 'Cancel',
    'proposalAction_DRAFT_CANCEL_BY_STORE_help'    => 'Cancel this draft. It can be reopened later.',
    'proposalAction_PROPOSE_UNDER_REVIEW'          => 'Customer requests changes',
    'proposalAction_PROPOSE_UNDER_REVIEW_note'     => 'What did the customer ask for?',
    'proposalAction_PROPOSE_UNDER_REVIEW_help'     => 'Record the customer\'s request (e.g. lower price, other quantity). The proposal opens for editing; send the revised version when it is ready.',
    'proposalAction_PROPOSE_STORE_APPROVE'         => 'Approve',
    'proposalAction_PROPOSE_STORE_APPROVE_help'    => 'Approve the proposal. It is locked and can be turned into an invoice.',
    'proposalAction_PROPOSE_CANCEL_BY_CLIENT'      => 'Rejected by client',
    'proposalAction_PROPOSE_CANCEL_BY_CLIENT_help' => 'The customer declined or cancelled the proposal.',
    'proposalAction_PROPOSE_CANCEL_BY_STORE'       => 'Cancel',
    'proposalAction_PROPOSE_CANCEL_BY_STORE_help'  => 'We withdraw the proposal.',
    'proposalAction_UNDER_REVIEW_PROPOSE'          => 'Send revision',
    'proposalAction_UNDER_REVIEW_PROPOSE_note'     => 'What changed? (optional)',
    'proposalAction_UNDER_REVIEW_PROPOSE_help'     => 'The revised proposal was sent to the customer. It is saved as a new revision and locked again.',
    'proposalAction_UNDER_REVIEW_STORE_APPROVE'    => 'Approve',
    'proposalAction_UNDER_REVIEW_STORE_APPROVE_help' => 'Approve the proposal. It is locked and can be turned into an invoice.',
    'proposalAction_UNDER_REVIEW_CANCEL_BY_CLIENT' => 'Rejected by client',
    'proposalAction_UNDER_REVIEW_CANCEL_BY_CLIENT_help' => 'The customer declined or cancelled the proposal.',
    'proposalAction_UNDER_REVIEW_CANCEL_BY_STORE'  => 'Cancel',
    'proposalAction_UNDER_REVIEW_CANCEL_BY_STORE_help' => 'We withdraw the proposal.',
    'proposalAction_STORE_APPROVE_UNDER_REVIEW'    => 'Revoke approval',
    'proposalAction_STORE_APPROVE_UNDER_REVIEW_help' => 'Back to negotiation. Only possible while no invoice was created from this proposal.',
    'proposalAction_CANCEL_BY_CLIENT_DRAFT'        => 'Reopen as draft',
    'proposalAction_CANCEL_BY_CLIENT_DRAFT_help'   => 'Continue working on this proposal.',
    'proposalAction_CANCEL_BY_STORE_DRAFT'         => 'Reopen as draft',
    'proposalAction_CANCEL_BY_STORE_DRAFT_help'    => 'Continue working on this proposal.',

    // Invoice statuses
    'invoiceStatus_DRAFT'          => 'Draft',
    'invoiceStatus_ISSUED'         => 'Issued',
    'invoiceStatus_PARTIALLY_PAID' => 'Partially paid',
    'invoiceStatus_PAID'           => 'Paid',
    'invoiceStatus_OVERDUE'        => 'Overdue',
    'invoiceStatus_VOID'           => 'Void',
    'invoiceStatus_CANCELLED'      => 'Cancelled',

    // Delivery note statuses and tracking events
    'deliveryStatus_PREPARING' => 'Preparing',
    'deliveryStatus_SHIPPED'   => 'On the way',
    'deliveryStatus_DELIVERED' => 'Delivered',
    'deliveryStatus_CANCELLED' => 'Cancelled',
    'deliveryEvent_CREATED'    => 'Delivery note created',
    'deliveryEvent_SHIPPED'    => 'Shipped',
    'deliveryEvent_UPDATE'     => 'Tracking update',
    'deliveryEvent_DELIVERED'  => 'Delivered',
    'deliveryEvent_CANCELLED'  => 'Cancelled',

    // Customers
    'schoolStatus_PUBLIC'  => 'Public school',
    'schoolStatus_PRIVATE' => 'Private school',
    'schoolStatusShort_PUBLIC'  => 'Public',
    'schoolStatusShort_PRIVATE' => 'Private',
    'addressType_BILLING'  => 'Billing',
    'addressType_SHIPPING' => 'Shipping',

    // Credit notes
    'reasonType_RETURN'           => 'Goods returned',
    'reasonType_PRICE_CORRECTION' => 'Price correction',
    'reasonType_CANCEL_BY_CLIENT' => 'Cancelled by client',
    'reasonType_CANCEL_BY_STORE'  => 'Cancelled by store',
    'reasonType_OTHER'            => 'Other',

    // Withholding kinds
    'taxKind_PPN'       => 'PPN collected',
    'taxKind_PPH22'     => 'PPh 22',
    'taxKind_PPH23'     => 'PPh 23',
    'taxKind_PPH_FINAL' => 'PPh final UMKM',

    // Attachments ("belongs to")
    'attachmentType_proposal'      => 'Proposal',
    'attachmentType_invoice'       => 'Invoice',
    'attachmentType_payment'       => 'Payment',
    'attachmentType_delivery_note' => 'Delivery note',

    // Reports
    'bucket_current' => 'Not due',
    'bucket_d1_30'   => '1–30 days',
    'bucket_d31_60'  => '31–60 days',
    'bucket_d61_90'  => '61–90 days',
    'bucket_d90'     => '> 90 days',
    'reconState_ok'            => 'Withheld, proof recorded',
    'reconState_missing_proof' => 'Withheld, proof number missing',
    'reconState_difference'    => 'Amount differs',
    'reconState_not_withheld'  => 'Not withheld yet',
    'reconState_unexpected'    => 'Withheld but not expected',

    // Document numbering types
    'docType_QUO' => 'Proposal (Surat Penawaran)',
    'docType_SPK' => 'Agreement (SPK)',
    'docType_INV' => 'Invoice',
    'docType_KW' => 'Receipt (kwitansi)',
    'docType_CN' => 'Credit note',
    'docType_DO' => 'Delivery note (surat jalan)',
    'docType_BAST' => 'Handover report (BAST)',
    'docType_SKU' => 'Product SKU',

    // User groups (Config\AuthGroups)
    'group_admin' => 'Admin',
    'groupDesc_admin' => 'Full access: settings, users, approvals, voiding.',
    'group_sales' => 'Sales',
    'groupDesc_sales' => 'Proposals (quotations) for their own schools; views their invoices and delivery notes.',
    'group_finance' => 'Finance',
    'groupDesc_finance' => 'Invoices, payments and credit notes.',
    'group_warehouse' => 'Warehouse',
    'groupDesc_warehouse' => 'Delivery notes: create, ship, track, deliver and cancel (all schools).',

    // Contact roles (contact_role)
    'contactRole_HEADMASTER' => 'Head of school',
    'contactRole_TREASURER' => 'BOS treasurer',
    'contactRole_OPERATOR' => 'School operator',
    'contactRole_COMMITTEE' => 'School committee',
    'contactRole_FOUNDATION' => 'Foundation',
    'contactRole_OTHER' => 'Other',
];
