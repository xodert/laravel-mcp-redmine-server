<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class InjectRedmineApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Redmine-API-Key');

        if ($apiKey !== null && $apiKey !== '') {
            config(['redmine.api_key' => $apiKey]);
        }

        return $next($request);
    }
}
