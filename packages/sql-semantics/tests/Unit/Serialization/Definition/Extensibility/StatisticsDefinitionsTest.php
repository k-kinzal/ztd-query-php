<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Extensibility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\Statistics\CreateStatisticsStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Extensibility\StatisticsDefinitions;

#[CoversClass(StatisticsDefinitions::class)]
#[Medium]
final class StatisticsDefinitionsTest extends TestCase
{
    #[TestWith(['CREATE STATISTICS s (ndistinct) ON a, b FROM t', 'CREATE STATISTICS "s"("ndistinct") ON "a", "b" FROM "public"."t"'])]
    #[TestWith(['CREATE STATISTICS ON (a || b) FROM t', 'CREATE STATISTICS ON (("a" || "b")) FROM "public"."t"'])]
    #[TestWith(['ALTER STATISTICS s SET STATISTICS 7', 'ALTER STATISTICS "s" SET STATISTICS 7'])]
    public function testWriteSpellsEachFormFromItsOperands(string $sql, string $expected): void
    {
        $tree = StatisticsDefinitions::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a TEXT, b TEXT)')))->bind($sql));
        self::assertSame($expected, $tree?->toString());
    }

    public function testWriteIgnoresOtherStatements(): void
    {
        self::assertNull(StatisticsDefinitions::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }

    public function testHeadWritesTheOptionalName(): void
    {
        self::assertSame('CREATE STATISTICS', implode(' ', array_map(static fn ($tree): string => $tree->toString(), StatisticsDefinitions::head(null, false))));
        self::assertSame('CREATE STATISTICS IF NOT EXISTS "a"."s"', implode(' ', array_map(static fn ($tree): string => $tree->toString(), StatisticsDefinitions::head(new QualifiedName(['a', 's']), true))));
    }

    public function testElementParenthesizesQualifiedColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a TEXT, b TEXT)')))->bind('CREATE STATISTICS ON a, (t.b) FROM t');
        self::assertInstanceOf(CreateStatisticsStatement::class, $statement);
        self::assertSame(['"a"', '("t"."b")'], array_map(static fn ($element): string => StatisticsDefinitions::element($element)->toString(), $statement->elements));
    }
}
