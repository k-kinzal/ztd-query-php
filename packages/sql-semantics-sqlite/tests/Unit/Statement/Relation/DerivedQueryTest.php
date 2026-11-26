<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\Field;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Relation\DerivedQuery;
use SqlSemantics\Testing\Sqlite\InspectedStatement;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(DerivedQuery::class)]
#[Medium]
final class DerivedQueryTest extends TestCase
{
    public function testDeriveRelationNamesTheRelationByItsResultColumns(): void
    {
        $semantics = new Inspection();
        $create = $semantics->diagnose('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->diagnose('SELECT * FROM (SELECT a, b AS bb, a + 1 FROM t) AS d', [$create]);
        $fields = $query->fields();

        self::assertInstanceOf(Select::class, $query->statement);
        self::assertInstanceOf(DerivedQuery::class, $query->statement->from);
        self::assertSame('d', $query->statement->from->alias?->value);
        self::assertInstanceOf(Select::class, $query->statement->from->query);
        self::assertNotNull($fields);
        self::assertSame(['a', 'bb', 'a + 1'], array_map(static fn (Field $field): ?string => $field->name?->value, $fields->items));
        self::assertSame($create->declarations()[0]->columns[2], $query->field('bb')->column());
        self::assertSame(Nullability::NotNull, $query->field('a')->nullability);
        self::assertNull($query->facts->relation($query->statement->from)->table);
        self::assertCount(3, $query->facts->relation($query->statement->from)->shape->slots);
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testDeriveRelationKeepsDuplicateNamesApartLikeSqlite(): void
    {
        $semantics = new Inspection();
        $create = $semantics->diagnose('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->diagnose('SELECT * FROM (SELECT a, a FROM t) AS d', [$create]);
        $fields = $query->fields();

        self::assertNotNull($fields);
        self::assertSame(['a', 'a:1'], array_map(static fn (Field $field): ?string => $field->name?->value, $fields->items));
    }

    public function testDeriveRelationNamesAnUnaliasedExpressionAfterItsText(): void
    {
        $semantics = new Inspection();
        $create = $semantics->diagnose('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->diagnose('SELECT d."a+1", d.zz FROM (SELECT a+1 FROM t) AS d', [$create]);

        self::assertInstanceOf(ResolvedColumn::class, $query->field(0)->resolution);
        self::assertSame('a+1', $query->field(0)->name?->value);
        self::assertInstanceOf(MissingColumn::class, $query->field(1)->resolution);
        self::assertSame('SELECT d."a+1", d.zz FROM (SELECT a+1 FROM t) AS d', $query->toString());
    }

    public function testDeriveRelationLeavesAColumnAfterAnUnexpandedStarUnnamed(): void
    {
        $query = (new Inspection())->diagnose('SELECT d.x FROM (SELECT *, 1 AS x FROM t) AS d');
        $resolution = $query->field(0)->resolution;

        self::assertInstanceOf(ConditionalColumn::class, $resolution);
        self::assertSame([], $resolution->candidates);
        self::assertInstanceOf(DerivedQuery::class, $resolution->relations[0]);
        self::assertSame('the declaration of relation t', $resolution->missing[0]->describe());
    }

    public function testRenderWritesTheQueryInParenthesesWithItsAlias(): void
    {
        $semantics = new Inspection();
        $built = new InspectedStatement($semantics->context(), new Select([new Star()], new DerivedQuery(new Select([new ResultColumn(new IntegerLiteral('1'), new Name('q'))]), new Name('d'))));

        self::assertSame('SELECT * FROM (SELECT 1 AS q) AS d', $built->toString());
        self::assertSame('SELECT * FROM (SELECT 1)', $semantics->diagnose('select * from (select 1)')->toString());
        self::assertSame('SELECT q FROM (SELECT 1 AS q) d', $semantics->diagnose('SELECT q FROM (SELECT 1 AS q) d')->toString());
        self::assertSame('SELECT * FROM (SELECT 1 AS q) d', (new InspectedStatement($semantics->context(), new Select([new Star()], new DerivedQuery(new Select([new ResultColumn(new IntegerLiteral('1'), new Name('q'))]), new Name('d'), false))))->toString());
    }

    public function testRenderRefusesToLeaveOutAsWithoutAnAlias(): void
    {
        $this->expectExceptionMessage('AS is left out only before an alias.');

        new DerivedQuery(new Select([new Star()]), null, false);
    }
}
