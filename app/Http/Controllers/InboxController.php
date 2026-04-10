<?php

namespace App\Http\Controllers;

use App\Models\ApiUsageLog;
use App\Models\CampaignRun;
use App\Models\Prospect;
use App\Models\ProspectMessage;
use App\Models\ProductMessageTemplate;
use App\Services\AI\GeminiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InboxController extends Controller
{
    public function show(Request $request, CampaignRun $run): View
    {
        $run->load([
            'campaign.product',
            'prospects' => fn ($query) => $query->with(['messages' => fn ($m) => $m->latest()])->orderByDesc('score'),
        ]);

        $contactFilter = $request->string('contact_filter')->toString();
        if (!in_array($contactFilter, ['all', 'with_contact', 'without_contact'], true)) {
            $contactFilter = 'all';
        }

        $prospects = $run->prospects;
        if ($contactFilter === 'with_contact') {
            $prospects = $prospects->filter(fn ($p) => filled($p->phone) || filled($p->email) || filled($p->instagram_handle))->values();
        } elseif ($contactFilter === 'without_contact') {
            $prospects = $prospects->filter(fn ($p) => !filled($p->phone) && !filled($p->email) && !filled($p->instagram_handle))->values();
        }

        $stats = [
            'total' => $prospects->count(),
            'approved' => $prospects->where('status', Prospect::STATUS_APPROVED)->count(),
            'with_channel' => $prospects->where('status', Prospect::STATUS_APPROVED)->whereNotNull('selected_channel')->count(),
            'approved_messages' => $prospects->flatMap->messages->where('status', ProspectMessage::STATUS_APPROVED)->count(),
            'total_all' => $run->prospects->count(),
        ];

        return view('inbox.show', [
            'run' => $run,
            'stats' => $stats,
            'prospects' => $prospects,
            'contactFilter' => $contactFilter,
        ]);
    }

    public function updateProspect(Request $request, Prospect $prospect): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:new,approved,rejected,contacted,converted'],
            'selected_channel' => ['nullable', 'in:whatsapp,email,instagram'],
        ]);

        if ($data['status'] !== Prospect::STATUS_APPROVED) {
            $data['selected_channel'] = null;
        }

        $prospect->update($data);

        return back()->with('status', "Prospecto {$prospect->company_name} actualizado.");
    }

    public function approveMessage(ProspectMessage $message): RedirectResponse
    {
        $message->update(['status' => ProspectMessage::STATUS_APPROVED]);

        return back()->with('status', "Mensaje {$message->channel} aprobado.");
    }

    public function updateMessage(Request $request, ProspectMessage $message): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string', 'min:10'],
        ]);

        $message->update([
            'subject' => $data['subject'] ?? null,
            'content' => $data['content'],
            // Si se edita un mensaje ya aprobado/sent/failed, vuelve a draft para revisión.
            'status' => ProspectMessage::STATUS_DRAFT,
            'ai_inbox_reviewed_at' => null,
            'ai_inbox_suggest_send' => null,
            'ai_inbox_review_notes' => null,
        ]);

        return back()->with('status', "Mensaje {$message->channel} actualizado y marcado como draft.");
    }

    public function reviewDrafts(Request $request, CampaignRun $run, GeminiService $gemini): RedirectResponse
    {
        $data = $request->validate([
            'prospect_ids' => ['required', 'array', 'min:1'],
            'prospect_ids.*' => ['integer'],
            'contact_filter' => ['nullable', 'string', 'in:all,with_contact,without_contact'],
        ], [
            'prospect_ids.required' => 'Seleccioná al menos una empresa.',
            'prospect_ids.min' => 'Seleccioná al menos una empresa.',
        ]);

        $run->load('campaign.product');

        $prospects = Prospect::query()
            ->where('campaign_run_id', $run->id)
            ->whereIn('id', $data['prospect_ids'])
            ->get();

        if ($prospects->isEmpty()) {
            return back()->with('status', 'Ningún prospecto válido para este run.');
        }

        $product = $run->campaign->product;
        $brand = trim((string) ($product?->brand_name ?? '')) ?: '-';
        $productName = trim((string) ($product?->name ?? '')) ?: '-';
        $valueProp = trim((string) ($product?->value_proposition ?? ''));
        if ($valueProp === '') {
            $valueProp = trim((string) ($product?->description ?? '')) ?: '-';
        } else {
            $valueProp = mb_strlen($valueProp) > 400 ? mb_substr($valueProp, 0, 400) . '...' : $valueProp;
        }

        $reviewed = 0;
        $omittedNoChannel = 0;
        $omittedNoDraft = 0;
        $errors = 0;

        $model = config('agents.chat.model');
        $maxTokens = (int) config('agents.chat.max_tokens', 4096);

        foreach ($prospects as $prospect) {
            $channel = $prospect->selected_channel;
            if (!filled($channel) || !in_array($channel, ['whatsapp', 'email', 'instagram'], true)) {
                $omittedNoChannel++;

                continue;
            }

            $message = ProspectMessage::query()
                ->where('prospect_id', $prospect->id)
                ->where('channel', $channel)
                ->where('status', ProspectMessage::STATUS_DRAFT)
                ->first();

            if (!$message) {
                $omittedNoDraft++;

                continue;
            }

            $prompt = $this->buildInboxReviewPrompt(
                companyName: $prospect->company_name,
                channel: $channel,
                subject: $message->subject,
                content: $message->content,
                brand: $brand,
                productName: $productName,
                valuePropSummary: $valueProp,
            );

            $response = $gemini->generate(
                prompt: $prompt,
                model: $model,
                maxTokens: $maxTokens,
                temperature: 0.3,
            );

            if (!$response['success']) {
                $errors++;

                continue;
            }

            $decoded = json_decode($response['content'], true);
            if (!is_array($decoded) && preg_match('/\{[\s\S]*\}/', $response['content'], $matches)) {
                $decoded = json_decode($matches[0], true);
            }

            if (!is_array($decoded) || empty($decoded['content']) || !is_string($decoded['content'])) {
                $errors++;

                continue;
            }

            $newContent = trim($decoded['content']);
            if (mb_strlen($newContent) < 10) {
                $errors++;

                continue;
            }

            $newSubject = $message->subject;
            if ($channel === 'email' && isset($decoded['subject']) && is_string($decoded['subject']) && trim($decoded['subject']) !== '') {
                $newSubject = trim($decoded['subject']);
            }

            $suggestSend = isset($decoded['suggest_send']) ? (bool) $decoded['suggest_send'] : false;
            $notes = isset($decoded['notes']) && is_string($decoded['notes'])
                ? mb_substr(trim($decoded['notes']), 0, 500)
                : null;

            $message->update([
                'content' => $newContent,
                'subject' => $newSubject,
                'ai_inbox_reviewed_at' => now(),
                'ai_inbox_suggest_send' => $suggestSend,
                'ai_inbox_review_notes' => $notes,
            ]);

            ApiUsageLog::create([
                'service' => 'gemini',
                'operation' => 'inbox_draft_review',
                'input_tokens' => $response['input_tokens'] ?? 0,
                'output_tokens' => $response['output_tokens'] ?? 0,
                'cost_usd' => $response['cost_usd'] ?? 0,
                'related_type' => CampaignRun::class,
                'related_id' => $run->id,
                'metadata' => [
                    'prospect_id' => $prospect->id,
                    'channel' => $channel,
                    'prospect_message_id' => $message->id,
                    'model' => $response['model'] ?? $model,
                ],
            ]);

            $reviewed++;
        }

        $parts = ["Revisados: {$reviewed}."];
        if ($omittedNoChannel > 0) {
            $parts[] = "Omitidos sin canal: {$omittedNoChannel}.";
        }
        if ($omittedNoDraft > 0) {
            $parts[] = "Omitidos sin borrador draft en el canal elegido: {$omittedNoDraft}.";
        }
        if ($errors > 0) {
            $parts[] = "Errores IA/parseo: {$errors}.";
        }

        $redirect = back();
        if (!empty($data['contact_filter']) && in_array($data['contact_filter'], ['all', 'with_contact', 'without_contact'], true)) {
            $redirect = redirect()->route('runs.inbox', [
                'run' => $run->id,
                'contact_filter' => $data['contact_filter'],
            ]);
        }

        return $redirect->with('status', implode(' ', $parts));
    }

    private function buildInboxReviewPrompt(
        string $companyName,
        string $channel,
        ?string $subject,
        string $content,
        string $brand,
        string $productName,
        string $valuePropSummary,
    ): string {
        $channelRules = match ($channel) {
            'whatsapp' => 'WhatsApp: mensaje conversacional, parrafos cortos, sin formalidad excesiva, emojis con moderacion (0-2), claro CTA breve. Evita muros de texto.',
            'email' => 'Email: tono profesional, saludo y cierre adecuados, asunto claro y sin spam-words, cuerpo escaneable (parrafos cortos).',
            default => 'Instagram DM: muy breve, tono humano, primera linea que enganche; sin parecer automatizado; emojis leves si suman.',
        };

        $subjectBlock = $channel === 'email'
            ? "Asunto actual:\n" . ($subject ?? '(sin asunto)') . "\n\n"
            : '';

        return <<<PROMPT
Sos un director de marketing senior. Optimizá este borrador de primer contacto B2B para legibilidad, coherencia con el producto y formato del medio.

Canal: {$channel}
{$channelRules}

Empresa destino: {$companyName}
Marca emisora: {$brand}
Producto: {$productName}
Resumen propuesta de valor (referencia): {$valuePropSummary}

{$subjectBlock}Borrador actual (cuerpo):
---
{$content}
---

Tareas:
1) Reescribi el cuerpo si hace falta (mantené intencion comercial y personalizacion hacia {$companyName}).
2) Si el canal es email, proponé tambien un asunto optimizado en "subject". Si no es email, "subject" debe ser null en el JSON.
3) Indicá si lo considerás apto para enviar como primer contacto (suggest_send true/false) con criterio exigente pero practico.
4) En "notes", una o dos frases en español con el motivo principal del veredicto.

