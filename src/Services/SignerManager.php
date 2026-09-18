<?php

namespace Darvis\Signer\Services;

use Darvis\Signer\Support\SignerConfig;

class SignerManager
{
    public function __construct(
        protected AuditLogger $auditLogger,
        protected SignerConfig $config,
    ) {}

    /**
     * Start a new document signing flow for the given PDF file.
     */
    public function document(string $pdfPath): DocumentBuilder
    {
        return new DocumentBuilder($pdfPath, $this->auditLogger, $this->config);
    }
}
