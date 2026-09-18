<?php

namespace Darvis\Signer\Models;

use Darvis\Signer\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int|null $customer_id
 * @property string $title
 * @property string $original_path
 * @property string|null $signed_path
 * @property DocumentStatus $status
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer|null $customer
 * @property-read Collection<int, Signer> $signers
 * @property-read Collection<int, AuditEvent> $auditEvents
 */
class Document extends Model
{
    protected $table = 'signer_documents';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
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

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * @return HasMany<Signer, $this>
     */
    public function signers(): HasMany
    {
        return $this->hasMany(Signer::class, 'document_id');
    }

    /**
     * @return HasMany<AuditEvent, $this>
     */
    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class, 'document_id');
    }

    public function isFullySigned(): bool
    {
        return $this->signers()->where('status', '!=', 'signed')->doesntExist();
    }
}
