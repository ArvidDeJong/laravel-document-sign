<?php

namespace Darvis\Signer\Models;

use Darvis\Signer\Enums\SignerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /**
     * @return HasMany<AuditEvent, $this>
     */
    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class, 'signer_id');
    }

    /**
     * When the invitation was last sent: the newest `invitation_sent` audit
     * event of this signer, or the creation date when the trail has none.
     */
    public function lastInvitedAt(): ?Carbon
    {
        $sentAt = $this->auditEvents()
            ->where('event', 'invitation_sent')
            ->latest('created_at')
            ->latest('id')
            ->value('created_at');

        return $sentAt instanceof Carbon ? $sentAt : $this->created_at;
    }

    /**
     * Whether the signing link is past the given number of hours since the
     * invitation was last sent.
     */
    public function invitationExpired(int $hours): bool
    {
        $invitedAt = $this->lastInvitedAt();

        return $invitedAt !== null && $invitedAt->copy()->addHours($hours)->isPast();
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
