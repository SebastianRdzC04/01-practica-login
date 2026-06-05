<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsurePendingAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            return $next($request);
        }

        $pendingUserId = $request->session()->get('pending_auth_user_id');
        if ($pendingUserId) {
            Auth::onceUsingId($pendingUserId);
            return $next($request);
        }

        return redirect()->route('login');
    }
}
