<?php

namespace Tests\Unit;

use Fleetbase\FleetOps\Http\Controllers\Internal\v1\DriverController;
use Fleetbase\FleetOps\Models\Driver;
use Fleetbase\FleetOps\Models\Fleet;
use Fleetbase\FleetOps\Models\Vehicle;
use Illuminate\Http\Request;
use Tests\TestCase;

class FleetPairingDriverFake extends Driver
{
    public array $assignedVehicles = [];
    public array $updates          = [];

    public function assignVehicle(Vehicle $vehicle): Driver
    {
        $this->assignedVehicles[] = $vehicle->uuid;

        return $this;
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        $this->updates[] = $attributes;

        return true;
    }
}

class FleetPairingControllerProbe extends DriverController
{
    public FleetPairingDriverFake $driver;
    public Vehicle $vehicle;
    public Fleet $fleet;
    public bool $driverIsMember  = true;
    public bool $vehicleIsMember = true;

    protected function findDriver(?string $id): Driver
    {
        return $this->driver;
    }

    protected function findVehicle(?string $id): Vehicle
    {
        return $this->vehicle;
    }

    protected function findPairingFleet(string $id): ?Fleet
    {
        return $this->fleet;
    }

    protected function fleetHasDriver(Fleet $fleet, Driver $driver): bool
    {
        return $this->driverIsMember;
    }

    protected function fleetHasVehicle(Fleet $fleet, Vehicle $vehicle): bool
    {
        return $this->vehicleIsMember;
    }
}

class FleetPairingIsolationTest extends TestCase
{
    private function controller(): FleetPairingControllerProbe
    {
        $controller          = new FleetPairingControllerProbe();
        $controller->driver  = new FleetPairingDriverFake(['uuid' => 'driver-a']);
        $controller->vehicle = new Vehicle(['uuid' => 'vehicle-a']);
        $controller->fleet   = new Fleet(['uuid' => 'fleet-a']);

        return $controller;
    }

    public function test_pairing_rejects_a_vehicle_outside_the_selected_fleet(): void
    {
        $controller                  = $this->controller();
        $controller->vehicleIsMember = false;

        $response = $controller->assignVehicle(new Request([
            'vehicle' => 'vehicle-a',
            'fleet'   => 'fleet-a',
        ]), 'driver-a');

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame([
            'error' => 'The driver and vehicle must both belong to the selected fleet.',
        ], $response->getData(true));
        $this->assertSame([], $controller->driver->assignedVehicles);
    }

    public function test_unpairing_rejects_an_assigned_vehicle_outside_the_selected_fleet(): void
    {
        $controller                  = $this->controller();
        $controller->vehicleIsMember = false;
        $controller->driver->setRelation('vehicle', $controller->vehicle);

        $response = $controller->unassignVehicle('driver-a', new Request([
            'fleet' => 'fleet-a',
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame([
            'error' => 'The driver and assigned vehicle must both belong to the selected fleet.',
        ], $response->getData(true));
        $this->assertSame([], $controller->driver->updates);
    }

    public function test_unassign_vehicle_keeps_its_existing_single_argument_contract(): void
    {
        $controller = $this->controller();

        $response = $controller->unassignVehicle('driver-a');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertContains(['vehicle_uuid' => null], $controller->driver->updates);
    }
}
