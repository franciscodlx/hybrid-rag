<?php

declare(strict_types=1);

namespace HybridRag\Domain\Retrieval\Fusion;

use HybridRag\Domain\Retrieval\RankedList;

interface RankFusion
{
    /**
     * @param  list<RankedList>  $lists
     * @return list<FusedHit> ordenados de mejor a peor
     */
    public function fuse(array $lists): array;
}
