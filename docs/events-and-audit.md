---
title: "Events & audit trail"
description: "React to a signature with the SignerSigned and DocumentCompleted events, and what the audit trail records: which steps, and which one has the IP address."
nav_order: 5
---

# Events & audit trail

## Events

An event is a message the package sends inside your application at a certain moment; a listener is your code that reacts to it, see the [Laravel events documentation](https://laravel.com/docs/events). The package dispatches two events:

| Event | When |
| --- | --- |
| `Darvis\Signer\Events\SignerSigned` | A signer has signed; carries the `Signer` |
| `Darvis\Signer\Events\DocumentCompleted` | The last signer has signed and the signed PDF is stored; carries the `Document` |

In the `boot()` method of `app/Providers/AppServiceProvider.php`:

```php
use Darvis\Signer\Events\DocumentCompleted;
use Darvis\Signer\Events\SignerSigned;
use Illuminate\Support\Facades\Event;

Event::listen(function (SignerSigned $event) {
    $event->signer;             // the Signer who signed
    $event->signer->document;   // its Document
});

Event::listen(function (DocumentCompleted $event) {
    // for example: mail the final PDF to every party
    $event->document->signed_path;
});
```

Both events are dispatched in the request of the person who signs, not through the queue. A slow listener makes the signer wait, and a listener that throws gives the signer an error page although the signature is stored. Put real work in a [queued listener](https://laravel.com/docs/events#queued-event-listeners).

Listen for the events instead of polling the status; `DocumentCompleted` fires after the signed PDF exists on the disk.

Both events are dispatched after the database transaction of the signature has been committed. A signature that failed and was rolled back, for example because stamping threw, dispatches nothing; see [When signing fails](signing.md#when-signing-fails).

## Audit trail

Every step is written to `signer_audit_events`. Only `document_signed` records the IP address and the user agent, those of the signer; the other three events leave both columns empty.

| Event | Recorded when |
| --- | --- |
| `document_created` | `send()` stored the document and its signers |
| `invitation_sent` | A signing invitation was mailed, one per signer. The newest one per signer starts the `link_expires_after_hours` window of the signing routes, so record it when you [send an invitation again](signing.md#when-the-link-expires) |
| `document_signed` | A signer submitted a signature; the only event with `ip_address` and `user_agent` |
| `document_completed` | The signed PDF was stored |

```php
foreach ($document->auditEvents as $event) {
    $event->event;        // 'document_signed'
    $event->signer;       // the Signer, or null for document level events
    $event->ip_address;   // null, except for document_signed
    $event->user_agent;   // null, except for document_signed
    $event->created_at;
}
```

A user agent longer than the 255 characters of the column is cut off.

To add a step of your own, call the logger. It stores the IP address and the user agent only when you pass the request:

```php
use Darvis\Signer\Services\AuditLogger;

app(AuditLogger::class)->log($document, 'reminder_sent', $signer, request(), ['channel' => 'mail']);
```

The signature is `log(Document $document, string $event, ?Signer $signer = null, ?Request $request = null, array $context = [])`; the `context` array is stored as JSON.

The portal shows the trail on the document page. Deleting a document removes its signers and its audit events through the foreign keys, so the trail lives as long as the document does. Only the delete button in the portal also removes the files from the disk; `$document->delete()` in your own code leaves them there.
