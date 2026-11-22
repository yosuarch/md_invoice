<?php

declare(strict_types=1);

namespace App\Models;

/**
 * document_numbering: number format per document type (Settings → Document numbering), read by
 * App\Services\DocumentSequence. Changes are audited.
 */
class DocumentNumberingModel extends BaseModel
{
    protected $table          = 'document_numbering';
    protected $allowedFields  = ['doc_type', 'pattern', 'period'];
    protected $useSoftDeletes = false;

    /**
     * Row of a document type.
     *
     * @param string $type Document type.
     *
     * @return array<string, mixed>|null
     */
    public function forType(string $type): ?array
    {
        return $this->where('doc_type', $type)->first();
    }
}
