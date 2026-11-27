<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlatformWalkthroughRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', Rule::in(['new', 'contacted', 'closed'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = DB::table('walkthrough_requests')->orderByDesc('id');
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return response()->json($query->paginate($filters['per_page'] ?? 25));
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $input = $request->validate(['status' => ['required', Rule::in(['new', 'contacted', 'closed'])]]);
        $lead = DB::table('walkthrough_requests')->where('id', $id)->first();
        abort_unless($lead, 404);

        if ($lead->status !== $input['status']) {
            DB::transaction(function () use ($lead, $input, $request, $id): void {
                $previous = DB::table('walkthrough_requests')->where('id', $id)->lockForUpdate()->value('status');
                DB::table('walkthrough_requests')->where('id', $id)->update([
                    'status' => $input['status'],
                    'updated_at' => now(),
                ]);
                DB::table('walkthrough_request_status_audits')->insert([
                    'walkthrough_request_id' => $id,
                    'from_status' => $previous,
                    'to_status' => $input['status'],
                    'actor_uuid' => $request->user()?->uuid,
                    'created_at' => now(),
                ]);
            });
        }

        return response()->json(['request' => DB::table('walkthrough_requests')->where('id', $id)->first()]);
    }
}
