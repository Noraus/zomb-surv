<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DevelopmentOnly
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(app()->environment('local', 'testing'), 404);

        return $next($request);
    }
}
