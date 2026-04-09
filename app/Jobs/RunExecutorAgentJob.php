<?php

namespace App\Jobs;

use App\Services\Pipeline\AgentOrchestrator;
use App\Models\CampaignRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunExecutorAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 minutes max (sending messages with delays)

    public function __construct(
        public CampaignRun $run,
    ) {
        $this->onQueue('agents');
    }

    public function handle(AgentOrchestrator $orchestrator): void
    {
        $orchestrator->runExecutor($this->run);
    }
}
