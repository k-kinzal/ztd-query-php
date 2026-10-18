<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class ApiAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('sanctum');
        return $next($request);
    }
}
