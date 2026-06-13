<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AuthLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ClientAreaController extends Controller
{
    /**
     * Muestra el área de cliente del usuario autenticado.
     *
     * Renderiza la vista principal del panel de cliente, diseñada
     * exclusivamente para usuarios con rol de cliente. Registra un
     * evento de auditoría con información del usuario y la solicitud.
     *
     * @param  Request $request Solicitud HTTP entrante.
     * @return View Vista del área de cliente con los datos del usuario.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        AuthLog::info('Client area viewed', [
            'event' => AuthLog::EVENT_ROUTE_VISIT,
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'message' => 'Area de cliente visitada por: ' . $user->email . '.',
        ]);

        return view('client.home', [
            'user' => $user,
        ]);
    }
}
