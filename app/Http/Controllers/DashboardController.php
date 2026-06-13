<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AuthLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Muestra el dashboard del usuario autenticado.
     *
     * Renderiza la vista principal del panel de control según el rol del
     * usuario (admin, cliente, etc.). Registra un evento de auditoría con
     * información del usuario y la solicitud.
     *
     * @param  Request $request Solicitud HTTP entrante.
     * @return View Vista del dashboard con los datos del usuario.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        AuthLog::info('Dashboard viewed', [
            'event' => AuthLog::EVENT_ROUTE_VISIT,
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => 'Dashboard visitado por ' . $user->role . ': ' . $user->email . '.',
        ]);

        return view('dashboard', [
            'user' => $user,
        ]);
    }
}
