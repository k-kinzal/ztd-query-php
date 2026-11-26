<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Rows\CompoundLowering;
use SqlSemantics\Platform\Sqlite\Statement\Query\Compound as ParsedCompound;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(CompoundLowering::class)]
#[Small]
final class CompoundLoweringTest extends TestCase
{
    public function testLowerKeepsTheOutputNamesOfEachArm(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1 AS first UNION ALL SELECT 2 AS other');
        self::assertInstanceOf(ParsedCompound::class, $operation->statement);

        self::assertSame(
            'SELECT 1 AS "first" UNION ALL SELECT 2 AS "other"',
            (new CompoundLowering())->lower($operation->binding(), $operation->statement)->toString(),
        );
    }
}
