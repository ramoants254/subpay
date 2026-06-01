<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * Get the authenticated user's subscription and billing history.
     */
    public function index(Request $request): JsonResponse
    {
        $subscriptions = $request->user()->subscriptions()
            ->with(['plan', 'charges' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }])
            ->get();

        return response()->json($subscriptions);
    }
}
