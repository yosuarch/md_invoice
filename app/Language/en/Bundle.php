<?php

/**
 * Combined order report (App\Services\OrderBundle, OrderBundles controller).
 */
return [
    'button'          => 'Combined report',
    'title'           => 'Combined report {0}',
    'intro'           => 'Choose the documents and attachments to join into one PDF. A cover page with a table of contents comes first.',
    'selectAll'       => 'Select all',
    'selectNone'      => 'Select none',
    'attachment'      => 'Attachment',
    'notMergeable'    => 'This file type cannot be included (only PDF and images).',
    'cancelledHint'   => 'Cancelled, void or reversed documents are not selected by default.',
    'download'        => 'Download instead of opening in the browser',
    'build'           => 'Create PDF',
    'notComplete'     => 'The combined report is available once the order is finished: approved, invoiced, paid in full and delivered.',
    'nothingSelected' => 'Select at least one document or attachment.',
    'appendix'        => 'Appendix',
    'timeline'        => 'Order history (who did what, when)',
];
