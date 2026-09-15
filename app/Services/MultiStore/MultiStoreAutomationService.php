<?php

declare(strict_types=1);

namespace App\Services\MultiStore;

use App\Repositories\MultiStoreAutomationRepository;

class MultiStoreAutomationService
{
    public function __construct(
        private MultiStoreAutomationRepository $repository
    ) {
    }

    public function saveAuditRun(?int $storeId = null): int
    {
        return $this->repository->saveAuditRun($storeId);
    }

    public function refreshCatalogCandidates(
        ?int $storeId = null
    ): int {
        return $this->repository->generateCatalogCandidates($storeId);
    }
}
