<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Contracts\AI\AiProvider;
use App\Services\AI\Providers\OpenAiResponsesClient;
use RuntimeException;

class AiProviderResolver
{
    public function __construct(
        private OpenAiResponsesClient $openAi
    ) {
    }

    public function resolve(
        string $provider
    ): AiProvider {
        return match (
            strtolower(trim($provider))
        ) {
            'openai' => $this->openAi,
            default => throw new RuntimeException(
                'Unsupported AI provider: '
                . $provider
            ),
        };
    }
}
