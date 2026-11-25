<?php

namespace Tests\Unit;

use Fleetbase\FleetOps\Http\Requests\CreateEntityRequest;
use Fleetbase\FleetOps\Http\Requests\CreateFuelTransactionRequest;
use Fleetbase\FleetOps\Http\Requests\CreateFuelReportRequest;
use Fleetbase\FleetOps\Http\Requests\CreateServiceRateRequest;
use Fleetbase\FleetOps\Http\Requests\QueryServiceQuotesRequest;
use Tests\TestCase;

class NewTransportCurrencyTest extends TestCase
{
    public function test_transport_records_accept_only_inr_on_create_and_update(): void
    {
        $cases = [
            [\Fleetbase\FleetOps\Http\Requests\CreateVehicleRequest::class, 'currency', ['currency' => 'USD']],
            [\Fleetbase\FleetOps\Http\Requests\CreateTrailerRequest::class, 'currency', ['currency' => 'USD']],
            [\Fleetbase\FleetOps\Http\Requests\CreatePartRequest::class, 'currency', ['currency' => 'USD']],
            [\Fleetbase\FleetOps\Http\Requests\CreateEquipmentRequest::class, 'currency', ['currency' => 'USD']],
            [\Fleetbase\FleetOps\Http\Requests\CreateWorkOrderRequest::class, 'currency', ['currency' => 'USD']],
            [\Fleetbase\FleetOps\Http\Requests\CreateDriverRequest::class, 'currency', ['currency' => 'USD']],
            [CreateFuelTransactionRequest::class, 'currency', ['currency' => 'USD']],
            [CreateFuelReportRequest::class, 'currency', ['currency' => 'USD']],
            [CreateServiceRateRequest::class, 'currency', ['currency' => 'USD']],
            [\Fleetbase\FleetOps\Http\Requests\CreateServiceQuoteRequest::class, 'currency', ['currency' => 'USD']],
            [QueryServiceQuotesRequest::class, 'currency', ['currency' => 'USD']],
            [\Fleetbase\FleetOps\Http\Requests\CreateMaintenanceRequest::class, 'currency', ['currency' => 'USD']],
            [CreateEntityRequest::class, 'currency', ['declared_value' => 100, 'currency' => 'USD']],
            [\Fleetbase\FleetOps\Http\Requests\CreatePayloadRequest::class, 'cod_currency', ['cod_amount' => 100, 'cod_currency' => 'USD']],
            [\Fleetbase\FleetOps\Http\Requests\CreateCustomerOrderRequest::class, 'entities.*.currency', ['entities' => [['declared_value' => 100, 'currency' => 'USD']]]],
        ];

        foreach ($cases as [$class, $field, $payload]) {
            foreach ([
                ['POST', 'INR', true], ['POST', 'USD', false],
                ['PATCH', 'INR', true], ['PATCH', 'USD', false],
                ['PUT', 'INR', true], ['PUT', 'USD', false],
            ] as [$method, $currency, $expected]) {
                $data = $payload;
                if ($field === 'entities.*.currency') {
                    $data['entities'][0]['currency'] = $currency;
                } else {
                    $data[$field] = $currency;
                }

                $request = $class::create('/test', $method, $data);
                $rules = [$field => $request->rules()[$field]];
                $this->assertSame($expected, $this->app['validator']->make($data, $rules)->passes(), "$class $method $currency");
            }
        }
    }

    public function test_maintenance_line_item_currency_is_inr_on_create_and_edit(): void
    {
        $controller = (new \ReflectionClass(\Fleetbase\FleetOps\Http\Controllers\Internal\v1\MaintenanceController::class))
            ->newInstanceWithoutConstructor();
        $rulesMethod = new \ReflectionMethod($controller, 'lineItemValidationRules');
        $rulesMethod->setAccessible(true);

        foreach ([
            ['POST', 'INR', true], ['POST', 'USD', false],
            ['PUT', 'INR', true], ['PUT', 'USD', false],
        ] as [$method, $currency, $expected]) {
            $request = \Illuminate\Http\Request::create('/maintenances/test/line-items', $method);
            $rules = $rulesMethod->invoke($controller, $request);
            $data = [
                'description' => 'Oil filter',
                'quantity'    => 1,
                'unit_cost'   => 100,
                'currency'    => $currency,
            ];

            $this->assertSame($expected, $this->app['validator']->make($data, $rules)->passes(), "maintenance line item $method $currency");
        }
    }
}
