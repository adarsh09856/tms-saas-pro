<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PublicWalkthroughRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:160'],
            'company' => ['required', 'string', 'max:180'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'phone' => ['nullable', 'string', 'max:40'],
            'consent' => ['accepted'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Please check the highlighted fields.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        DB::table('walkthrough_requests')->insert([
            'name' => trim($data['name']),
            'company' => trim($data['company']),
            'email' => strtolower(trim($data['email'])),
            'phone' => isset($data['phone']) ? trim($data['phone']) : null,
            'consent_at' => now(),
            'created_at' => now(),
        ]);

        return response()->json(['message' => 'Your walkthrough request has been received.'], 201);
    }
}
