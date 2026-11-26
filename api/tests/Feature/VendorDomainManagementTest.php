<?php

namespace Tests\Feature;

use App\Models\VendorDomainClaim;
use App\Http\Middleware\ResolveVendorDomainTenant;
use App\Services\VendorDomainDnsTxtLookup;
use App\Services\VendorDomainHostValidator;
use Fleetbase\Http\Middleware\AdminGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VendorDomainManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'sqlite',
            'database.connections.mysql.database' => ':memory:',
            'app.url' => 'https://tms.example.com',
            'fleetbase.console.host' => 'tms.example.com',
            'vendor-domains.reserved_hosts' => ['tms.example.com'],
            'vendor-domains.subdomain_base_domain' => 'vendors.example.com',
            'vendor-domains.verification_ttl_minutes' => 60,
        ]);
        DB::purge('mysql');

        Schema::connection('mysql')->create('companies', function (Blueprint $table) {
            $table->string('uuid', 64)->primary();
            $table->string('owner_uuid', 64)->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::connection('mysql')->create('company_users', function (Blueprint $table) {
            $table->string('uuid', 64)->primary();
            $table->string('company_uuid', 64);
            $table->string('user_uuid', 64);
            $table->string('status', 24)->default('active');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
        Schema::connection('mysql')->create('roles', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('name');
            $table->string('guard_name')->default('sanctum');
            $table->string('company_uuid', 64)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::connection('mysql')->create('model_has_roles', function (Blueprint $table) {
            $table->string('role_id', 64);
            $table->string('model_type');
            $table->string('model_uuid', 64);
        });
        Schema::connection('mysql')->create('vendor_domain_claims', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('host', 253)->unique();
            $table->string('kind', 32);
            $table->string('company_uuid', 64)->index();
            $table->string('status', 24)->default('pending_dns')->index();
            $table->string('verification_token_hash', 64);
            $table->timestamp('verification_expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->string('created_by_uuid', 64)->nullable();
            $table->string('verified_by_uuid', 64)->nullable();
            $table->timestamps();
        });
        Schema::connection('mysql')->create('vendor_domain_audits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('vendor_domain_claim_id')->nullable();
            $table->string('host', 253);
            $table->string('company_uuid', 64);
            $table->string('action', 32);
            $table->string('actor_uuid', 64)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        DB::connection('mysql')->table('companies')->insert([
            ['uuid' => 'company-1', 'owner_uuid' => 'vendor-owner', 'status' => 'active'],
            ['uuid' => 'company-2', 'owner_uuid' => 'other-owner', 'status' => 'active'],
        ]);

        // Exercise the first-party route handlers directly in this isolated
        // controller contract test; route middleware is asserted separately.
        $this->withoutMiddleware();
    }

    public function test_custom_claim_is_normalized_and_secret_is_only_returned_once(): void
    {
        $response = $this->postJson('/int/v1/platform/vendor-domains', [
            'company_uuid' => 'company-1',
            'host' => '  Dispatch.Example.org. ',
            'kind' => 'custom',
        ]);

        $response->assertCreated()
            ->assertJsonPath('domain.host', 'dispatch.example.org')
            ->assertJsonPath('domain.status', 'pending_dns')
            ->assertJsonPath('verification_record.type', 'TXT');

        $this->assertSame(1, DB::connection('mysql')->table('vendor_domain_audits')->where('action', 'claimed')->count());
        $this->assertStringNotContainsString('verification_token_hash', $this->getJson('/int/v1/platform/vendor-domains/1')->getContent());
    }

    public function test_platform_and_duplicate_hosts_are_rejected(): void
    {
        $hostValidator = app(VendorDomainHostValidator::class);
        try {
            $hostValidator->normalize('tms.example.com', 'custom');
            $this->fail('A platform hostname must not be claimable.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $payload = ['company_uuid' => 'company-1', 'host' => 'same.example.org', 'kind' => 'custom'];
        $this->postJson('/int/v1/platform/vendor-domains', $payload)->assertCreated();
        $this->postJson('/int/v1/platform/vendor-domains', $payload)->assertStatus(409);
    }

    public function test_platform_subdomain_requires_configured_base_and_single_label(): void
    {
        $validator = app(VendorDomainHostValidator::class);
        $this->assertSame('north.vendors.example.com', $validator->normalize('North.Vendors.Example.com', 'platform_subdomain'));

        try {
            $validator->normalize('nested.north.vendors.example.com', 'platform_subdomain');
            $this->fail('Nested vendor subdomains must be rejected.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        try {
            $validator->normalize('tms.vendors.example.com', 'platform_subdomain');
            $this->fail('Reserved platform labels must not be claimable as vendor subdomains.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_verified_vendor_host_resolves_to_company_before_permissions_and_billing(): void
    {
        $this->withoutMiddleware();
        DB::connection('mysql')->table('vendor_domain_claims')->insert([
            'host' => 'north.vendors.example.com',
            'kind' => 'platform_subdomain',
            'company_uuid' => 'company-1',
            'status' => 'verified',
            'verification_token_hash' => hash('sha256', 'already-verified'),
            'verification_expires_at' => now()->addHour(),
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('mysql')->table('company_users')->insert([
            'uuid' => 'membership-1',
            'company_uuid' => 'company-1',
            'user_uuid' => 'vendor-owner',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('mysql')->table('company_users')->insert([
            'uuid' => 'membership-2',
            'company_uuid' => 'company-2',
            'user_uuid' => 'vendor-owner',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('https://north.vendors.example.com/int/v1/orders');
        $session = new \Illuminate\Session\Store('test-session', new \Illuminate\Session\ArraySessionHandler(120));
        $session->start();
        $session->put('company', 'company-2');
        $request->setLaravelSession($session);
        $user = new \Fleetbase\Models\User();
        $user->forceFill(['uuid' => 'vendor-owner', 'company_uuid' => 'company-2']);
        $request->setUserResolver(fn () => $user);

        $response = app(ResolveVendorDomainTenant::class)->handle($request, function ($request) {
            return response()->json([
                'company_uuid' => $request->session()->get('company'),
                'context_company_uuid' => $request->attributes->get('vendor_domain_company_uuid'),
                'user_company_uuid' => $request->user()->company_uuid,
                'role_company_uuid' => $request->user()->companyUser->company_uuid,
            ]);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'company_uuid' => 'company-1',
            'context_company_uuid' => 'company-1',
            'user_company_uuid' => 'company-1',
            'role_company_uuid' => 'company-1',
        ], $response->getData(true));
    }

    public function test_vendor_host_rejects_inactive_claims_and_unprovisioned_platform_subdomains(): void
    {
        DB::connection('mysql')->table('vendor_domain_claims')->insert([
            'host' => 'disabled.vendors.example.com',
            'kind' => 'platform_subdomain',
            'company_uuid' => 'company-1',
            'status' => 'disabled',
            'verification_token_hash' => hash('sha256', 'disabled'),
            'verification_expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('mysql')->table('vendor_domain_claims')->insert([
            'host' => 'missing-proof.vendors.example.com',
            'kind' => 'platform_subdomain',
            'company_uuid' => 'company-1',
            'status' => 'verified',
            'verification_token_hash' => hash('sha256', 'missing-proof'),
            'verification_expires_at' => now()->addHour(),
            'verified_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('mysql')->table('companies')->where('uuid', 'company-2')->update(['status' => 'suspended']);
        DB::connection('mysql')->table('vendor_domain_claims')->insert([
            'host' => 'suspended.vendors.example.com',
            'kind' => 'platform_subdomain',
            'company_uuid' => 'company-2',
            'status' => 'verified',
            'verification_token_hash' => hash('sha256', 'suspended'),
            'verification_expires_at' => now()->addHour(),
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $middleware = app(ResolveVendorDomainTenant::class);
        $session = new \Illuminate\Session\Store('test-session', new \Illuminate\Session\ArraySessionHandler(120));
        $session->start();

        foreach ([
            'disabled.vendors.example.com',
            'missing.vendors.example.com',
            'missing-proof.vendors.example.com',
            'suspended.vendors.example.com',
            'unclaimed-custom-domain.example.org',
        ] as $host) {
            $request = Request::create('https://' . $host . '/int/v1/orders');
            $request->setLaravelSession(clone $session);
            $response = $middleware->handle($request, fn () => response()->json(['unexpected' => true]));
            $this->assertSame(404, $response->getStatusCode());
        }
    }

    public function test_verified_host_denies_authenticated_user_without_active_membership(): void
    {
        DB::connection('mysql')->table('vendor_domain_claims')->insert([
            'host' => 'north.vendors.example.com',
            'kind' => 'platform_subdomain',
            'company_uuid' => 'company-1',
            'status' => 'verified',
            'verification_token_hash' => hash('sha256', 'verified'),
            'verification_expires_at' => now()->addHour(),
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('https://north.vendors.example.com/int/v1/orders');
        $session = new \Illuminate\Session\Store('test-session', new \Illuminate\Session\ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);
        $outsider = new \Fleetbase\Models\User();
        $outsider->forceFill(['uuid' => 'outsider', 'company_uuid' => 'company-2']);
        $request->setUserResolver(fn () => $outsider);

        $response = app(ResolveVendorDomainTenant::class)->handle($request, fn () => response()->json(['unexpected' => true]));
        $this->assertSame(403, $response->getStatusCode());
        $this->assertNull($session->get('company'));
    }

    public function test_vendor_context_is_registered_between_fleetbase_session_and_authorization(): void
    {
        $group = app('router')->getMiddlewareGroups()['fleetbase.protected'] ?? [];
        $priority = app(\App\Http\Kernel::class)->getMiddlewarePriority();

        $this->assertContains(ResolveVendorDomainTenant::class, $group);
        $this->assertLessThan(
            array_search(\Fleetbase\Http\Middleware\AuthorizationGuard::class, $priority, true),
            array_search(ResolveVendorDomainTenant::class, $priority, true)
        );
        $this->assertGreaterThan(
            array_search(\Fleetbase\Http\Middleware\SetupFleetbaseSession::class, $priority, true),
            array_search(ResolveVendorDomainTenant::class, $priority, true)
        );
    }

    public function test_dns_verification_checks_txt_proof_then_audits_status(): void
    {
        $created = $this->postJson('/int/v1/platform/vendor-domains', [
            'company_uuid' => 'company-1',
            'host' => 'verified.example.org',
            'kind' => 'custom',
        ])->assertCreated()->json();

        $txtValue = $created['verification_record']['value'];
        $lookup = new class($txtValue) extends VendorDomainDnsTxtLookup {
            public function __construct(private string $value) {}
            public function recordsFor(string $hostname): array { return [$this->value]; }
        };
        $this->app->instance(VendorDomainDnsTxtLookup::class, $lookup);

        $this->postJson('/int/v1/platform/vendor-domains/1/verify')
            ->assertOk()
            ->assertJsonPath('verified', true)
            ->assertJsonPath('domain.status', 'verified');

        $this->assertSame(1, DB::connection('mysql')->table('vendor_domain_audits')->where('action', 'verified')->count());
        $this->patchJson('/int/v1/platform/vendor-domains/1/status', ['status' => 'disabled'])
            ->assertOk()
            ->assertJsonPath('domain.status', 'disabled');
        $this->assertSame(1, DB::connection('mysql')->table('vendor_domain_audits')->where('action', 'status_changed')->count());
    }

    public function test_dns_verification_rejects_a_mismatched_txt_value(): void
    {
        $this->postJson('/int/v1/platform/vendor-domains', [
            'company_uuid' => 'company-1',
            'host' => 'wrong-proof.example.org',
            'kind' => 'custom',
        ])->assertCreated();

        $this->app->instance(VendorDomainDnsTxtLookup::class, new class extends VendorDomainDnsTxtLookup {
            public function recordsFor(string $hostname): array { return ['kodewaves-domain-verification=wrong']; }
        });

        $this->postJson('/int/v1/platform/vendor-domains/1/verify')
            ->assertStatus(422)
            ->assertJsonPath('message', 'The required DNS TXT verification record was not found.');
        $this->assertSame('pending_dns', VendorDomainClaim::query()->findOrFail(1)->status);
    }

    public function test_non_admin_user_is_denied_by_platform_domain_guard(): void
    {
        config(['auth.defaults.guard' => 'web']);
        $user = new \Fleetbase\Models\User();
        $user->forceFill(['type' => 'vendor']);
        Auth::guard('web')->setUser($user);
        $request = Request::create('/int/v1/platform/vendor-domains');
        $request->setUserResolver(static fn () => $user);

        $response = app(AdminGuard::class)->handle($request, static fn () => response()->json(['allowed' => true]));
        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_verification_route_is_session_protected_and_admin_guarded(): void
    {
        $route = collect(Route::getRoutes())->first(fn ($route) => $route->uri() === 'int/v1/platform/vendor-domains/{id}/verify');
        $this->assertNotNull($route);
        $middleware = $route->middleware();
        $this->assertContains('fleetbase.protected', $middleware);
        $this->assertContains(\Fleetbase\Http\Middleware\AdminGuard::class, $middleware);

        $vendorRoute = collect(Route::getRoutes())->first(fn ($route) => $route->uri() === 'int/v1/vendor/domains/{id}/verify');
        $this->assertNotNull($vendorRoute);
        $vendorMiddleware = $vendorRoute->middleware();
        $this->assertContains('fleetbase.protected', $vendorMiddleware);
        $this->assertNotContains(\Fleetbase\Http\Middleware\AdminGuard::class, $vendorMiddleware);
    }

    public function test_challenge_is_hashed_and_expires(): void
    {
        $created = $this->postJson('/int/v1/platform/vendor-domains', [
            'company_uuid' => 'company-1',
            'host' => 'expiry.example.org',
            'kind' => 'custom',
        ])->assertCreated()->json();

        $challenge = substr($created['verification_record']['value'], strlen('kodewaves-domain-verification='));
        $claim = VendorDomainClaim::query()->findOrFail(1);
        $this->assertNotSame($challenge, $claim->verification_token_hash);
        $this->assertSame(hash('sha256', $challenge), $claim->verification_token_hash);

        $claim->forceFill(['verification_expires_at' => now()->subSecond()])->save();
        $this->postJson('/int/v1/platform/vendor-domains/1/verify')->assertStatus(409);
        $this->assertSame('pending_dns', $claim->fresh()->status);
    }

    public function test_vendor_claim_uses_selected_company_and_rejects_company_override(): void
    {
        $this->asCompanyOwner('company-1', 'vendor-owner', 'active');

        $this->postJson('/int/v1/vendor/domains', [
            'company_uuid' => 'company-2',
            'host' => 'blocked.example.org',
            'kind' => 'custom',
        ])->assertUnprocessable();

        $response = $this->postJson('/int/v1/vendor/domains', [
            'host' => 'vendor.example.org',
            'kind' => 'custom',
        ])->assertCreated();

        $this->assertSame('company-1', $response->json('domain.company_uuid'));
        $this->assertSame(0, DB::connection('mysql')->table('vendor_domain_claims')->where('company_uuid', 'company-2')->count());
    }

    public function test_vendor_list_and_detail_are_scoped_to_selected_company(): void
    {
        $this->asCompanyOwner('company-1', 'vendor-owner', 'active');
        $this->insertClaimFor('company-1', 'own.example.org');
        $this->insertClaimFor('company-2', 'other.example.org');

        $this->getJson('/int/v1/vendor/domains')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.host', 'own.example.org');
        $this->getJson('/int/v1/vendor/domains?company_uuid=company-2')->assertUnprocessable();
        $this->getJson('/int/v1/vendor/domains/2')->assertNotFound();
    }

    public function test_active_non_admin_member_and_inactive_owner_are_denied(): void
    {
        $this->asCompanyOwner('company-1', 'ordinary-member', 'active', 'vendor-owner');
        $this->getJson('/int/v1/vendor/domains')->assertForbidden();

        $this->asCompanyOwner('company-1', 'vendor-owner', 'inactive');
        $this->getJson('/int/v1/vendor/domains')->assertForbidden();
    }

    public function test_company_administrator_role_is_scoped_to_selected_membership(): void
    {
        $this->asCompanyOwner('company-1', 'company-admin', 'active', 'vendor-owner');
        DB::connection('mysql')->table('roles')->insert([
            'id' => 'admin-role', 'name' => 'Administrator', 'guard_name' => 'sanctum', 'company_uuid' => 'company-1',
        ]);
        DB::connection('mysql')->table('model_has_roles')->insert([
            'role_id' => 'admin-role', 'model_type' => \Fleetbase\Models\CompanyUser::class, 'model_uuid' => 'membership-company-1-company-admin',
        ]);

        $this->postJson('/int/v1/vendor/domains', ['host' => 'admin.example.org', 'kind' => 'custom'])->assertCreated();

        $this->asCompanyOwner('company-2', 'company-admin', 'active', 'other-owner');
        $this->getJson('/int/v1/vendor/domains')->assertForbidden();
    }

    private function asCompanyOwner(string $companyUuid, string $userUuid, string $membershipStatus, ?string $ownerUuid = null): void
    {
        DB::connection('mysql')->table('companies')->where('uuid', $companyUuid)->update(['owner_uuid' => $ownerUuid ?? $userUuid, 'status' => 'active']);
        DB::connection('mysql')->table('company_users')->updateOrInsert(
            ['uuid' => 'membership-' . $companyUuid . '-' . $userUuid],
            [
                'company_uuid' => $companyUuid,
                'user_uuid' => $userUuid,
                'status' => $membershipStatus,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $user = new \Fleetbase\Models\User();
        $user->forceFill(['uuid' => $userUuid, 'type' => 'user', 'company_uuid' => $companyUuid]);
        $this->actingAs($user, 'web');
        session(['company' => $companyUuid]);
    }

    private function insertClaimFor(string $companyUuid, string $host): VendorDomainClaim
    {
        return VendorDomainClaim::create([
            'host' => $host,
            'kind' => 'custom',
            'company_uuid' => $companyUuid,
            'status' => 'pending_dns',
            'verification_token_hash' => hash('sha256', 'test-challenge'),
            'verification_expires_at' => now()->addHour(),
        ]);
    }
}
