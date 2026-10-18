<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isActive(), 403, 'This account is temporarily suspended.');
        return $next($request);
    }
}
