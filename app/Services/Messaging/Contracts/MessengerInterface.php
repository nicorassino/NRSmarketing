<?php

namespace App\Services\Messaging\Contracts;

use App\Models\Prospect;
use App\Models\ProspectMessage;

interface MessengerInterface
{
    /**
     * Send a message through a specific channel transport.
     *
     * @return array<string, mixed>
     */
    public function send(Prospect $prospect, ProspectMessage $message): array;
}
