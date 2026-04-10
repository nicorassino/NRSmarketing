<?php

namespace App\Http\Controllers;

use App\Models\ApiUsageLog;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Product;
use App\Services\AI\GeminiService;
use App\Services\Context\ContextManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductChatController extends Controller
{
    public function show(Product $product): View
    {
        $conversation = $this->resolveConversation($product);
        $messages = $conversation->messages()->orderBy('created_at')->get();

        return view('chat.show', [
            'product' => $product,
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    public function send(
        Request $request,
        Product $product,
        GeminiService $gemini,
        ContextManager $contextManager,
    ): RedirectResponse {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:10000'],
        ]);

        $conversation = $this->resolveConversation($product);
        $history = $conversation->messages()
            ->orderBy('created_at')
            ->get(['role', 'content'])
            ->toArray();

        $contextText = $this->buildProductContext($product, $contextManager);

        ChatMessage::create([
            'chat_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $data['content'],
            'context_files_used' => [],
        ]);

        $history[] = [
            'role' => 'user',
            'content' => $data['content'] . "\n\nContexto operativo del producto:\n" . $contextText,
        ];

        $response = $gemini->chat(
            messages: $history,
            model: config('agents.chat.model'),
            maxTokens: (int) config('agents.chat.max_tokens', 4096),
            temperature: (float) config('agents.chat.temperature', 0.7),
            systemPrompt: config('agents.chat.system_prompt'),
        );

        if (!$response['success']) {
            return back()->with('status', 'Error al consultar Gemini: ' . ($response['error'] ?? 'desconocido'));
        }

        ChatMessage::create([
            'chat_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $response['content'],
            'context_files_used' => ['product_context'],
            'input_tokens' => $response['input_tokens'] ?? 0,
            'output_tokens' => $response['output_tokens'] ?? 0,
            'cost_usd' => $response['cost_usd'] ?? 0,
        ]);

        ApiUsageLog::create([
            'service' => 'gemini',
            'operation' => 'product_chat',
            'input_tokens' => $response['input_tokens'] ?? 0,
            'output_tokens' => $response['output_tokens'] ?? 0,
            'cost_usd' => $response['cost_usd'] ?? 0,
            'related_type' => Product::class,
            'related_id' => $product->id,
            'metadata' => [
                'conversation_id' => $conversation->id,
                'model' => $response['model'] ?? null,
            ],
        ]);

        return redirect()
            ->route('products.chat.show', $product)
            ->with('status', 'Mensaje enviado.');
    }

    private function resolveConversation(Product $product): ChatConversation
    {
        return ChatConversation::firstOrCreate(
            [
                'product_id' => $product->id,
                'campaign_id' => null,
            ],
            [
                'title' => "Chat {$product->name}",
            ]
        );
    }

    private function buildProductContext(Product $product, ContextManager $contextManager): string
    {
        $campaign = $product->campaigns()->with('latestRun')->latest()->first();
        if (!$campaign || !$campaign->latestRun) {
            return "No hay contexto de runs disponible para este producto.";
        }

        return $contextManager->formatContextForAI($campaign->latestRun);
    }
}
