<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Store;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $userData = json_decode($request->user, true);
        $user_id = $userData['id'];

        $storeId = $request->header('X-Store-ID');

        if (!$storeId) {
            return response()->json(['message' => 'Store ID not provided.'], 400);
        }

        $store = Store::where('id', $storeId)->first();

        if (!$store) {
            return response()->json(['message' => 'You must create a store first.'], 403);
        } else {
            $request['store'] = json_encode($store);
            return $next($request);
        }
    }
}