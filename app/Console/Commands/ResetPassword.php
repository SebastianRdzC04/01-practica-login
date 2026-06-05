<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RequiresDualTotp;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetPassword extends Command
{
    use RequiresDualTotp;

    protected $signature = 'user:reset-password {email : Email del usuario a resetear}';
    protected $description = 'Resetea la contrasena de un usuario (requiere doble TOTP de aprobacion)';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("Usuario con email '{$email}' no encontrado.");
            return Command::FAILURE;
        }

        $this->warn("ATENCION: Vas a resetear la contrasena de {$user->name} ({$user->role})");
        $this->newLine();

        if (! $this->requireDualTotpApproval("Resetear contrasena de {$user->name} ({$email})")) {
            return Command::FAILURE;
        }

        $newPassword = str()->random(20);

        $user->password = Hash::make($newPassword);
        $user->save();

        $this->newLine();
        $this->info("✓ Contrasena reseteada exitosamente para: {$user->name} ({$email})");
        $this->line("  Nueva contrasena: {$newPassword}");
        $this->warn("  El usuario debe cambiarla en el proximo login.");

        return Command::SUCCESS;
    }
}
