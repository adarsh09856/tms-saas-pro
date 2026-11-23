<?php

namespace Tests\Unit;

use Fleetbase\FleetOps\Http\Requests\CreateEntityRequest;
use Fleetbase\FleetOps\Http\Requests\CreateFuelTransactionRequest;
use Fleetbase\FleetOps\Http\Requests\CreateServiceRateRequest;
use Tests\TestCase;

class NewTransportCurrencyTest extends TestCase
{
    public function test_new_transport_records_accept_only_inr_while_existing_currency_remains_editable(): void
    {
        foreach ([CreateEntityRequest::class, CreateFuelTransactionRequest::class, CreateServiceRateRequest::class] as $class) {
            foreach ([['POST', 'INR', true], ['POST', 'USD', false], ['PATCH', 'USD', true]] as [$method, $currency, $expected]) {
                $request = $class::create('/test', $method, ['currency' => $currency]);
                $rules = ['currency' => $request->rules()['currency']];
                $this->assertSame($expected, $this->app['validator']->make(['currency' => $currency], $rules)->passes(), "$class $method $currency");
            }
        }
    }
}
