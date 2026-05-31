<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\LoginLockout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoginLockoutStatusController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $email = (string) $request->query('email', '');

        return response()->json(LoginLockout::state($email, (string) $request->ip()));
    }
}
