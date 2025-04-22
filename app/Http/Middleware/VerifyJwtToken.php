<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\HttpFoundation\Response;
use Exception;
use DomainException;

class VerifyJwtToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $token = $request->header('Authorization');
        if (!$token) {
            return response()->json(['error' => 'Token not provided'], 401);
        }

        try {
            $token = str_replace('Bearer ', '', $token);
            $decoded = JWT::decode($token, new Key(config('services.jwt.secret_key'), 'HS256'));
            $request->auth = $decoded;
        } catch (DomainException $e) {
            return response()->json(['error' => 'Invalid token: Malformed or corrupted'], 401);
        } catch (Exception $e) {
            return response()->json(['error' => 'Invalid token: ' . $e->getMessage()], 401);
        }

        return $next($request);
    }
}
