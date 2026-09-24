<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\Routine\RoutineKind;
use SqlSemantics\Model\Statement\Inspection\Definition\ShowRoutineStatusStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoutineKind::class)]
#[Medium]
final class RoutineKindTest extends TestCase
{
    #[TestWith(['SHOW FUNCTION STATUS', RoutineKind::Function])]
    #[TestWith(['SHOW PROCEDURE STATUS', RoutineKind::Procedure])]
    public function testRetainsTheListedRoutineKind(string $sql, RoutineKind $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
        self::assertInstanceOf(ShowRoutineStatusStatement::class, $statement);
        self::assertSame($expected, $statement->routine);
        self::assertSame($sql, $statement->toString());
    }
}
