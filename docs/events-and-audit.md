---
title: "Events & audit trail"
description: "React to signatures with the SignerSigned and DocumentCompleted events, and what the audit trail in signer_audit_events records for every document."
nav_order: 4
---

# Events & audit trail

## Events

The package dispatches two events your application can listen for:

| Event | When |
| --- | --- |
| `Darvis\Signer\Events\SignerSigned` | A signer has signed; carries the `Signer` |
| `Darvis\Signer\Events\DocumentCompleted` | The last signer has signed and the signed PDF is stored; carries the `Document` |

```php
use Darvis\Signer\Events\DocumentCompleted;
use Illuminate\Support\Facades\Event;

Event::listen(function (DocumentCompleted $event) {
    // for example: mail the final PDF to every party
    $event->document->signed_path;
});
```

Listen for the events instead of polling the status; `DocumentCompleted` fires after the signed PDF exists on the disk.

Both events are dispatched after the database transaction of the signature has been committed. A signature that failed and was rolled back, for example because stamping threw, dispatches nothing; see [When signing fails](signing.md#when-signing-fails).

## Audit trail

Every step is written to `signer_audit_events`, with the IP address and user agent when a request was at hand:

| Event | Recorded when |
| --- | --- |
| `document_created` | `send()` stored the document and its signers |
| `invitation_sent` | A signing invitation was mailed, one per signer. The newest one per signer starts the `link_expires_after_hours` window of the signing routes, so record it when you [send an invitation again](signing.md#when-the-link-expires) |
| `document_signed` | A signer submitted a signature, with the signer's IP address and user agent |
| `document_completed` | The signed PDF was stored |

```php
foreach ($document->auditEvents as $event) {
    $event->event;        // 'document_signed'
    $event->signer;       // the Signer, or null for document level events
    $event->ip_address;
    $event->user_agent;
    $event->created_at;
}
```

A user agent longer than the 255 characters of the column is cut off.

The portal shows the trail on the document page. Deleting a document through the portal removes its files, its signers and its audit events; the trail lives as long as the document does.
