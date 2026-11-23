<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BillingOrganizationIsolationTest extends TestCase
{
    #[Test]
    public function billing_administrator_lookup_requires_active_company_membership(): void
    {
        $apiRoot = dirname(__DIR__, 2);
        $source = file_get_contents($apiRoot . '/vendor/fleetbase/core-api/src/Billing/Http/Controllers/SubscriptionController.php');

        $this->assertIsString($source);
        $this->assertMatchesRegularExpression(
            <<<'REGEX'
/CompanyUser::where\('company_uuid', \$company->uuid\).*?where\('user_uuid', \$user->uuid\).*?where\('status', 'active'\).*?first\(\)/s
REGEX,
            $source
        );
    }
}
