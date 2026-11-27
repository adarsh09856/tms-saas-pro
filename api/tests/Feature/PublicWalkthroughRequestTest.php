<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicWalkthroughRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'sqlite',
            'database.connections.mysql.database' => ':memory:',
            'fleetbase.connection.db' => 'mysql',
        ]);
        DB::purge('mysql');

        Schema::create('walkthrough_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 160);
            $table->string('company', 180);
            $table->string('email', 254)->index();
            $table->string('phone', 40)->nullable();
            $table->timestamp('consent_at');
            $table->string('status', 24)->default('new')->index();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('walkthrough_request_status_audits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('walkthrough_request_id')->index();
            $table->string('from_status', 24);
            $table->string('to_status', 24);
            $table->string('actor_uuid', 64)->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
        });

        // Core's EnsureFleetbaseConfigured global middleware fails closed unless
        // these installation tables exist, even for the public marketing route.
        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('key')->index();
                $table->longText('value')->nullable();
            });
        }
        foreach (['users', 'companies'] as $tableName) {
            if (!Schema::hasTable($tableName)) {
                Schema::create($tableName, function (Blueprint $table) {
                    $table->bigIncrements('id');
                });
            }
        }
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('walkthrough_requests');
        Schema::dropIfExists('walkthrough_request_status_audits');
        parent::tearDown();
    }

    public function test_valid_request_is_saved_for_review_without_exposing_a_read_route(): void
    {
        $response = $this->postJson('/int/v1/public/walkthrough-requests', [
            'name' => ' Avery Example ',
            'company' => ' Northline Transport ',
            'email' => ' SALES@EXAMPLE.COM ',
            'phone' => '+1 555 0100',
            'consent' => true,
        ]);
        $response->assertCreated()
            ->assertJson(['message' => 'Your walkthrough request has been received.'])
            ->assertJsonMissingPath('id')
            ->assertJsonMissingPath('request');
        $this->assertDatabaseHas('walkthrough_requests', [
            'name' => 'Avery Example',
            'company' => 'Northline Transport',
            'email' => 'sales@example.com',
            'phone' => '+1 555 0100',
        ]);
        $this->getJson('/int/v1/public/walkthrough-requests')->assertMethodNotAllowed();
    }

    public function test_invalid_submission_is_rejected_without_persistence(): void
    {
        $this->postJson('/int/v1/public/walkthrough-requests', [
            'name' => '',
            'company' => 'Northline',
            'email' => 'not-an-email',
            'consent' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'consent']);

        $this->assertDatabaseCount('walkthrough_requests', 0);
    }

    public function test_submission_is_rate_limited(): void
    {
        $payload = ['name' => 'Avery', 'company' => 'Northline', 'email' => 'avery@example.com', 'consent' => true];
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/int/v1/public/walkthrough-requests', $payload)->assertCreated();
        }

        $this->postJson('/int/v1/public/walkthrough-requests', $payload)->assertStatus(429);
    }

    public function test_platform_admin_can_list_and_update_status_with_append_only_audit(): void
    {
        $this->withoutMiddleware();
        $this->postJson('/int/v1/public/walkthrough-requests', [
            'name' => 'Avery', 'company' => 'Northline', 'email' => 'avery@example.com', 'consent' => true,
        ])->assertCreated();

        $this->getJson('/int/v1/platform/walkthrough-requests')
            ->assertOk()->assertJsonPath('data.0.email', 'avery@example.com')->assertJsonPath('data.0.status', 'new');

        $this->patchJson('/int/v1/platform/walkthrough-requests/1/status', ['status' => 'contacted'])
            ->assertOk()->assertJsonPath('request.status', 'contacted');

        $this->assertDatabaseHas('walkthrough_request_status_audits', [
            'walkthrough_request_id' => 1, 'from_status' => 'new', 'to_status' => 'contacted',
        ]);
        $this->patchJson('/int/v1/platform/walkthrough-requests/1/status', ['status' => 'deleted'])->assertUnprocessable();
        $this->assertDatabaseCount('walkthrough_request_status_audits', 1);
    }

    public function test_walkthrough_admin_routes_are_protected_and_deny_guests_and_tenant_users(): void
    {
        $list = collect(Route::getRoutes())->first(fn ($route) => $route->uri() === 'int/v1/platform/walkthrough-requests');
        $status = collect(Route::getRoutes())->first(fn ($route) => $route->uri() === 'int/v1/platform/walkthrough-requests/{id}/status');
        $this->assertNotNull($list);
        $this->assertNotNull($status);
        foreach ([$list, $status] as $route) {
            $this->assertContains('fleetbase.protected', $route->middleware());
            $this->assertContains(\Fleetbase\Http\Middleware\AdminGuard::class, $route->middleware());
        }

        $this->withoutMiddleware('fleetbase.protected');
        config(['auth.defaults.guard' => 'web']);
        $this->getJson('/int/v1/platform/walkthrough-requests')->assertUnauthorized();
        $this->patchJson('/int/v1/platform/walkthrough-requests/1/status', ['status' => 'closed'])->assertUnauthorized();

        $user = new \Fleetbase\Models\User();
        $user->forceFill(['type' => 'vendor']);
        $this->actingAs($user, 'web');
        $this->getJson('/int/v1/platform/walkthrough-requests')->assertUnauthorized();
        $this->patchJson('/int/v1/platform/walkthrough-requests/1/status', ['status' => 'closed'])->assertUnauthorized();
    }
}
