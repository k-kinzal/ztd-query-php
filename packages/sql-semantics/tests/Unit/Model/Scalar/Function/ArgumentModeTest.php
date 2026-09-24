<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Function;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Function\AggregateCall;
use SqlSemantics\Model\Scalar\Function\ArgumentMode;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ArgumentMode::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ArgumentModeTest extends TestCase
{
    #[TestWith(['count(ALL n)', ArgumentMode::All])]
    #[TestWith(['count(DISTINCT n)', ArgumentMode::Distinct])]
    #[TestWith(['count(n)', ArgumentMode::All])]
    public function testBindsTheDeclaredArgumentMode(string $call, ArgumentMode $mode): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(n INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT ' . $call . ' FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $aggregate = $statement->outputs[0]->expression;
        self::assertInstanceOf(AggregateCall::class, $aggregate);
        self::assertSame($mode, $aggregate->mode);
    }

    public function testOnlyDistinctIsWrittenBackToSql(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(n INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT count(ALL n), count(DISTINCT n) FROM t');
        self::assertSame('SELECT "count"("n"), "count"(DISTINCT "n") FROM "public"."t"', $statement->toString());
    }

    public function testSpellsEachModeAsItsKeyword(): void
    {
        self::assertSame(['ALL', 'DISTINCT'], array_map(static fn (ArgumentMode $mode): string => $mode->value, ArgumentMode::cases()));
    }
}
