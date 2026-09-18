<?php

namespace Darvis\Signer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $table = 'signer_customers';

    protected $guarded = [];

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'customer_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'customer_id');
    }
}
