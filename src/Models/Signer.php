<?php

namespace Darvis\Signer\Models;

use Darvis\Signer\Enums\SignerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $document_id
 * @property string $name
 * @property string $email
 * @property SignerStatus $status
 * @property int $page
 * @property float $x
 * @property float $y
 * @property float|null $width
 * @property string|null $signature_path
 * @property Carbon|null $signed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Document $document
 */
class Signer extends Model
{
    use Notifiable;

    protected $table = 'signer_signers';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
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

    /**
     * @return BelongsTo<Document, $this>
     */
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
