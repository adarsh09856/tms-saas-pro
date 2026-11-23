<?php

namespace App\Http\Controllers;

use App\Models\VendorDomainAudit;
use App\Models\VendorDomainClaim;
use App\Services\VendorDomainDnsTxtLookup;
use App\Services\VendorDomainHostValidator;
use Fleetbase\Models\Company;
use Fleetbase\Models\CompanyUser;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Vendor-facing domain management. Company scope always comes from the
 * authenticated console session and an active company membership.
 */
class VendorSelfServiceDomainController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $this->authorizedCompany($request);
        if ($company instanceof JsonResponse) {
            return $company;
        }

        $filters = $request->validate([
            'company_uuid' => ['prohibited'],
            'company' => ['prohibited'],
            'status' => ['sometimes', Rule::in(['pending_dns', 'verified', 'disabled'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = VendorDomainClaim::query()->where('company_uuid', $company->uuid)->orderByDesc('id');
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return response()->json($query->paginate($filters['per_page'] ?? 25));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        if ($error = $this->rejectCompanyOverride($request)) {
            return $error;
        }
        $company = $this->authorizedCompany($request);
        if ($company instanceof JsonResponse) {
            return $company;
        }

        return response()->json($this->claimForCompany($id, $company));
    }

    public function store(Request $request, VendorDomainHostValidator $hosts): JsonResponse
    {
        $company = $this->authorizedCompany($request);
        if ($company instanceof JsonResponse) {
            return $company;
        }

        $input = $request->validate([
            'company_uuid' => ['prohibited'],
            'company' => ['prohibited'],
            'host' => ['required', 'string', 'max:253'],
            'kind' => ['required', Rule::in(['custom', 'platform_subdomain'])],
        ]);
        $host = $hosts->normalize($input['host'], $input['kind']);
        $actor = $request->user();
        $token = Str::random(48);
        $expiresAt = now()->addMinutes(max(5, min(10080, (int) config('vendor-domains.verification_ttl_minutes', 1440))));

        try {
            $claim = DB::transaction(function () use ($company, $host, $input, $actor, $token, $expiresAt) {
                $claim = VendorDomainClaim::create([
                    'host' => $host,
                    'kind' => $input['kind'],
                    'company_uuid' => $company->uuid,
                    'status' => 'pending_dns',
                    'verification_token_hash' => hash('sha256', $token),
                    'verification_expires_at' => $expiresAt,
                    'created_by_uuid' => $actor?->uuid,
                ]);

                $this->audit($claim, 'claimed', $actor?->uuid, ['kind' => $claim->kind]);
                return $claim;
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                return response()->json(['message' => 'This hostname already has a claim.'], 409);
            }
            throw $exception;
        }

        return response()->json([
            'domain' => $claim,
            'verification_record' => [
                'type' => 'TXT',
                'name' => $this->verificationRecordName($claim->host),
                'value' => 'kodewaves-domain-verification=' . $token,
                'expires_at' => $expiresAt->toIso8601String(),
            ],
        ], 201);
    }

    public function verify(Request $request, int $id, VendorDomainDnsTxtLookup $dns): JsonResponse
    {
        if ($error = $this->rejectCompanyOverride($request)) {
            return $error;
        }
        $company = $this->authorizedCompany($request);
        if ($company instanceof JsonResponse) {
            return $company;
        }

        $claim = $this->claimForCompany($id, $company);
        if ($claim->status === 'verified' || $claim->verified_at !== null) {
            return response()->json(['domain' => $claim, 'verified' => true]);
        }
        if ($claim->status !== 'pending_dns' || $claim->verification_expires_at->isPast()) {
            return response()->json(['message' => 'The verification challenge has expired or is no longer active.'], 409);
        }

        $txtName = $this->verificationRecordName($claim->host);
        $expectedHash = $claim->verification_token_hash;
        $verified = false;
        foreach ($dns->recordsFor($txtName) as $record) {
            $prefix = 'kodewaves-domain-verification=';
            if (str_starts_with($record, $prefix) && hash_equals($expectedHash, hash('sha256', substr($record, strlen($prefix))))) {
                $verified = true;
                break;
            }
        }
        if (!$verified) {
            return response()->json([
                'message' => 'The required DNS TXT verification record was not found.',
                'record' => ['type' => 'TXT', 'name' => $txtName],
            ], 422);
        }

        $actor = $request->user();
        DB::transaction(function () use ($claim, $actor) {
            $claim->forceFill([
                'status' => 'verified',
                'verified_at' => now(),
                'verified_by_uuid' => $actor?->uuid,
                'verification_token_hash' => hash('sha256', Str::random(48)),
            ])->save();
            $this->audit($claim, 'verified', $actor?->uuid);
        });

        return response()->json(['domain' => $claim->fresh(), 'verified' => true]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        if ($error = $this->rejectCompanyOverride($request)) {
            return $error;
        }
        $company = $this->authorizedCompany($request);
        if ($company instanceof JsonResponse) {
            return $company;
        }

        $input = $request->validate(['status' => ['required', Rule::in(['verified', 'disabled'])]]);
        $claim = $this->claimForCompany($id, $company);
        if ($input['status'] === 'verified' && $claim->verified_at === null) {
            return response()->json(['message' => 'A domain must pass DNS verification before it can be enabled.'], 409);
        }
        if ($input['status'] === $claim->status) {
            return response()->json(['domain' => $claim]);
        }

        $actor = $request->user();
        DB::transaction(function () use ($claim, $input, $actor) {
            $previous = $claim->status;
            $claim->forceFill(['status' => $input['status']])->save();
            $this->audit($claim, 'status_changed', $actor?->uuid, ['from' => $previous, 'to' => $claim->status]);
        });

        return response()->json(['domain' => $claim->fresh()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($error = $this->rejectCompanyOverride($request)) {
            return $error;
        }
        $company = $this->authorizedCompany($request);
        if ($company instanceof JsonResponse) {
            return $company;
        }

        $claim = $this->claimForCompany($id, $company);
        $actor = $request->user();
        DB::transaction(function () use ($claim, $actor) {
            $this->audit($claim, 'deleted', $actor?->uuid, ['prior_status' => $claim->status]);
            $claim->delete();
        });

        return response()->json(['deleted' => true]);
    }

    private function authorizedCompany(Request $request): Company|JsonResponse
    {
        $user = $request->user();
        $companyUuid = session('company');
        if (!$user || !is_string($companyUuid) || $companyUuid === '') {
            return response()->json(['message' => 'An authenticated company session is required.'], 401);
        }

        $company = Company::query()->where('uuid', $companyUuid)->first();
        if (!$company) {
            return response()->json(['message' => 'The selected company is unavailable.'], 403);
        }

        $membership = CompanyUser::query()
            ->where('company_uuid', $company->uuid)
            ->where('user_uuid', $user->uuid)
            ->where('status', 'active')
            ->first();
        if (!$membership) {
            return response()->json(['message' => 'An active membership in the selected company is required.'], 403);
        }

        $isOwner = $company->owner_uuid === $user->uuid;
        $isCompanyAdministrator = $membership->roles()->where('name', 'Administrator')->exists();
        if (!$isOwner && !$isCompanyAdministrator) {
            return response()->json(['message' => 'Only the company owner or an Administrator can manage domains.'], 403);
        }

        return $company;
    }

    private function rejectCompanyOverride(Request $request): ?JsonResponse
    {
        if ($request->hasAny(['company_uuid', 'company'])) {
            return response()->json(['message' => 'Company context is taken from the active session.'], 422);
        }

        return null;
    }

    private function claimForCompany(int $id, Company $company): VendorDomainClaim
    {
        return VendorDomainClaim::query()
            ->where('company_uuid', $company->uuid)
            ->findOrFail($id);
    }

    private function audit(VendorDomainClaim $claim, string $action, ?string $actorUuid, array $details = []): void
    {
        VendorDomainAudit::create([
            'vendor_domain_claim_id' => $claim->id,
            'host' => $claim->host,
            'company_uuid' => $claim->company_uuid,
            'action' => $action,
            'actor_uuid' => $actorUuid,
            'details' => $details ?: null,
        ]);
    }

    private function verificationRecordName(string $host): string
    {
        return '_kodewaves-domain-verification.' . $host;
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            || str_contains(strtolower($exception->getMessage()), 'unique constraint');
    }
}
