<?php

namespace App\Jobs;

use App\Services\Pipeline\AgentOrchestrator;
use App\Models\CampaignRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunScoutAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes max (multiple API calls)

    public function __construct(
        public CampaignRun $run,
    ) {
        $this->onQueue('agents');
    }

    public function handle(AgentOrchestrator $orchestrator): void
    {
        $orchestrator->runScout($this->run);
    }
}
