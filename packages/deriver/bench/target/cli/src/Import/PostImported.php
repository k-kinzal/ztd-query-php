<?php

declare(strict_types=1);

namespace HarborOps\Import;

final readonly class PostImported
{
    public function __construct(public int $id, public int $communityId, public string $externalId)
    {
    }
}
