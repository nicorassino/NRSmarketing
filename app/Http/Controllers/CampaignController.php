<?php

namespace App\Http\Controllers;

use App\Jobs\RunAnalystAgentJob;
use App\Jobs\RunExecutorAgentJob;
use App\Jobs\RunScoutAgentJob;
use App\Models\Campaign;
use App\Models\CampaignRun;
use App\Models\ContextFile;
use App\Models\Product;
use App\Models\Prospect;
use App\Models\ProspectMessage;
use App\Services\Pipeline\AgentOrchestrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(): View
    {
        return view('campaigns.index', [
            'campaigns' => Campaign::with(['product', 'latestRun'])->latest()->get(),
            'products' => Product::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'name' => ['required', 'string', 'max:255'],
            'objective' => ['nullable', 'string'],
            'target_niche' => ['nullable', 'string', 'max:255'],
            'target_location' => ['nullable', 'string', 'max:255'],
        ]);

        $campaign = Campaign::create($data + ['status' => Campaign::STATUS_DRAFT]);

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('status', 'Campana creada.');
    }

    public function show(Campaign $campaign): View
    {
        $campaign->load([
            'product',
            'runs' => fn ($query) => $query->latest(),
            'runs.agentExecutions' => fn ($query) => $query->latest(),
            'runs.contextFiles',
        ]);

        return view('campaigns.show', ['campaign' => $campaign]);
    }

    public function startRun(Campaign $campaign, AgentOrchestrator $orchestrator): RedirectResponse
    {
        $run = $orchestrator->startNewRun($campaign);

        RunAnalystAgentJob::dispatch($run);

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('status', "Run #{$run->run_number} iniciado. Analyst en cola.");
    }

    public function runScout(CampaignRun $run): RedirectResponse
    {
        $campaign = $run->campaign;
        $overrides = (array) ($run->metadata['scout_overrides'] ?? []);
        $hasMission = ContextFile::where('campaign_run_id', $run->id)
            ->where('step', '02_scout_mission')
            ->exists();

        $hasLocation = filled($overrides['location'] ?? null) || filled($campaign->target_location);
        $hasTargeting = filled($campaign->target_niche) || filled($campaign->objective);

        if (!$hasMission) {
            return back()->with('status', "Run #{$run->run_number}: falta 02_scout_mission (ejecuta Analyst primero).");
        }

        if (!$hasLocation || !$hasTargeting) {
            return back()->with(
                'status',
                "Run #{$run->run_number}: completa target_location y al menos target_niche u objective antes de ejecutar Scout."
            );
        }

        RunScoutAgentJob::dispatch($run);

        return back()->with('status', "Scout en cola para run #{$run->run_number}.");
    }

    public function updateScoutPayload(Request $request, CampaignRun $run): RedirectResponse
    {
        $data = $request->validate([
            'location' => ['required', 'string', 'max:255'],
            'hl' => ['required', 'string', 'size:2'],
            'gl' => ['required', 'string', 'size:2'],
            'num' => ['required', 'integer', 'min:1', 'max:100'],
            'max_searches' => ['required', 'integer', 'min:1', 'max:25'],
        ]);

        $metadata = $run->metadata ?? [];
        $metadata['scout_overrides'] = [
            'location' => $data['location'],
            'hl' => strtolower($data['hl']),
            'gl' => strtolower($data['gl']),
            'num' => (int) $data['num'],
            'max_searches' => (int) $data['max_searches'],
        ];

        $run->update(['metadata' => $metadata]);

        return back()->with('status', "Payload Scout actualizado para run #{$run->run_number}.");
    }

    public function runExecutor(CampaignRun $run): RedirectResponse
    {
        $eligibleProspects = Prospect::where('campaign_run_id', $run->id)
            ->where('status', Prospect::STATUS_APPROVED)
            ->whereNotNull('selected_channel')
            ->get();

        if ($eligibleProspects->isEmpty()) {
            return back()->with('status', "Run #{$run->run_number}: no hay prospectos aprobados con canal seleccionado.");
        }

        $withSendableMessages = 0;
        foreach ($eligibleProspects as $prospect) {
            $hasMessage = ProspectMessage::where('prospect_id', $prospect->id)
                ->where('channel', $prospect->selected_channel)
                ->whereIn('status', [
                    ProspectMessage::STATUS_APPROVED,
                    ProspectMessage::STATUS_FAILED,
                ])
                ->exists();
            if ($hasMessage) {
                $withSendableMessages++;
            }
        }

        if ($withSendableMessages === 0) {
            return back()->with(
                'status',
                "Run #{$run->run_number}: hay prospectos aprobados, pero ningun mensaje listo para este canal (aprobado o fallido para reintento)."
            );
        }

        RunExecutorAgentJob::dispatch($run);

        return back()->with(
            'status',
            "Executor en cola para run #{$run->run_number}. Prospectos listos para enviar: {$withSendableMessages}."
        );
    }

    public function debugRun(CampaignRun $run): View
    {
        $run->load(['campaign.product', 'agentExecutions']);

        $searchContext = ContextFile::where('campaign_run_id', $run->id)
            ->where('step', '03_search_results')
            ->first();

        $executionContext = ContextFile::where('campaign_run_id', $run->id)
            ->where('step', '05_execution_log')
            ->first();

        $searchData = $searchContext?->getContent();
        $executionData = $executionContext?->getContent();

        if (!is_array($searchData)) {
            $searchData = [];
        }

        if (!is_array($executionData)) {
            $executionData = [];
        }

        return view('campaigns.run-debug', [
            'run' => $run,
            'searchData' => $searchData,
            'executionData' => $executionData,
        ]);
    }
}
