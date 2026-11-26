<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorDomainAudit extends Model
{
    public const UPDATED_AT = null;

    /** Audit history remains central even when a request carries a sandbox header. */
    protected $connection = 'mysql';

    protected $table = 'vendor_domain_audits';

    protected $fillable = [
        'vendor_domain_claim_id',
        'host',
        'company_uuid',
        'action',
        'actor_uuid',
        'details',
    ];

    protected $casts = ['details' => 'array'];
}
