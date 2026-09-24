<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\Text\FullTextMode;
use SqlSemantics\Model\Scalar\Text\FullTextSearch;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(FullTextSearch::class)]
#[Medium]
final class FullTextSearchTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testInputsListTheSearchedColumnsBeforeTheSearchString(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t (title TEXT, body TEXT)'));
        $query = $binder->bind("SELECT MATCH (title, t.body) AGAINST ('word' IN NATURAL LANGUAGE MODE WITH QUERY EXPANSION) FROM t");
        self::assertInstanceOf(BoundSelect::class, $query);
        $search = $query->outputs[0]->expression;
        self::assertInstanceOf(FullTextSearch::class, $search);
        self::assertSame(['title', 'body'], array_map(static fn (Expression $column): ?string => $column->columnBinding()?->column->name, $search->columns));
        self::assertSame("'word'", $search->query->spelling());
        self::assertSame(FullTextMode::QueryExpansion, $search->mode);
        self::assertSame([...$search->columns, $search->query], $search->inputs());
        self::assertSame('double precision', $search->type->name);
        self::assertSame(Nullability::NotNull, $search->nullability);
        self::assertSame(ExpressionKind::FullTextSearch, $search->kind);
        self::assertSame("SELECT MATCH(`title`, `t`.`body`) AGAINST('word' WITH QUERY EXPANSION) FROM `t`", $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testInputsRejectAnEmptyColumnList(): void
    {
        $value = Expression::literal('word', Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new FullTextSearch($value->source, [], $value, FullTextMode::Boolean);
    }

    public function testInputsRejectAColumnListEntryThatIsNotAColumn(): void
    {
        $value = Expression::literal('word', Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new FullTextSearch($value->source, [$value], $value, FullTextMode::Boolean);
    }

    public function testInputsRejectAnotherDialect(): void
    {
        $column = Expression::reference(['title'], Dialect::MySql);
        $value = Expression::literal('word', Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new FullTextSearch($value->source, [$column], $value, FullTextMode::Boolean);
    }

    public function testSpellingNamesTheSearch(): void
    {
        $column = Expression::reference(['title'], Dialect::MySql);
        $value = Expression::literal('word', Dialect::MySql);
        self::assertSame('MATCH', (new FullTextSearch($value->source, [$column], $value, FullTextMode::Boolean))->spelling());
    }

    public function testWithFactsPreservesTheSearchOperands(): void
    {
        $column = Expression::reference(['title'], Dialect::MySql);
        $value = Expression::literal('word', Dialect::MySql);
        $search = new FullTextSearch($value->source, [$column], $value, FullTextMode::Boolean);
        $copy = $search->withFacts($search->facts);
        self::assertNotSame($search, $copy);
        self::assertSame([$column], $copy->columns);
        self::assertSame(FullTextMode::Boolean, $copy->mode);
    }

    public function testWithFactsRejectsContradictoryFacts(): void
    {
        $column = Expression::reference(['title'], Dialect::MySql);
        $value = Expression::literal('word', Dialect::MySql);
        $search = new FullTextSearch($value->source, [$column], $value, FullTextMode::Boolean);
        $this->expectException(InvalidStructure::class);
        $search->withFacts($value->facts);
    }
}
