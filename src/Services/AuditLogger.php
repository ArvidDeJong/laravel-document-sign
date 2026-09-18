<?php

namespace Darvis\Signer\Services;

use Darvis\Signer\Models\AuditEvent;
use Darvis\Signer\Models\Document;
use Darvis\Signer\Models\Signer;
use Illuminate\Http\Request;

class AuditLogger
{
    /**
     * Record an audit trail event for a document.
     */
    public function log(
        Document $document,
        string $event,
        ?Signer $signer = null,
        ?Request $request = null,
        array $context = [],
    ): AuditEvent {
        return $document->auditEvents()->create([
            'signer_id' => $signer?->id,
            'event' => $event,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'context' => $context === [] ? null : $context,
        ]);
    }
}
