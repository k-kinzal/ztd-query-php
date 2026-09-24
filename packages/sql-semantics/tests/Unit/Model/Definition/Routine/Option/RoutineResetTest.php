<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Option\RoutineReset;
use SqlSemantics\Model\Statement\Definition\Routine\AlterRoutineStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoutineReset::class)]
#[Medium]
final class RoutineResetTest extends TestCase
{
    public function testRetainsTheRemovedParameter(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FUNCTION f() RESET search_path RESET ALL');
        self::assertInstanceOf(AlterRoutineStatement::class, $statement);
        $one = $statement->changes[0];
        $all = $statement->changes[1];
        self::assertInstanceOf(RoutineReset::class, $one);
        self::assertInstanceOf(RoutineReset::class, $all);
        self::assertSame(['search_path'], $one->setting?->name);
        self::assertNull($all->setting);
    }
}
