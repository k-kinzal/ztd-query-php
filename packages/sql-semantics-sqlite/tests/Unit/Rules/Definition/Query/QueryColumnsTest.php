<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Definition\Query\QueryColumns;
use SqlSemantics\Platform\Sqlite\Statement\Query\Compound;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\NoAffinity;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(QueryColumns::class)]
#[Medium]
final class QueryColumnsTest extends TestCase
{
    public function testSettledStopsBeforeTheFirstUnexpandedStar(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 AS a, *, 2 AS b FROM undeclared');

        self::assertNotNull($query->facts->output);
        self::assertCount(1, (new QueryColumns())->settled($query->facts->output));
    }

    public function testUniqueRenamesARepeatedNameAsSqliteDoes(): void
    {
        $names = (new QueryColumns())->unique([new Name('a'), new Name('A'), new Name('a'), new Name('b:7'), new Name('B:7')], Comparison::AsciiInsensitive);

        self::assertSame(['a', 'A:1', 'a:2', 'b:7', 'B:1'], array_map(static fn (Name $name): string => $name->value, $names));
    }

    public function testUniqueEndsBeforeANameThatIsNotDetermined(): void
    {
        $columns = new QueryColumns();
        $repeated = array_fill(0, 6, new Name('a'));

        self::assertSame(['a', 'a:1', 'a:2', 'a:3', 'a:4'], array_map(static fn (Name $name): string => $name->value, $columns->unique($repeated, Comparison::AsciiInsensitive)));
        self::assertSame(['a'], array_map(static fn (Name $name): string => $name->value, $columns->unique([new Name('a'), null, new Name('b')], Comparison::AsciiInsensitive)));
    }

    public function testTakenComparesNamesWithoutRegardToCase(): void
    {
        self::assertTrue((new QueryColumns())->taken('A', [new Name('a')], Comparison::AsciiInsensitive));
        self::assertFalse((new QueryColumns())->taken('b', [new Name('a')], Comparison::AsciiInsensitive));
    }

    public function testStemDropsATrailingColonAndDigits(): void
    {
        $columns = new QueryColumns();

        self::assertSame('a', $columns->stem('a:12'));
        self::assertSame('a', $columns->stem('a:'));
        self::assertSame('a1', $columns->stem('a1'));
        self::assertSame('', $columns->stem(':1'));
        self::assertSame('', $columns->stem(''));
    }

    public function testTableColumnsRecordTheTypeOfTheExpressionAffinity(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE s (i INTEGER NOT NULL, t VARCHAR(5), n NUMERIC, r REAL, b BLOB)');
        $query = $semantics->analyze('SELECT i, t, n, r, b, 1 AS one, CAST(i AS TEXT) AS c, (SELECT i FROM s) AS sq, * FROM s', [$table]);
        $select = $query->statement;

        self::assertInstanceOf(Select::class, $select);
        self::assertNotNull($query->facts->output);
        $columns = (new QueryColumns())->tableColumns($select, $query->facts->output, $query->facts, Comparison::AsciiInsensitive);
        self::assertSame(['INT', 'TEXT', 'NUM', 'REAL', '', '', 'TEXT', 'INT', 'INT', 'TEXT', 'NUM', 'REAL', ''], array_map(static fn (Column $column): string => $column->type->name(), $columns));
        self::assertSame(['i', 't', 'n', 'r', 'b', 'one', 'c', 'sq', 'i:1', 't:1', 'n:1', 'r:1', 'b:1'], array_map(static fn (Column $column): string => $column->name->value, $columns));
        self::assertSame(Nullability::Nullable, $columns[0]->nullability);
    }

    public function testTableColumnsRecordACompoundFromItsArms(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE s (i INTEGER, t TEXT)');
        $query = $semantics->analyze("SELECT t AS x, i AS y, CAST(i AS INT) AS c, 1 AS one FROM s UNION ALL SELECT 1, 'a', 2, 2 FROM s", [$table]);
        $compound = $query->statement;

        self::assertInstanceOf(Compound::class, $compound);
        self::assertNotNull($query->facts->output);
        $columns = (new QueryColumns())->tableColumns($compound, $query->facts->output, $query->facts, Comparison::AsciiInsensitive);
        self::assertSame(['', '', 'NUM', ''], array_map(static fn (Column $column): string => $column->type->name(), $columns));
    }

