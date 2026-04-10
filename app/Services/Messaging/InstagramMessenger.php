<?php

namespace App\Services\Messaging;

use App\Models\Prospect;
use App\Models\ProspectMessage;
use App\Services\Messaging\Contracts\MessengerInterface;

class InstagramMessenger implements MessengerInterface
{
    public function send(Prospect $prospect, ProspectMessage $message): array
    {
        if (empty($prospect->instagram_handle)) {
            return ['success' => false, 'error' => 'No hay handle de Instagram', 'channel' => 'instagram'];
        }

        return [
            'success' => false,
            'error' => 'Instagram API no configurada todavia',
            'channel' => 'instagram',
            'metadata' => [
                'handle' => $prospect->instagram_handle,
                'preview' => mb_substr($message->content, 0, 120),
            ],
        ];
    }
}
