<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

/**
 * A value nothing can build, because PHP builds every one of them itself.
 */
enum TestTier: string
{
    case Gold = 'gold';
    case Silver = 'silver';
}
