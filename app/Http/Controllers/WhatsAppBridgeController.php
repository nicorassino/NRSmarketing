<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class WhatsAppBridgeController extends Controller
{
    public function show(): View
    {
        $baseUrl = rtrim((string) config('services.whatsapp_bridge.url'), '/');
        $token = (string) config('services.whatsapp_bridge.token');

        $status = null;
        $error = null;

        if (!$baseUrl || !$token) {
            $error = 'Configura WHATSAPP_BRIDGE_URL y WHATSAPP_BRIDGE_TOKEN en .env';
        } else {
            try {
                $response = Http::timeout((int) config('services.whatsapp_bridge.timeout', 15))
                    ->withToken($token)
                    ->get("{$baseUrl}/session/status");

                if ($response->successful()) {
                    $status = $response->json();
                    // Normalize camelCase response from Node bridge to snake_case used in Blade.
                    if (is_array($status) && isset($status['qrDataUrl']) && !isset($status['qr_data_url'])) {
                        $status['qr_data_url'] = $status['qrDataUrl'];
                    }
                    if (is_array($status) && isset($status['lastUpdatedAt']) && !isset($status['last_updated_at'])) {
                        $status['last_updated_at'] = $status['lastUpdatedAt'];
                    }
                } else {
                    $error = "Bridge respondio HTTP {$response->status()}";
                }
            } catch (ConnectionException $e) {
                $error = 'No se pudo conectar al bridge: ' . $e->getMessage();
            }
        }

        return view('whatsapp.bridge', [
            'status' => $status,
            'error' => $error,
        ]);
    }

    public function start(): RedirectResponse
    {
        $baseUrl = rtrim((string) config('services.whatsapp_bridge.url'), '/');
        $token = (string) config('services.whatsapp_bridge.token');

        if (!$baseUrl || !$token) {
            return back()->with('status', 'Bridge no configurado en .env');
        }

        try {
            $response = Http::timeout((int) config('services.whatsapp_bridge.timeout', 15))
                ->withToken($token)
                ->post("{$baseUrl}/session/start");

            if (!$response->successful()) {
                return back()->with('status', "Error iniciando sesion: HTTP {$response->status()}");
            }
        } catch (ConnectionException $e) {
            return back()->with('status', 'Error de conexion al bridge: ' . $e->getMessage());
        }

        return back()->with('status', 'Solicitud de inicio enviada al bridge.');
    }
}