Respondé SOLO JSON valido con esta forma exacta:
{
  "content": "texto del cuerpo final",
  "subject": "solo email; string o null",
  "suggest_send": true,
  "notes": "breve"
}
PROMPT;
    }

    public function generateDrafts(CampaignRun $run): RedirectResponse
    {
        $run->load(['campaign.product', 'prospects']);
        $product = $run->campaign->product;
        $templatesByChannel = ProductMessageTemplate::query()
            ->where('product_id', $product->id)
            ->where('is_selected', true)
            ->get()
            ->keyBy('channel');

        $created = 0;
        foreach ($run->prospects as $prospect) {
            $analysisHint = trim((string) ($prospect->ai_analysis ?? ''));
            if (mb_strlen($analysisHint) > 220) {
                $analysisHint = mb_substr($analysisHint, 0, 220) . '...';
            }

            foreach (['whatsapp', 'email', 'instagram'] as $channel) {
                $exists = $prospect->messages()->where('channel', $channel)->exists();
                if ($exists) {
                    continue;
                }

                $content = $this->buildDraftContent(
                    channel: $channel,
                    companyName: $prospect->company_name,
                    brandName: $product->brand_name,
                    productName: $product->name ?? 'nuestra solucion',
                    valueProp: $product->value_proposition,
                    analysisHint: $analysisHint,
                    productDescription: $product->description,
                    selectedTemplate: $templatesByChannel->get($channel)
                );

                ProspectMessage::create([
                    'prospect_id' => $prospect->id,
                    'channel' => $channel,
                    'subject' => $channel === 'email'
                        ? $this->buildDraftSubject($prospect->company_name, $templatesByChannel->get($channel))
                        : null,
                    'content' => $content,
                    'original_ai_content' => $content,
                    'status' => ProspectMessage::STATUS_DRAFT,
                ]);
                $created++;
            }
        }

        return back()->with('status', "Borradores generados: {$created}.");
    }

    private function buildDraftContent(
        string $channel,
        string $companyName,
        ?string $brandName,
        string $productName,
        ?string $valueProp,
        string $analysisHint,
        ?string $productDescription,
        ?ProductMessageTemplate $selectedTemplate
    ): string {
        $brandLabel = trim((string) $brandName);
        if ($brandLabel === '') {
            $brandLabel = 'nuestro equipo';
        }

        $productLabel = trim($productName);
        if ($productLabel === '') {
            $productLabel = 'nuestra solucion';
        }
        if (mb_strlen($productLabel) > 48) {
            $productLabel = mb_substr($productLabel, 0, 48) . '...';
        }

        $value = trim((string) $valueProp);
        if ($value === '') {
            $value = trim((string) $productDescription);
        }
        if ($value === '') {
            $value = 'ayudamos a equipos comerciales y de marketing a mejorar la prospeccion con IA';
        }
        if (mb_strlen($value) > 140) {
            $value = mb_substr($value, 0, 140) . '...';
        }

        $analysisLine = $analysisHint !== ''
            ? Str::of($analysisHint)->replace("\n", ' ')->trim()->limit(140)->toString()
            : 'Vi potencial de mejora comercial en su presencia digital.';

        if ($selectedTemplate) {
            return $this->renderTemplate($selectedTemplate->content, [
                'company_name' => $companyName,
                'brand_name' => $brandLabel,
                'product_name' => $productLabel,
                'value_proposition' => $value,
                'analysis_hint' => $analysisLine,
            ]);
        }

        return match ($channel) {
            'whatsapp' => "Hola {$companyName}! 👋\n\nTe escribo desde {$brandLabel} (producto: {$productLabel}).\nAyudamos a organizaciones como la tuya: {$value}.\n\nViendo su contexto, detecte esto: {$analysisLine} 📌\n\nSi te parece, te comparto una idea concreta en 2 minutos por aca? 🙂",
            'email' => "Hola equipo de {$companyName},\n\nTe escribo desde {$brandLabel} (producto: {$productLabel}).\nAyudamos a organizaciones como la suya: {$value}.\n\nRevisando su contexto, detecte: {$analysisLine}\n\nSi les interesa, les comparto una propuesta breve y accionable para su caso.\n\nSaludos.",
            default => "Hola {$companyName}! 👋\n\nSoy de {$brandLabel} (producto: {$productLabel}).\nAyudamos a organizaciones a: {$value}.\n\nViendo su perfil detecte: {$analysisLine} ✨\n\nSi queres, te mando una idea puntual para su situacion por aca.",
        };
    }

    private function buildDraftSubject(string $companyName, ?ProductMessageTemplate $selectedTemplate): string
    {
        if ($selectedTemplate && filled($selectedTemplate->subject)) {
            return $this->renderTemplate($selectedTemplate->subject, [
                'company_name' => $companyName,
                'brand_name' => 'nuestra marca',
                'product_name' => 'nuestro producto',
                'value_proposition' => 'mejora comercial con IA',
                'analysis_hint' => 'potencial de mejora en prospeccion',
            ]);
        }

        return "Idea para {$companyName}";
    }

    private function renderTemplate(string $template, array $vars): string
    {
        $replace = [];
        foreach ($vars as $key => $value) {
            $replace['{{' . $key . '}}'] = (string) $value;
        }

        return strtr($template, $replace);
    }
}
