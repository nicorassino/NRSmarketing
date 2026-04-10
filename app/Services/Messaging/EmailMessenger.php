<?php

namespace App\Services\Messaging;

use App\Models\Prospect;
use App\Models\ProspectMessage;
use App\Services\Messaging\Contracts\MessengerInterface;
use Illuminate\Support\Facades\Mail;

class EmailMessenger implements MessengerInterface
{
    public function send(Prospect $prospect, ProspectMessage $message): array
    {
        if (empty($prospect->email)) {
            return ['success' => false, 'error' => 'No hay email', 'channel' => 'email'];
        }

        try {
            Mail::raw($message->content, function ($mail) use ($prospect, $message) {
                $mail->to($prospect->email)
                    ->subject($message->subject ?? 'Solucion para ' . $prospect->company_name);
            });

            return ['success' => true, 'channel' => 'email'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'channel' => 'email'];
        }
    }
}
