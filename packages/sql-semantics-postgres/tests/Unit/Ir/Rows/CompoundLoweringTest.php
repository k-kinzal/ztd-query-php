<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Compound;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\CompoundLowering;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(CompoundLowering::class)]
#[Medium]
final class CompoundLoweringTest extends TestCase
{
    public function testLowerKeepsEachArmsOutputName(): void
    {
        $inspection = new Inspection();
        $query = $inspection->inspect('SELECT 1 AS a UNION ALL SELECT 2 AS b');
        self::assertInstanceOf(SetOperation::class, $query->statement);

        $value = (new CompoundLowering())->lower($query->binding(), $query->statement);
        self::assertInstanceOf(Compound::class, $value);

        self::assertStringContainsString(' UNION ALL ', $value->toString());
        self::assertNotSame($value->left->toString(), $value->right->toString());
    }
}
