<?php

namespace App\Http\Controllers;

use App\Http\Util\MailHelper;
use Illuminate\View\View;

class MailTestController extends Controller
{
    /**
     * Envia um e-mail de teste e exibe o resultado (sucesso ou erro) na tela.
     */
    public function __invoke(): View
    {
        $to = 'luispimenta.contato@gmail.com';
        $success = false;
        $message = null;
        $error = null;

        try {
            MailHelper::emailLead([
                'nome' => 'Luis Pimenta',
                'tituloMaterial' => 'E-mail de teste — Missão Nomeação',
                'url' => url('/'),
            ], $to);

            $success = true;
            $message = "E-mail de teste enviado com sucesso para {$to}.";
        } catch (\Throwable $e) {
            $success = false;
            $message = 'Falha ao enviar o e-mail de teste.';
            $error = $e->getMessage();
        }

        return view('mail-test', [
            'success' => $success,
            'message' => $message,
            'error' => $error,
            'to' => $to,
            'mailer' => config('mail.default'),
            'from' => config('mail.from.address'),
            'host' => config('mail.mailers.smtp.host'),
        ]);
    }
}
