<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class ShowTotpSetup extends Command
{
    protected $signature = 'user:show-totp-setup';
    protected $description = 'Muestra los QR para configurar los TOTP de aprobacion dual';

    public function handle(Google2FA $google2fa): int
    {
        $approvers = config('totp-approvers.approvers');

        if (empty($approvers) || empty($approvers[0]['secret']) || empty($approvers[1]['secret'])) {
            $this->error('Los secretos de los aprobadores no estan configurados en .env');
            $this->line('Agrega:');
            $this->line('  APPROVER_1_NAME="Tu"');
            $this->line('  APPROVER_1_SECRET=...');
            $this->line('  APPROVER_2_NAME="Compañero"');
            $this->line('  APPROVER_2_SECRET=...');
            return Command::FAILURE;
        }

        $renderer = new ImageRenderer(new RendererStyle(300), new SvgImageBackEnd());
        $writer = new Writer($renderer);

        foreach ($approvers as $i => $approver) {
            $label = $approver['name'] ?? "Aprobador " . ($i + 1);
            $email = 'approver' . ($i + 1) . '@sistema.local';
            $url = $google2fa->getQRCodeUrl('Sistema Seguro', $email, $approver['secret']);

            $svg = $writer->writeString($url);
            $base64 = base64_encode($svg);

            $this->info("=== {$label} ===");
            $this->line("Secret: {$approver['secret']}");
            $this->line("Escanea este codigo QR con Google Authenticator:");
            $this->line("<img src='data:image/svg+xml;base64,{$base64}' />");
            $this->newLine();
        }

        $this->warn('Escanea AMBOS codigos en tu telefono antes de probar el comando.');
        $this->warn('Luego ejecuta: php artisan user:reset-password <email>');

        return Command::SUCCESS;
    }
}
