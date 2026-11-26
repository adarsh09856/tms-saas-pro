<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorDomainClaim extends Model
{
    /** Domain ownership claims belong to the platform database, never a tenant sandbox. */
    protected $connection = 'mysql';

    protected $table = 'vendor_domain_claims';

    protected $fillable = [
        'host',
        'kind',
        'company_uuid',
        'status',
        'verification_token_hash',
        'verification_expires_at',
        'verified_at',
        'created_by_uuid',
        'verified_by_uuid',
    ];

    protected $hidden = ['verification_token_hash'];

    protected $casts = [
        'verification_expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];
}
