<?php

namespace App\Agents\Contracts;

class AgentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly array $data = [],
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly float $costUsd = 0,
        public readonly ?string $error = null,
    ) {}

    public static function success(string $message, array $data = [], int $inputTokens = 0, int $outputTokens = 0, float $costUsd = 0): self
    {
        return new self(
            success: true,
            message: $message,
            data: $data,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            costUsd: $costUsd,
        );
    }

    public static function failure(string $message, ?string $error = null): self
    {
        return new self(
            success: false,
            message: $message,
            error: $error,
        );
    }
}
