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
     *
     * @param  array<string, mixed>  $context
     */
    public function log(
        Document $document,
        string $event,
        ?Signer $signer = null,
        ?Request $request = null,
        array $context = [],
    ): AuditEvent {
        $userAgent = $request?->userAgent();

        return $document->auditEvents()->create([
            'signer_id' => $signer?->id,
            'event' => $event,
            'ip_address' => $request?->ip(),
            // The column holds 255 characters; a longer value fails the insert on a strict database.
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 255),
            'context' => $context === [] ? null : $context,
        ]);
    }
}
