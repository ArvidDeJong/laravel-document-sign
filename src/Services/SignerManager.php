<?php

namespace Darvis\Signer\Services;

class SignerManager
{
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * Start a new document signing flow for the given PDF file.
     */
    public function document(string $pdfPath): DocumentBuilder
    {
        return new DocumentBuilder($pdfPath, $this->auditLogger);
    }
}
