<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity;
use SqlSemantics\Model\Definition\Routine\Option\RoutineOption;
use SqlSemantics\Model\Statement\Definition\Routine\AlterRoutineStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoutineOption::class)]
#[Medium]
final class RoutineOptionTest extends TestCase
{
    public function testEveryAttributeButSecurityIsARoutineOption(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FUNCTION f() STABLE STRICT LEAKPROOF PARALLEL SAFE COST 2 ROWS 3 SUPPORT s SET a = 1 RESET b SECURITY DEFINER');
        self::assertInstanceOf(AlterRoutineStatement::class, $statement);
        self::assertSame(9, count(array_filter($statement->changes, static fn (RoutineOption|RoutineSecurity $change): bool => $change instanceof RoutineOption)));
        self::assertSame(RoutineSecurity::Definer, $statement->changes[9]);
    }
}
