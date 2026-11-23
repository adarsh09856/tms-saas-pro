<?php

namespace Tests\Unit;

use Fleetbase\FleetOps\Http\Requests\Internal\CreateOrderRequest;
use Tests\TestCase;

class InternalOrderDispatchInputTest extends TestCase
{
    public function test_legacy_false_is_preserved_and_canonical_input_wins(): void
    {
        $this->assertSame(['dispatch' => false, 'dispatched' => false], CreateOrderRequest::normalizeDispatchInput(['dispatch' => false]));
        $this->assertSame(['dispatch' => true, 'dispatched' => false], CreateOrderRequest::normalizeDispatchInput(['dispatch' => true, 'dispatched' => false]));
        $this->assertSame([], CreateOrderRequest::normalizeDispatchInput([]));
    }

    public function test_canonical_dispatch_value_is_validated_without_truthy_casting(): void
    {
        $rules = (new CreateOrderRequest())->rules();
        foreach ([true, false, 0, 1, '0', '1', null] as $value) {
            $validator = $this->app['validator']->make(['dispatched' => $value], ['dispatched' => $rules['dispatched']]);
            $this->assertTrue($validator->passes());
        }
        foreach (['false', 'yes', 2, []] as $value) {
            $input = CreateOrderRequest::normalizeDispatchInput(['dispatch' => $value]);
            $validator = $this->app['validator']->make($input, ['dispatched' => $rules['dispatched']]);
            $this->assertTrue($validator->fails());
        }
    }

    public function test_nested_payload_requires_a_complete_route(): void
    {
        $originalDefault = config('database.default');
        config(['database.connections.order_validation' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        config(['database.default' => 'order_validation']);
        session()->put('company', 'company-a');
        \Illuminate\Support\Facades\Schema::create('places', function ($table) {
            $table->string('uuid');
            $table->string('public_id')->nullable();
            $table->string('company_uuid');
            $table->timestamp('deleted_at')->nullable();
        });
        \Illuminate\Support\Facades\DB::table('places')->insert([
            ['uuid' => 'a', 'company_uuid' => 'company-a'],
            ['uuid' => 'b', 'company_uuid' => 'company-a'],
            ['uuid' => 'foreign', 'company_uuid' => 'company-b'],
        ]);
        foreach (['drivers', 'vehicles', 'service_quotes', 'purchase_rates', 'contacts', 'vendors', 'integrated_vendors'] as $tableName) {
            \Illuminate\Support\Facades\Schema::create($tableName, function ($table) {
                $table->string('uuid');
                $table->string('company_uuid');
                $table->timestamp('deleted_at')->nullable();
            });
            \Illuminate\Support\Facades\DB::table($tableName)->insert([
                ['uuid' => 'own', 'company_uuid' => 'company-a'],
                ['uuid' => 'foreign', 'company_uuid' => 'company-b'],
            ]);
        }
        $cases = [
            [['waypoints' => [['place_uuid' => 'a'], ['place_uuid' => 'b']]], true],
            [['waypoints' => [['place_uuid' => 'a']]], false],
            [['pickup_uuid' => 'a', 'dropoff_uuid' => 'b'], true],
            [['pickup_uuid' => 'a', 'dropoff_uuid' => 'b', 'waypoints' => []], true],
            [['pickup' => ['name' => 'Origin'], 'dropoff' => ['name' => 'Destination'], 'waypoints' => []], true],
            [['pickup' => ['name' => 'Origin'], 'waypoints' => []], false],
            [['pickup_uuid' => 'a'], false],
            [['pickup_uuid' => 'foreign', 'dropoff_uuid' => 'b'], false],
            [['pickup' => ['uuid' => 'foreign'], 'dropoff_uuid' => 'b'], false],
            [['pickup_uuid' => 'a', 'dropoff_uuid' => 'b', 'return_uuid' => 'foreign'], false],
            [['waypoints' => [['place_uuid' => 'a'], ['place_uuid' => 'foreign']]], false],
            [['waypoints' => [['place_uuid' => 'a'], ['place' => ['uuid' => 'foreign']]]], false],
            [['waypoints' => [['place_uuid' => 'a'], ['place' => ['place_uuid' => 'foreign']]]], false],
            [['waypoints' => [['place_uuid' => 'a'], ['place' => ['uuid' => 'temporary-search-id', 'name' => 'New place']]]], true],
            [['pickup' => ['name' => 'Origin', 'company_uuid' => 'company-b'], 'dropoff_uuid' => 'b'], false],
            [['waypoints' => [['place_uuid' => 'a', 'customer_uuid' => 'foreign'], ['place_uuid' => 'b']]], false],
        ];

        try {
            foreach ($cases as [$payload, $expected]) {
                $order = ['order_config_uuid' => 'test-config', 'payload' => $payload];
                $request = CreateOrderRequest::create('/int/v1/orders', 'POST', ['order' => $order]);
                $this->assertSame($expected, $this->app['validator']->make($order, $request->rules())->passes());
            }
            foreach (['driver_assigned_uuid', 'vehicle_assigned_uuid', 'service_quote_uuid', 'purchase_rate_uuid', 'customer_uuid', 'facilitator_uuid', 'driver', 'service_quote', 'purchase_rate'] as $field) {
                foreach (['own' => true, 'foreign' => false] as $value => $expected) {
                    $order = ['order_config_uuid' => 'test-config', $field => $value];
                    $request = CreateOrderRequest::create('/int/v1/orders', 'POST', ['order' => $order]);
                    $this->assertSame($expected, $this->app['validator']->make($order, $request->rules())->passes(), "$field: $value");
                }
            }
        } finally {
            config(['database.default' => $originalDefault]);
            \Illuminate\Support\Facades\DB::purge('order_validation');
        }
    }
}
