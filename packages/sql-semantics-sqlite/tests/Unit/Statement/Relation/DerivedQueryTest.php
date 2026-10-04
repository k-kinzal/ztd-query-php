<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\UnkeptSpelling;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Relation\DerivedQuery;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(DerivedQuery::class)]
#[Medium]
final class DerivedQueryTest extends TestCase
{
    public function testDeriveRelationNamesTheRelationByItsResultColumns(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('SELECT * FROM (SELECT a, b AS bb, a + 1 FROM t) AS d', [$create]);
        $fields = $query->fields();

        self::assertInstanceOf(Select::class, $query->statement);
        self::assertInstanceOf(DerivedQuery::class, $query->statement->from);
        self::assertSame('d', $query->statement->from->alias?->value);
        self::assertInstanceOf(Select::class, $query->statement->from->query);
        self::assertNotNull($fields);
        self::assertSame(['a', 'bb', null], array_map(static fn (Field $field): ?string => $field->name?->value, $fields->items));
        self::assertSame($create->declarations()[0]->columns[2], $query->field('bb')->column());
        self::assertSame(Nullability::NotNull, $query->field('a')->nullability);
        self::assertNull($query->facts->relation($query->statement->from)->table);
        self::assertCount(3, $query->facts->relation($query->statement->from)->shape->slots);
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testDeriveRelationKeepsDuplicateNamesApartLikeSqlite(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('SELECT * FROM (SELECT a, a FROM t) AS d', [$create]);
        $fields = $query->fields();

        self::assertNotNull($fields);
        self::assertSame(['a', 'a:1'], array_map(static fn (Field $field): ?string => $field->name?->value, $fields->items));
    }

    public function testDeriveRelationLeavesAnUnaliasedExpressionUnnamed(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('SELECT d.zz FROM (SELECT a + 1 FROM t) AS d', [$create]);
        $resolution = $query->field(0)->resolution;

        self::assertInstanceOf(ConditionalColumn::class, $resolution);
        self::assertSame([], $resolution->candidates);
        self::assertInstanceOf(DerivedQuery::class, $resolution->relations[0]);
        self::assertInstanceOf(UnkeptSpelling::class, $resolution->missing[0]);
    }

    public function testRenderWritesTheQueryInParenthesesWithItsAlias(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $built = new Operation($semantics->context(), new Select([new Star()], new DerivedQuery(new Select([new ResultColumn(new IntegerLiteral('1'), new Name('q'))]), new Name('d'))));

        self::assertSame('SELECT * FROM (SELECT 1 AS q) AS d', $built->toString());
        self::assertSame('SELECT * FROM (SELECT 1)', $semantics->analyze('select * from (select 1)')->toString());
        self::assertSame('SELECT q FROM (SELECT 1 AS q) AS d', $semantics->analyze('SELECT q FROM (SELECT 1 AS q) d')->toString());
    }
}
