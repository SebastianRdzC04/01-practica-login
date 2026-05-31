<?php

namespace App\Http\Controllers;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('dashboard', [
            'user' => $user,
            'loginLogs' => $user->hasRole(User::ROLE_LOGGER)
                ? LoginLog::query()->latest()->paginate(15)
                : null,
        ]);
    }
}
