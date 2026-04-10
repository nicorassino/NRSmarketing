<?php

namespace App\Services\Messaging;

use App\Models\Prospect;
use App\Models\ProspectMessage;
use App\Services\Messaging\Contracts\MessengerInterface;
use Illuminate\Support\Facades\Http;

class WhatsAppBridgeMessenger implements MessengerInterface
{
    public function send(Prospect $prospect, ProspectMessage $message): array
    {
        if (empty($prospect->phone)) {
            return ['success' => false, 'error' => 'No hay numero de telefono', 'channel' => 'whatsapp'];
        }

        $baseUrl = config('services.whatsapp_bridge.url');
        $token = config('services.whatsapp_bridge.token');
        $timeout = (int) config('services.whatsapp_bridge.timeout', 15);

        if (empty($baseUrl) || empty($token)) {
            return [
                'success' => false,
                'error' => 'WhatsApp bridge no configurado',
                'channel' => 'whatsapp',
            ];
        }

        $to = $this->normalizePhone((string) $prospect->phone);
        if ($to === null) {
            return [
                'success' => false,
                'error' => 'Numero de telefono invalido para WhatsApp bridge',
                'channel' => 'whatsapp',
            ];
        }

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ])
                ->post(rtrim($baseUrl, '/') . '/send', [
                    'to' => $to,
                    'text' => $message->content,
                    'external_id' => (string) $message->id,
                    'company' => $prospect->company_name,
                ]);

            $data = $response->json();
            if (!is_array($data)) {
                $data = ['raw' => $response->body()];
            }

            if (!$response->successful()) {
                $err = $data['error'] ?? null;
                if (is_array($err)) {
                    $err = json_encode($err, JSON_UNESCAPED_UNICODE);
                }
                if ($err === null || $err === '') {
                    $err = 'Bridge error HTTP ' . $response->status();
                }

                return [
                    'success' => false,
                    'error' => $err,
                    'channel' => 'whatsapp',
                    'http_status' => $response->status(),
                    'response' => $data,
                ];
            }

            if (isset($data['ok']) && $data['ok'] === false) {
                $err = $data['error'] ?? 'Bridge respondio ok=false';
                if (is_array($err)) {
                    $err = json_encode($err, JSON_UNESCAPED_UNICODE);
                }

                return [
                    'success' => false,
                    'error' => $err,
                    'channel' => 'whatsapp',
                    'http_status' => $response->status(),
                    'response' => $data,
                ];
            }

            return [
                'success' => true,
                'channel' => 'whatsapp',
                'bridge_message_id' => $data['message_id'] ?? null,
                'response' => $data,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'channel' => 'whatsapp',
            ];
        }
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (!is_string($digits) || strlen($digits) < 8) {
            return null;
        }

        return $digits;
    }
}
