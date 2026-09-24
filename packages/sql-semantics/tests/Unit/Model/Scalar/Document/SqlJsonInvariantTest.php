<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Document\SqlJsonInvariant;
use SqlSemantics\Model\TableFunction\Json\Format;
use SqlSemantics\Model\TableFunction\Json\Input;
use SqlSemantics\Model\TableFunction\Json\PassingArgument;
use SqlSemantics\Model\TableFunction\Json\Response\DefaultResponse;
use SqlSemantics\Model\TableFunction\Json\Response\ValueBehavior;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(SqlJsonInvariant::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class SqlJsonInvariantTest extends TestCase
{
    public function testDialectAcceptsOperandsOfTheDialect(): void
    {
        SqlJsonInvariant::dialect('JSON_VALUE', Dialect::MySql, [Expression::literal(1, Dialect::MySql)]);
        $this->expectException(InvalidStructure::class);
        SqlJsonInvariant::dialect('JSON_VALUE', Dialect::MySql, [Expression::literal(1, Dialect::PostgreSql)]);
    }

    public function testPostgreSqlRejectsAnotherDialect(): void
    {
        SqlJsonInvariant::postgreSql('JSON_QUERY', [Expression::literal(1, Dialect::PostgreSql)]);
        $this->expectException(InvalidStructure::class);
        SqlJsonInvariant::postgreSql('JSON_QUERY', [Expression::literal(1, Dialect::Sqlite)]);
    }

    public function testPassedListsThePassingValuesInOrder(): void
    {
        $first = Expression::literal(1, Dialect::PostgreSql);
        $second = Expression::literal(2, Dialect::PostgreSql);
        self::assertSame([$first, $second], SqlJsonInvariant::passed([new PassingArgument('a', new Input($first)), new PassingArgument('b', new Input($second, Format::Json))]));
    }

    public function testValuesListTheFormattedExpressions(): void
    {
        $value = Expression::literal('{}', Dialect::PostgreSql);
        self::assertSame([$value], SqlJsonInvariant::values([new Input($value, Format::Json)]));
        self::assertSame([], SqlJsonInvariant::values([]));
    }

    public function testDefaultsKeepOnlyDefaultResponses(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        self::assertSame([$value], SqlJsonInvariant::defaults(ValueBehavior::Error, new DefaultResponse($value), ValueBehavior::Default));
    }

    public function testExtensionsMergeDistinctRelationOccurrences(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        self::assertSame([], SqlJsonInvariant::extensions([$value, $value]));
    }

    public function testExtensionsKeepEachOuterJoinOnceInOrder(): void
    {
        $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a INT)', 'CREATE TABLE u (b INT)', 'CREATE TABLE v (c INT)')))->bind('SELECT u.b, v.c FROM t LEFT JOIN u ON true LEFT JOIN v ON true');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $inner = $statement->outputs[0]->expression;
        $outer = $statement->outputs[1]->expression;
        self::assertCount(1, $inner->nullExtendedBy);
        self::assertCount(1, $outer->nullExtendedBy);
        self::assertSame([$inner->nullExtendedBy[0], $outer->nullExtendedBy[0]], SqlJsonInvariant::extensions([$inner, $outer, $inner]));
    }

    public function testDialectNamesTheOperation(): void
    {
        $this->expectExceptionObject(new InvalidStructure('JSON_VALUE operands must share one SQL dialect.'));
        SqlJsonInvariant::dialect('JSON_VALUE', Dialect::MySql, [Expression::literal(1, Dialect::MySql), Expression::literal(1, Dialect::Sqlite)]);
    }
}