    public function testTableColumnsStopAtAFieldWithoutADeterminedNameOrAffinity(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE s (i INTEGER, t TEXT)');
        $unnamed = $semantics->analyze('SELECT i, (SELECT i FROM s), t FROM s', [$table]);
        $undeclared = $semantics->analyze('SELECT s.i, u.x, s.t FROM s, u', [$table]);
        $columns = new QueryColumns();

        self::assertInstanceOf(Select::class, $unnamed->statement);
        self::assertInstanceOf(Select::class, $undeclared->statement);
        self::assertNotNull($unnamed->facts->output);
        self::assertNotNull($undeclared->facts->output);
        self::assertCount(1, $columns->tableColumns($unnamed->statement, $unnamed->facts->output, $unnamed->facts, Comparison::AsciiInsensitive));
        self::assertCount(1, $columns->tableColumns($undeclared->statement, $undeclared->facts->output, $undeclared->facts, Comparison::AsciiInsensitive));
    }

    public function testViewColumnsKeepTheDeclaredTypeAndTheNullFactOfAReferencedColumn(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE s (i INTEGER NOT NULL, t VARCHAR(5), n)');
        $query = $semantics->analyze('SELECT i, t, CAST(t AS INTEGER) AS c, 1 AS one, n FROM s', [$table]);
        $select = $query->statement;

        self::assertInstanceOf(Select::class, $select);
        self::assertNotNull($query->facts->output);
        $columns = (new QueryColumns())->viewColumns($select, $query->facts->output, $query->facts, null, Comparison::AsciiInsensitive);
        self::assertSame(['INTEGER', 'VARCHAR(5)', 'INT', '', 'BLOB'], array_map(static fn (Column $column): string => $column->type->name(), $columns));
        self::assertInstanceOf(NoAffinity::class, $columns[3]->type);
        self::assertSame(Nullability::NotNull, $columns[0]->nullability);
        self::assertSame(Nullability::Nullable, $columns[1]->nullability);
    }

    public function testViewColumnsRecordACompoundFromItsArms(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE s (i INTEGER, t VARCHAR(5))');
        $query = $semantics->analyze('SELECT t AS x, i AS y, t AS z FROM s UNION ALL SELECT 1, i, t FROM s', [$table]);
        $compound = $query->statement;

        self::assertInstanceOf(Compound::class, $compound);
        self::assertNotNull($query->facts->output);
        $columns = (new QueryColumns())->viewColumns($compound, $query->facts->output, $query->facts, null, Comparison::AsciiInsensitive);
        self::assertSame(['BLOB', 'INTEGER', 'VARCHAR(5)'], array_map(static fn (Column $column): string => $column->type->name(), $columns));
    }

    public function testViewColumnsGiveNoTypesWhenTheColumnListDoesNotFitTheQuery(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE s (i INTEGER)');
        $query = $semantics->analyze('SELECT i FROM s', [$table]);
        $select = $query->statement;

        self::assertInstanceOf(Select::class, $select);
        self::assertNotNull($query->facts->output);
        $columns = (new QueryColumns())->viewColumns($select, $query->facts->output, $query->facts, [new Name('x'), new Name('y')], Comparison::AsciiInsensitive, false);
        self::assertContainsOnlyInstancesOf(NoAffinity::class, array_map(static fn (Column $column): object => $column->type, $columns));
        self::assertSame(['x', 'y'], array_map(static fn (Column $column): string => $column->name->value, $columns));
        self::assertSame(Nullability::Nullable, $columns[0]->nullability);
        self::assertSame(Nullability::Dependent, $columns[1]->nullability);
    }

    public function testViewColumnsTakeTheirNamesFromAColumnListAndStopAtANameWithoutAField(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT 1 AS a, 2 AS b');
        $select = $query->statement;

        self::assertInstanceOf(Select::class, $select);
        self::assertNotNull($query->facts->output);
        $columns = (new QueryColumns())->viewColumns($select, $query->facts->output, $query->facts, [new Name('x'), new Name('X'), new Name('z')], Comparison::AsciiInsensitive);
        self::assertSame(['x', 'X:1'], array_map(static fn (Column $column): string => $column->name->value, $columns));
    }
}
