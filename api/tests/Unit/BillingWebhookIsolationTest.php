<?php

namespace Tests\Unit;

use Fleetbase\Billing\Models\Subscription;
use Fleetbase\Billing\Services\BillingService;
use Fleetbase\Models\Company;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use UnexpectedValueException;

class BillingWebhookIsolationTest extends TestCase
{
    private BillingService $billing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->billing = new BillingService();
    }

    #[Test]
    public function matching_subscription_customer_is_accepted(): void
    {
        $subscription = $this->subscription('company-a', 'cus_a');

        $this->billing->assertStripeCustomerMatches($subscription, 'cus_a');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function missing_or_different_invoice_customer_is_rejected(): void
    {
        $subscription = $this->subscription('company-a', 'cus_a');

        foreach ([null, '', 'cus_b'] as $customerId) {
            try {
                $this->billing->assertStripeCustomerMatches($subscription, $customerId);
                $this->fail('Expected a mismatched Stripe customer to be rejected.');
            } catch (UnexpectedValueException $error) {
                $this->assertSame('Stripe customer does not match the billing subscription.', $error->getMessage());
            }
        }
    }

    #[Test]
    public function subscription_cannot_move_to_another_company(): void
    {
        $company = $this->company('company-a', 'cus_a');
        $existing = $this->subscription('company-b', 'cus_a');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Stripe subscription cannot be reassigned to another organization.');

        $this->invokeOwnershipCheck($company, $existing, 'company-a', 'cus_a');
    }

    #[Test]
    public function event_customer_must_belong_to_metadata_company(): void
    {
        $company = $this->company('company-a', 'cus_a');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Stripe customer does not belong to the referenced organization.');

        $this->invokeOwnershipCheck($company, null, 'company-a', 'cus_b');
    }

    #[Test]
    public function matching_company_customer_and_existing_subscription_are_accepted(): void
    {
        $company = $this->company('company-a', 'cus_a');
        $existing = $this->subscription('company-a', 'cus_a');

        $this->invokeOwnershipCheck($company, $existing, 'company-a', 'cus_a');

        $this->addToAssertionCount(1);
    }

    private function invokeOwnershipCheck(Company $company, ?Subscription $subscription, string $companyUuid, string $customerId): void
    {
        $method = new ReflectionMethod($this->billing, 'assertStripeOwnership');
        $method->setAccessible(true);
        $method->invoke($this->billing, $company, $subscription, $companyUuid, $customerId);
    }

    private function company(string $uuid, string $customerId): Company
    {
        /** @var Company $company */
        $company = (new ReflectionClass(Company::class))->newInstanceWithoutConstructor();
        $company->setRawAttributes(['uuid' => $uuid, 'stripe_customer_id' => $customerId]);

        return $company;
    }

    private function subscription(string $companyUuid, string $customerId): Subscription
    {
        return (new Subscription())->forceFill(['company_uuid' => $companyUuid, 'stripe_customer_id' => $customerId]);
    }
}
