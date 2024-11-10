<?php

namespace App\Http\Middleware;

use Closure;

class CorsMiddleware
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        $frontendUrl = env('ORIGIN'); // add this line
        $response->headers->set('Access-Control-Allow-Credentials', 'true'); //set this if you are using axios
        $response->headers->set('Access-Control-Allow-Origin', $frontendUrl);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept, Authorization, X-Requested-With');

        return $response;
    }
}
