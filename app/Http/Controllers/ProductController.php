<?php

namespace App\Http\Controllers;

use App\Jobs\RunAnalystAgentJob;
use App\Models\Campaign;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductMessageTemplate;
use App\Services\AI\GeminiService;
use App\Services\Pipeline\AgentOrchestrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('products.index', [
            'products' => Product::withCount('documents')->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'value_proposition' => ['nullable', 'string'],
        ]);

        Product::create($data + ['status' => 'active']);

        return back()->with('status', 'Producto creado.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'value_proposition' => ['nullable', 'string'],
            'status' => ['required', 'in:active,archived'],
        ]);

        $product->update($data);

        return back()->with('status', 'Producto actualizado.');
    }

    public function generatePositioning(Product $product, GeminiService $gemini): RedirectResponse
    {
        $product->load('documents');

        $contextParts = [];
        if ($product->description) {
            $contextParts[] = "Descripcion actual:\n" . $product->description;
        }

        foreach ($product->documents as $doc) {
            if (!empty($doc->extracted_text)) {
                $contextParts[] = "Documento {$doc->title}:\n" . $doc->extracted_text;
            }
        }

        if (empty($contextParts)) {
            return back()->with('status', 'No hay contexto suficiente (sube archivos o pega texto primero).');
        }

        $brand = trim((string) $product->brand_name);
        $productName = trim((string) $product->name);

        $prompt = <<<PROMPT
Quiero que generes contenido comercial para un software.

Marca: {$brand}
Producto: {$productName}

Contexto disponible:
{$this->truncateContext(implode("\n\n---\n\n", $contextParts))}

Responde SOLO en JSON válido con esta estructura:
{
  "description": "descripcion clara del producto (120-220 palabras)",
  "value_proposition": "propuesta de valor concreta (50-120 palabras)"
}
PROMPT;

        $response = $gemini->generate(
            prompt: $prompt,
            model: config('agents.analyst.model'),
            maxTokens: 2048,
            temperature: 0.4,
        );

        if (!$response['success']) {
            return back()->with('status', 'Error Gemini al generar posicionamiento: ' . ($response['error'] ?? 'desconocido'));
        }

        $decoded = json_decode($response['content'], true);
        if (!is_array($decoded)) {
            if (preg_match('/\{.*\}/s', $response['content'], $matches)) {
                $decoded = json_decode($matches[0], true);
            }
        }

        if (!is_array($decoded)) {
            return back()->with('status', 'Gemini respondio en formato no parseable. Reintenta.');
        }

        $product->update([
            'description' => $decoded['description'] ?? $product->description,
            'value_proposition' => $decoded['value_proposition'] ?? $product->value_proposition,
        ]);

        return back()->with('status', 'Descripcion y propuesta generadas por Gemini Pro (editables).');
    }

    public function show(Product $product): View
    {
        $product->load([
            'documents',
            'messageTemplates',
            'campaigns' => fn ($query) => $query->latest()->with('latestRun'),
        ]);

        return view('products.show', ['product' => $product]);
    }

    public function generateMessageTemplates(Product $product, GeminiService $gemini): RedirectResponse
    {
        $product->load('documents');

        $contextParts = [];
        if ($product->description) {
            $contextParts[] = "Descripcion del producto:\n{$product->description}";
        }
        if ($product->value_proposition) {
            $contextParts[] = "Propuesta de valor:\n{$product->value_proposition}";
        }
        foreach ($product->documents as $doc) {
            if (!empty($doc->extracted_text)) {
                $contextParts[] = "Contexto {$doc->title}:\n{$doc->extracted_text}";
            }
        }

        if (empty($contextParts)) {
            return back()->with('status', 'No hay contexto suficiente para generar plantillas.');
        }

        $brand = trim((string) $product->brand_name) ?: 'nuestra marca';
        $productName = trim((string) $product->name) ?: 'nuestro producto';
        $context = $this->truncateContext(implode("\n\n---\n\n", $contextParts));

        $prompt = <<<PROMPT
Actua como estratega senior de marketing B2B.
Necesito plantillas base de mensajes para prospeccion general.

Marca: {$brand}
Producto: {$productName}
Contexto:
{$context}

Genera 3 opciones para cada canal (whatsapp, instagram, email).
Deben ser profesionales, claras, y pensadas para primer contacto.
WhatsApp e Instagram con estructura natural para DM y emojis moderados.

Usa placeholders:
- {{company_name}}
- {{brand_name}}
- {{product_name}}
- {{value_proposition}}
- {{analysis_hint}}

Devuelve SOLO JSON valido con formato:
{
  "whatsapp": [{"name":"...", "content":"..."}],
  "instagram": [{"name":"...", "content":"..."}],
  "email": [{"name":"...", "subject":"...", "content":"..."}]
}
PROMPT;

        $response = $gemini->generate(
            prompt: $prompt,
            model: config('agents.analyst.model'),
            maxTokens: 4096,
            temperature: 0.6,
        );

        if (!$response['success']) {
            return back()->with('status', 'Error al generar plantillas: ' . ($response['error'] ?? 'desconocido'));
        }

        $decoded = json_decode($response['content'], true);
        if (!is_array($decoded) && preg_match('/\{.*\}/s', $response['content'], $matches)) {
            $decoded = json_decode($matches[0], true);
        }
        if (!is_array($decoded)) {
            return back()->with('status', 'No se pudo parsear la respuesta de Gemini para plantillas.');
        }

        $inserted = 0;
        foreach (['whatsapp', 'instagram', 'email'] as $channel) {
            $options = $decoded[$channel] ?? [];
            if (!is_array($options)) {
                continue;
            }

            foreach ($options as $idx => $opt) {
                if (!is_array($opt) || empty($opt['content'])) {
                    continue;
                }

                ProductMessageTemplate::create([
                    'product_id' => $product->id,
                    'channel' => $channel,
                    'name' => $opt['name'] ?? strtoupper($channel) . ' Opcion ' . ($idx + 1),
                    'subject' => $channel === 'email' ? ($opt['subject'] ?? 'Propuesta para {{company_name}}') : null,
                    'content' => $opt['content'],
                    'is_selected' => false,
                ]);
                $inserted++;
            }
        }

        return back()->with('status', "Plantillas generadas: {$inserted}. Elige una por canal.");
    }

    public function updateMessageTemplate(Request $request, ProductMessageTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string', 'min:20'],
        ]);

        $template->update([
            'name' => $data['name'],
            'subject' => $data['subject'] ?? null,
            'content' => $data['content'],
        ]);

        return back()->with('status', 'Plantilla actualizada.');
    }

    public function selectMessageTemplate(ProductMessageTemplate $template): RedirectResponse
    {
        ProductMessageTemplate::where('product_id', $template->product_id)
            ->where('channel', $template->channel)
            ->update(['is_selected' => false]);

        $template->update(['is_selected' => true]);

        return back()->with('status', "Plantilla seleccionada para {$template->channel}.");
    }

    public function uploadDocument(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:manual,brochure,specs,other'],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $file = $data['file'];
        $storedPath = $file->store("products/{$product->id}", 'local');

        ProductDocument::create([
            'product_id' => $product->id,
            'title' => $data['title'],
            'file_path' => $storedPath,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'type' => $data['type'],
            'extracted_text' => $this->extractTextForAnalysis($storedPath, $file->getClientMimeType()),
        ]);

        return back()->with('status', 'Documento cargado.');
    }

    public function analyze(Product $product, AgentOrchestrator $orchestrator): RedirectResponse
    {
        $campaign = $product->campaigns()->latest()->first();

        if (!$campaign) {
            $campaign = Campaign::create([
                'product_id' => $product->id,
                'name' => 'Analisis de ' . $product->name,
                'objective' => 'Analizar producto y generar mision inicial Scout',
                'status' => Campaign::STATUS_DRAFT,
            ]);
        }

        $run = $orchestrator->startNewRun($campaign);
        RunAnalystAgentJob::dispatch($run);

        return redirect()
            ->route('products.show', $product)
            ->with('status', "Analyst en cola para run #{$run->run_number}.");
    }

    public function storeTextContext(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:manual,brochure,specs,other'],
            'content' => ['required', 'string', 'min:20'],
        ]);

        ProductDocument::create([
            'product_id' => $product->id,
            'title' => $data['title'],
            'file_path' => 'inline://' . Str::uuid()->toString() . '.txt',
            'original_filename' => 'inline-context.txt',
            'mime_type' => 'text/plain',
            'file_size' => strlen($data['content']),
            'type' => $data['type'],
            'extracted_text' => $data['content'],
        ]);

        return back()->with('status', 'Contexto en texto agregado al producto.');
    }

    private function extractTextForAnalysis(string $storedPath, ?string $mimeType): ?string
    {
        // Minimal extraction: plain text-like files are indexed directly.
        $textMimes = [
            'text/plain',
            'text/markdown',
            'application/json',
            'text/csv',
        ];

        if ($mimeType && in_array($mimeType, $textMimes, true)) {
            return Storage::disk('local')->get($storedPath);
        }

        return null;
    }

    private function truncateContext(string $context): string
    {
        return mb_strlen($context) > 15000
            ? mb_substr($context, 0, 15000) . "\n\n[Contexto truncado por limite de tokens]"
            : $context;
    }
}
