<?php

namespace Darvis\Signer\Models;

use Darvis\Signer\Enums\SignerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class Signer extends Model
{
    use Notifiable;

    protected $table = 'signer_signers';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => SignerStatus::class,
            'page' => 'integer',
            'x' => 'float',
            'y' => 'float',
            'width' => 'float',
            'signed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Signer $signer) {
            $signer->uuid ??= (string) Str::uuid();
        });
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function hasSigned(): bool
    {
        return $this->status === SignerStatus::Signed;
    }

    public function routeNotificationForMail(): string
    {
        return $this->email;
    }
}
