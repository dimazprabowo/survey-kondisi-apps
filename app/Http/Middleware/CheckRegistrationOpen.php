<?php

namespace App\Http\Middleware;

use App\Helpers\ConfigHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRegistrationOpen
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! ConfigHelper::isRegistrationOpen()) {
            session()->flash('status', ConfigHelper::getRegistrationClosedMessage());

            return redirect()->route('login');
        }

        return $next($request);
    }
}
