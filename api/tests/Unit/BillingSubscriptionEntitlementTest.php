<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Fleetbase\Billing\Models\Subscription;
use Tests\TestCase;

class BillingSubscriptionEntitlementTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_active_subscription_is_entitled(): void
    {
        $subscription = new Subscription(['payment_gateway_status' => 'active']);

        $this->assertTrue($subscription->isEntitled());
    }

    public function test_trial_expires_at_its_end_time(): void
    {
        Carbon::setTestNow('2026-11-03 12:00:00');
        $subscription = new Subscription([
            'payment_gateway_status' => 'trialing',
            'trial_ends_at' => '2026-11-03 12:01:00',
        ]);
        $this->assertTrue($subscription->isEntitled());

        Carbon::setTestNow('2026-11-03 12:01:00');
        $this->assertFalse($subscription->isEntitled());
    }

    public function test_past_due_subscription_expires_after_grace_period(): void
    {
        Carbon::setTestNow('2026-11-03 12:00:00');
        $subscription = new Subscription([
            'payment_gateway_status' => 'past_due',
            'grace_ends_at' => '2026-11-04 12:00:00',
        ]);
        $this->assertTrue($subscription->isEntitled());

        Carbon::setTestNow('2026-11-04 12:00:00');
        $this->assertFalse($subscription->isEntitled());
    }

    public function test_cancelled_subscription_is_not_entitled(): void
    {
        $subscription = new Subscription(['payment_gateway_status' => 'cancelled']);

        $this->assertFalse($subscription->isEntitled());
    }
}
