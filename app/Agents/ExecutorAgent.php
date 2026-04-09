<?php

namespace App\Agents;

use App\Agents\Contracts\AgentResult;
use App\Models\CampaignRun;
use App\Models\Prospect;
use App\Models\ProspectMessage;

class ExecutorAgent extends BaseAgent
{
    public function getType(): string
    {
        return 'executor';
    }

    public function getRequiredContextFiles(): array
    {
        return ['01_product_analysis', '04_selected_leads'];
    }

    public function getOutputSteps(): array
    {
        return ['05_execution_log'];
    }

    protected function process(CampaignRun $run, array $context): AgentResult
    {
        // Get approved prospects with selected channel and approved messages
        $prospects = Prospect::where('campaign_run_id', $run->id)
            ->where('status', Prospect::STATUS_APPROVED)
            ->whereNotNull('selected_channel')
            ->with('messages')
            ->get();

        if ($prospects->isEmpty()) {
            return AgentResult::failure(
                'No hay prospectos aprobados con canal seleccionado para enviar.',
                'NO_APPROVED_PROSPECTS'
            );
        }

        $results = [];
        $sentCount = 0;
        $failedCount = 0;

        foreach ($prospects as $prospect) {
            // Get the approved message for the selected channel
            $message = $prospect->messages()
                ->where('channel', $prospect->selected_channel)
                ->where('status', ProspectMessage::STATUS_APPROVED)
                ->first();

            if (!$message) {
                $results[] = [
                    'prospect' => $prospect->company_name,
                    'channel' => $prospect->selected_channel,
                    'status' => 'skipped',
                    'reason' => 'No hay mensaje aprobado para este canal',
                ];
                continue;
            }

            // Send via the appropriate channel
            try {
                $deliveryResult = $this->sendMessage($prospect, $message);

                $message->update([
                    'status' => $deliveryResult['success'] ? ProspectMessage::STATUS_SENT : ProspectMessage::STATUS_FAILED,
                    'sent_at' => $deliveryResult['success'] ? now() : null,
                    'delivery_metadata' => $deliveryResult,
                ]);

                $prospect->update([
                    'status' => $deliveryResult['success'] ? Prospect::STATUS_CONTACTED : Prospect::STATUS_APPROVED,
                ]);

                if ($deliveryResult['success']) {
                    $sentCount++;
                } else {
                    $failedCount++;
                }

                $results[] = [
                    'prospect' => $prospect->company_name,
                    'channel' => $prospect->selected_channel,
                    'status' => $deliveryResult['success'] ? 'sent' : 'failed',
                    'details' => $deliveryResult,
                ];

            } catch (\Throwable $e) {
                $failedCount++;
                $results[] = [
                    'prospect' => $prospect->company_name,
                    'channel' => $prospect->selected_channel,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }

            // Delay between messages
            $delay = config('agents.executor.delay_between_messages', 30);
            if ($delay > 0) {
                sleep($delay);
            }
        }

        // Save execution log
        $this->contextManager->writeContextFile(
            $run,
            '05_execution_log',
            [
                'executed_at' => now()->toISOString(),
                'total_prospects' => $prospects->count(),
                'sent' => $sentCount,
                'failed' => $failedCount,
                'results' => $results,
            ]
        );

        return AgentResult::success(
            message: "Ejecución completada: {$sentCount} enviados, {$failedCount} fallidos de {$prospects->count()} prospectos.",
            data: [
                'sent' => $sentCount,
                'failed' => $failedCount,
                'results' => $results,
            ],
        );
    }

    private function sendMessage(Prospect $prospect, ProspectMessage $message): array
    {
        return match ($message->channel) {
            'whatsapp' => $this->sendWhatsApp($prospect, $message),
            'email' => $this->sendEmail($prospect, $message),
            'instagram' => $this->sendInstagram($prospect, $message),
            default => ['success' => false, 'error' => "Canal no soportado: {$message->channel}"],
        };
    }

    private function sendWhatsApp(Prospect $prospect, ProspectMessage $message): array
    {
        // TODO: Implement WhatsApp Cloud API integration
        // For now, return a simulated result
        if (empty($prospect->phone)) {
            return ['success' => false, 'error' => 'No hay número de teléfono'];
        }

        return [
            'success' => false,
            'error' => 'WhatsApp API no configurada todavía',
            'channel' => 'whatsapp',
        ];
    }

    private function sendEmail(Prospect $prospect, ProspectMessage $message): array
    {
        if (empty($prospect->email)) {
            return ['success' => false, 'error' => 'No hay email'];
        }

        try {
            \Illuminate\Support\Facades\Mail::raw($message->content, function ($mail) use ($prospect, $message) {
                $mail->to($prospect->email)
                    ->subject($message->subject ?? 'Solución para ' . $prospect->company_name);
            });

            return ['success' => true, 'channel' => 'email'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'channel' => 'email'];
        }
    }

    private function sendInstagram(Prospect $prospect, ProspectMessage $message): array
    {
        // TODO: Implement Instagram Graph API integration
        if (empty($prospect->instagram_handle)) {
            return ['success' => false, 'error' => 'No hay handle de Instagram'];
        }

        return [
            'success' => false,
            'error' => 'Instagram API no configurada todavía',
            'channel' => 'instagram',
        ];
    }
}
