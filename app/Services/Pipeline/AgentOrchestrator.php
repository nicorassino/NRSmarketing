<?php

namespace App\Services\Pipeline;

use App\Agents\AnalystAgent;
use App\Agents\ExecutorAgent;
use App\Agents\ScoutAgent;
use App\Models\Campaign;
use App\Models\CampaignRun;
use Illuminate\Support\Facades\Log;

class AgentOrchestrator
{
    public function __construct(
        protected AnalystAgent $analystAgent,
        protected ScoutAgent $scoutAgent,
        protected ExecutorAgent $executorAgent,
    ) {}

    /**
     * Start a new campaign run.
     */
    public function startNewRun(Campaign $campaign): CampaignRun
    {
        $lastRun = $campaign->runs()->orderBy('run_number', 'desc')->first();
        $runNumber = $lastRun ? $lastRun->run_number + 1 : 1;

        $run = CampaignRun::create([
            'campaign_id' => $campaign->id,
            'run_number' => $runNumber,
            'status' => 'running',
            'started_at' => now(),
        ]);

        Log::info("Campaign #{$campaign->id}: Started run #{$runNumber}");

        return $run;
    }

    /**
     * Execute the Analyst Agent.
     */
    public function runAnalyst(CampaignRun $run): array
    {
        $run->campaign->update(['status' => Campaign::STATUS_ANALYZING]);

        $result = $this->analystAgent->execute($run);

        if ($result->success) {
            $run->campaign->update(['status' => Campaign::STATUS_MISSION_REVIEW]);
        }

        return [
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ];
    }

    /**
     * Execute the Scout Agent.
     */
    public function runScout(CampaignRun $run): array
    {
        $run->campaign->update(['status' => Campaign::STATUS_SCOUTING]);

        $result = $this->scoutAgent->execute($run);

        if ($result->success) {
            $run->campaign->update(['status' => Campaign::STATUS_INBOX_REVIEW]);
        }

        return [
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ];
    }

    /**
     * Execute the Executor Agent.
     */
    public function runExecutor(CampaignRun $run): array
    {
        $run->campaign->update(['status' => Campaign::STATUS_EXECUTING]);

        $result = $this->executorAgent->execute($run);

        if ($result->success) {
            $run->campaign->update(['status' => Campaign::STATUS_COMPLETED]);
            $run->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        return [
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
        ];
    }

    /**
     * Restart a specific agent within a run.
     */
    public function restartAgent(string $agentType, CampaignRun $run): array
    {
        return match ($agentType) {
            'analyst' => $this->runAnalyst($run),
            'scout' => $this->runScout($run),
            'executor' => $this->runExecutor($run),
            default => ['success' => false, 'message' => "Agente desconocido: {$agentType}"],
        };
    }

    /**
     * Get the current status of a campaign run.
     */
    public function getRunStatus(CampaignRun $run): array
    {
        $executions = $run->agentExecutions()->orderBy('created_at')->get();
        $contextFiles = $run->contextFiles()->orderBy('step')->get();

        return [
            'run' => $run,
            'campaign_status' => $run->campaign->status,
            'executions' => $executions,
            'context_files' => $contextFiles,
            'prospects_count' => $run->prospects()->count(),
            'approved_count' => $run->prospects()->where('status', 'approved')->count(),
            'contacted_count' => $run->prospects()->where('status', 'contacted')->count(),
        ];
    }
}
