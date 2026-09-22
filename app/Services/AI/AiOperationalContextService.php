<?php

declare(strict_types=1);

namespace App\Services\AI;

use RuntimeException;

/**
 * Retained only to fail closed for older callers.
 *
 * The global operational snapshot could combine data across stores. All
 * authorized AI context must now pass through the scoped service with an
 * authenticated operator, explicit store, and validated reporting dates.
 */
class AiOperationalContextService
{
    public const CONTEXT_TYPE = 'mission_control_operational_snapshot_v1';

    /**
     * @return never
     */
    public function previewForAgent(int $agentId): array
    {
        throw new RuntimeException(
            'Unscoped AI operational context is disabled. Select one authorized store and reporting period.'
        );
    }

    /**
     * @return never
     */
    public function contextForAgent(int $agentId): array
    {
        throw new RuntimeException(
            'Unscoped AI operational context is disabled. Select one authorized store and reporting period.'
        );
    }
}
