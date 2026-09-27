<?php

declare(strict_types=1);

namespace Tests\Contract;

use PHPUnit\Framework\Assert;
use SqlSemantics\Statement\Resolution;
use SqlSemantics\Statement\Statement;

/**
 * States that a statement analyzed with dependencies carries its resolution.
 */
final class Resolved
{
    /**
     * The resolution of a statement analyzed with dependencies.
     */
    public static function of(Statement $statement): Resolution
    {
        Assert::assertNotNull($statement->resolution);

        return $statement->resolution;
    }
}
