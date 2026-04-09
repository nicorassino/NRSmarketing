<?php

namespace App\Agents\Contracts;

use App\Models\CampaignRun;

interface AgentInterface
{
    /**
     * Execute the agent's task.
     *
     * @param CampaignRun $run The campaign run context
     * @param array $context Previously generated context data
     * @return AgentResult The result of the execution
     */
    public function execute(CampaignRun $run, array $context = []): AgentResult;

    /**
     * Get the context file steps this agent requires as input.
     */
    public function getRequiredContextFiles(): array;

    /**
     * Get the context file step this agent produces as output.
     */
    public function getOutputSteps(): array;

    /**
     * Get the agent type identifier.
     */
    public function getType(): string;
}
