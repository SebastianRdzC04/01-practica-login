<?php

namespace App\Console\Commands\Concerns;

use PragmaRX\Google2FA\Google2FA;

trait RequiresDualTotp
{
    protected function requireDualTotpApproval(?string $purpose = null): bool
    {
        $approvers = config('totp-approvers.approvers');

        if (count($approvers) < 2 || empty($approvers[0]['secret']) || empty($approvers[1]['secret'])) {
            $this->error('Los dos TOTP aprobadores no estan configurados.');
            $this->line('Configura APPROVER_1_SECRET y APPROVER_2_SECRET en .env');
            return false;
        }

        if ($purpose) {
            $this->warn("Se requiere doble aprobacion TOTP para: {$purpose}");
            $this->newLine();
        }

        $google2fa = app(Google2FA::class);

        foreach ($approvers as $i => $approver) {
            $label = $approver['name'] ?? 'Aprobador '.($i + 1);
            $code = $this->secret("TOTP de {$label} (codigo de 6 digitos)");

            if (! $google2fa->verifyKey($approver['secret'], $code, 2)) {
                $this->error("Codigo TOTP invalido para {$label}. Operacion cancelada.");
                return false;
            }

            $this->info("✓ TOTP de {$label} verificado.");
        }

        return true;
    }
}
