<?php

namespace App\Services\Context;

use App\Models\CampaignRun;
use App\Models\ContextFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ContextManager
{
    /**
     * Get the base path for context files.
     */
    public function getBasePath(): string
    {
        return base_path(config('agents.context.base_path', 'context'));
    }

    /**
     * Get the full directory path for a campaign run.
     */
    public function getRunPath(CampaignRun $run): string
    {
        return sprintf(
            '%s/campaign_%03d/run_%03d',
            $this->getBasePath(),
            $run->campaign_id,
            $run->run_number
        );
    }

    /**
     * Write a context file and register it in the database.
     */
    public function writeContextFile(CampaignRun $run, string $step, mixed $content): ContextFile
    {
        $stepConfig = config("agents.context.steps.{$step}");
        $format = $stepConfig['format'] ?? 'md';

        $dirPath = $this->getRunPath($run);
        $fileName = "{$step}.{$format}";
        $filePath = "{$dirPath}/{$fileName}";
        $relativePath = str_replace(base_path() . '/', '', $filePath);
        $relativePath = str_replace(base_path() . '\\', '', $relativePath);

        // Ensure directory exists
        if (!File::isDirectory($dirPath)) {
            File::makeDirectory($dirPath, 0755, true);
        }

        // Write content
        if ($format === 'json') {
            $fileContent = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $fileContent = is_string($content) ? $content : json_encode($content, JSON_UNESCAPED_UNICODE);
        }

        File::put($filePath, $fileContent);

        // Create or update DB record
        $contextFile = ContextFile::updateOrCreate(
            [
                'campaign_run_id' => $run->id,
                'step' => $step,
            ],
            [
                'file_path' => $relativePath,
                'format' => $format,
                'summary' => $this->generateSummary($content),
            ]
        );

        Log::info("Context file written: {$relativePath}");

        return $contextFile;
    }

    /**
     * Read a context file.
     */
    public function readContextFile(CampaignRun $run, string $step): string|array|null
    {
        $contextFile = ContextFile::where('campaign_run_id', $run->id)
            ->where('step', $step)
            ->first();

        if (!$contextFile) {
            return null;
        }

        return $contextFile->getContent();
    }

    /**
     * Load ALL context files for a run (used by Global Chat).
     */
    public function loadFullContext(CampaignRun $run): array
    {
        $contextFiles = ContextFile::where('campaign_run_id', $run->id)
            ->orderBy('step')
            ->get();

        $context = [];

        foreach ($contextFiles as $file) {
            $content = $file->getContent();
            if ($content !== null) {
                $context[$file->step] = [
                    'content' => $content,
                    'format' => $file->format,
                    'summary' => $file->summary,
                    'updated_at' => $file->updated_at->toISOString(),
                ];
            }
        }

        return $context;
    }

    /**
     * Update an existing context file.
     */
    public function updateContextFile(ContextFile $file, mixed $newContent): void
    {
        $fullPath = $file->full_path;

        if ($file->format === 'json') {
            $fileContent = json_encode($newContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $fileContent = is_string($newContent) ? $newContent : json_encode($newContent, JSON_UNESCAPED_UNICODE);
        }

        File::put($fullPath, $fileContent);

        $file->update([
            'summary' => $this->generateSummary($newContent),
        ]);

        Log::info("Context file updated: {$file->file_path}");
    }

    /**
     * Format full context as text for AI consumption.
     */
    public function formatContextForAI(CampaignRun $run): string
    {
        $context = $this->loadFullContext($run);

        if (empty($context)) {
            return "No hay archivos de contexto generados aún para este run.";
        }

        $formatted = "# Archivos de Contexto - Campaña #{$run->campaign_id}, Run #{$run->run_number}\n\n";

        foreach ($context as $step => $data) {
            $formatted .= "## {$step}\n";
            $formatted .= "Última actualización: {$data['updated_at']}\n\n";

            if (is_array($data['content'])) {
                $formatted .= "```json\n" . json_encode($data['content'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n```\n\n";
            } else {
                $formatted .= $data['content'] . "\n\n";
            }

            $formatted .= "---\n\n";
        }

        return $formatted;
    }

    /**
     * Generate a brief summary of content.
     */
    private function generateSummary(mixed $content): string
    {
        if (is_array($content)) {
            $keys = array_keys($content);
            return 'JSON con claves: ' . implode(', ', array_slice($keys, 0, 5));
        }

        if (is_string($content)) {
            return mb_substr(strip_tags($content), 0, 200) . '...';
        }

        return 'Contenido generado';
    }
}
