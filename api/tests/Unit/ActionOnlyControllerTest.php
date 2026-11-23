<?php
namespace Tests\Unit;
use Fleetbase\Http\Controllers\FleetbaseController;
use PHPUnit\Framework\TestCase;
class ActionOnlyControllerTest extends TestCase {
    public function test_action_only_controller_does_not_resolve_empty_model(): void {
        $controller = new class extends FleetbaseController {};
        $this->assertNull($controller->model);
    }
}
