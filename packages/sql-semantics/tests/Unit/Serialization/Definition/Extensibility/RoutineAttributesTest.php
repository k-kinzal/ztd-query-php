<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Extensibility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity;
use SqlSemantics\Model\Definition\Routine\Option;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Extensibility\RoutineAttributes;

#[CoversClass(RoutineAttributes::class)]
#[Medium]
final class RoutineAttributesTest extends TestCase
{
    public function testAlterationWritesTheTargetAndChanges(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER PROCEDURE app.p(integer) SET a TO 1 RESET b');
        self::assertSame('ALTER PROCEDURE "app"."p"(integer) SET "a" = 1 RESET "b"', RoutineAttributes::alteration($statement)?->toString());
        self::assertNull(RoutineAttributes::alteration((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }

    #[TestWith([Option\Volatility::Stable, 'STABLE'])]
    #[TestWith([Option\NullInputBehavior::Called, 'CALLED ON NULL INPUT'])]
    #[TestWith([Option\LeakproofBehavior::NotLeakproof, 'NOT LEAKPROOF'])]
    #[TestWith([RoutineSecurity::Invoker, 'SECURITY INVOKER'])]
    #[TestWith([Option\ParallelSafety::Restricted, 'PARALLEL RESTRICTED'])]
    #[TestWith([new Option\RoutineReset(null), 'RESET ALL'])]
    public function testWriteSpellsEachAttribute(Option\RoutineOption|RoutineSecurity $option, string $expected): void
    {
        self::assertSame($expected, RoutineAttributes::write($option)->toString());
    }

    public function testWriteQualifiesTheSupportFunction(): void
    {
        self::assertSame('SUPPORT "app"."s"', RoutineAttributes::write(new Option\SupportFunction(new QualifiedName(['app', 's'])))->toString());
    }
}
