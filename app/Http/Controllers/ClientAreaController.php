<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AuthLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ClientAreaController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        AuthLog::debug('Client area viewed', [
            'event' => AuthLog::EVENT_ROUTE_VISIT,
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => 'Area de cliente visitada.',
        ]);

        return view('client.home', [
            'user' => $user,
        ]);
    }
}
