<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User; // Replace with your user model path
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class Authenticate
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
    

        $token = explode(' ', $request->header('Authorization'))[1];

        $user = User::where('api_token', $token)->first(); // Efficient user lookup

        if(!$user){
            return response()->json('Unauthorized Access', 401);
        } else {

            
            $connectedUser = null;

            $connectedUser = json_encode($user);

            if ($connectedUser) {
                $request['user'] = $connectedUser;
                return $next($request);
            } else {
                return response()->json('Unauthorized Access', 401);
            }
        }
    }
}
