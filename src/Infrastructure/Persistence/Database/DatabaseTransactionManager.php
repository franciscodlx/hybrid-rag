<?php

declare(strict_types=1);

namespace HybridRag\Infrastructure\Persistence\Database;

use HybridRag\Application\Shared\TransactionManager;
use Illuminate\Database\ConnectionInterface;

final readonly class DatabaseTransactionManager implements TransactionManager
{
    public function __construct(private ConnectionInterface $db) {}

    public function transactional(callable $operation): mixed
    {
        return $this->db->transaction(static fn (): mixed => $operation());
    }
}
