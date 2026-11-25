<?php

declare(strict_types=1);

namespace HybridRag\Application\Shared;

/**
 * Garantiza que documento, índice léxico e índice vectorial se actualicen de
 * forma atómica: o quedan los tres coherentes o no queda ninguno.
 */
interface TransactionManager
{
    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    public function transactional(callable $operation): mixed;
}
