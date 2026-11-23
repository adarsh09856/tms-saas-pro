<?php

namespace Tests\Unit;

use Fleetbase\FleetOps\Models\TollTransaction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class FastagMappingStateTest extends TestCase
{
    #[Test]
    public function vehicle_mapping_preserves_disputes_and_finance_posting_states(): void
    {
        foreach (['disputed', 'allocated', 'partially_allocated', 'reversed', 'refund_allocated', 'refund_partially_allocated', 'reversal_pending', 'ready'] as $status) {
            $transaction = $this->transaction($status, '125.00');
            $this->assertSame($status, $transaction->statusAfterVehicleMapping());
        }
    }

    #[Test]
    public function only_unmatched_records_advance_to_the_appropriate_review_state(): void
    {
        $this->assertSame('ready', $this->transaction('unmatched', '125.00')->statusAfterVehicleMapping());
        $this->assertSame('reversal_pending', $this->transaction('unmatched', '-125.00')->statusAfterVehicleMapping());
    }

    #[Test]
    public function dispute_resolution_uses_the_previous_status_after_mapping(): void
    {
        $transaction = $this->transaction('disputed', '125.00');
        $this->assertSame('ready', $transaction->statusAfterVehicleMapping('unmatched'));
        $this->assertSame('allocated', $transaction->statusAfterVehicleMapping('allocated'));
    }

    private function transaction(string $status, string $amount): TollTransaction
    {
        /** @var TollTransaction $transaction */
        $transaction = (new ReflectionClass(TollTransaction::class))->newInstanceWithoutConstructor();
        $transaction->setRawAttributes(['status' => $status, 'amount' => $amount]);

        return $transaction;
    }
}
