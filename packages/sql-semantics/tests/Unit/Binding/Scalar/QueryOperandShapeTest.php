<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Scalar\QueryOperandShape::class)]
final class QueryOperandShapeTest extends TestCase
{
    #[TestWith(['SELECT 1 IN (SELECT 1, 2)'])]
    #[TestWith(['SELECT ROW(1, 2) IN (SELECT 1)'])]
    #[TestWith(['SELECT 1 = ANY (SELECT 1, 2)'])]
    public function testCheckReportsStructuralWidthMismatch(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $binder->bind($sql, strict: false);
    }
}
