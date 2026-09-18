<?php

namespace Darvis\Signer\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $company
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $postal_code
 * @property string|null $city
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Contact> $contacts
 * @property-read Collection<int, Document> $documents
 */
class Customer extends Model
{
    protected $table = 'signer_customers';

    protected $guarded = [];

    /**
     * @return HasMany<Contact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'customer_id');
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'customer_id');
    }
}
