<?php

namespace Darvis\Signer\Models;

use Darvis\Signer\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Document extends Model
{
    protected $table = 'signer_documents';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Document $document) {
            $document->uuid ??= (string) Str::uuid();
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function signers(): HasMany
    {
        return $this->hasMany(Signer::class, 'document_id');
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class, 'document_id');
    }

    public function isFullySigned(): bool
    {
        return $this->signers()->where('status', '!=', 'signed')->doesntExist();
    }
}
