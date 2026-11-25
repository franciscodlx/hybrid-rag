<?php

declare(strict_types=1);

namespace HybridRag\Infrastructure\Persistence\InMemory;

use HybridRag\Application\Shared\TransactionManager;

final class InMemoryTransactionManager implements TransactionManager
{
    public function transactional(callable $operation): mixed
    {
        return $operation();
    }
}
