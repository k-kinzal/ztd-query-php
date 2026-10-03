<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Definition\QueryColumns;
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
        $query = $semantics->analyze('SELECT i, t, n, r, b, 1 AS one, CAST(i AS TEXT) AS c FROM s', [$table]);

        self::assertNotNull($query->facts->output);
        $columns = (new QueryColumns())->tableColumns($query->facts->output, $query->facts, Comparison::AsciiInsensitive);
        self::assertSame(['INT', 'TEXT', 'NUM', 'REAL', '', '', 'TEXT'], array_map(static fn (Column $column): string => $column->type->name(), $columns));
        self::assertSame(Nullability::Nullable, $columns[0]->nullability);
    }

    public function testViewColumnsKeepTheDeclaredTypeAndTheNullFactOfAReferencedColumn(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE s (i INTEGER NOT NULL, t VARCHAR(5))');
        $query = $semantics->analyze('SELECT i, t, CAST(t AS INTEGER) AS c, 1 AS one FROM s', [$table]);

        self::assertNotNull($query->facts->output);
        $columns = (new QueryColumns())->viewColumns($query->facts->output, $query->facts, null, Comparison::AsciiInsensitive);
        self::assertSame($table->declarations()[0]->columns[0]->type, $columns[0]->type);
        self::assertSame(Nullability::NotNull, $columns[0]->nullability);
        self::assertSame(['INTEGER', 'VARCHAR(5)', 'INT', ''], array_map(static fn (Column $column): string => $column->type->name(), $columns));
    }

    public function testViewColumnsGiveNoTypesWhenTheColumnListDoesNotFitTheQuery(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE s (i INTEGER)');
        $query = $semantics->analyze('SELECT i FROM s', [$table]);

        self::assertNotNull($query->facts->output);
        $columns = (new QueryColumns())->viewColumns($query->facts->output, $query->facts, [new Name('x'), new Name('y')], Comparison::AsciiInsensitive, false);
        self::assertSame(['', ''], array_map(static fn (Column $column): string => $column->type->name(), $columns));
        self::assertSame(Nullability::Nullable, $columns[0]->nullability);
    }

    public function testViewColumnsTakeTheirNamesFromAColumnList(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT 1 AS a, 2 AS b');

        self::assertNotNull($query->facts->output);
        $columns = (new QueryColumns())->viewColumns($query->facts->output, $query->facts, [new Name('x'), new Name('X'), new Name('z')], Comparison::AsciiInsensitive);
        self::assertSame(['x', 'X:1', 'z'], array_map(static fn (Column $column): string => $column->name->value, $columns));
        self::assertSame(Nullability::Dependent, $columns[2]->nullability);
    }

    public function testViewTypeOfAnUndeterminedFieldIsNoDeclaredType(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 AS a');

        self::assertSame('', (new QueryColumns())->viewType(null, $query->facts)->name());
    }

    public function testViewTypeFallsBackToTheStandardNameOfTheAffinityWhenTheDeclaredTextDoesNotGiveIt(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE s (n, a ANY, b BLOB, i INTEGER, t VARCHAR(5))');
        $strict = $semantics->analyze('CREATE TABLE u (a ANY) STRICT');
        $query = $semantics->analyze('SELECT n, s.a, b, i, t, u.a, (SELECT i FROM s), (SELECT n FROM s), CAST(n AS NUMERIC), CAST(n AS BLOB), *, 1 FROM s, u', [$table, $strict]);
        $columns = new QueryColumns();
        $types = array_map(static fn (object $field): string => $columns->viewType($field, $query->facts)->name(), iterator_to_array($query->fields() ?? []));

        self::assertSame(['BLOB', 'ANY', 'BLOB', 'INTEGER', 'VARCHAR(5)', 'BLOB', 'INTEGER', 'BLOB', 'NUM', 'BLOB', 'BLOB', 'ANY', 'BLOB', 'INTEGER', 'VARCHAR(5)', 'BLOB', ''], array_values($types));
        self::assertSame($table->declarations()[0]->columns[3]->type, $columns->viewType($query->field(3), $query->facts));
    }

    public function testTableColumnsRecordTheAffinityOfASubqueryAndOfAnExpandedStar(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE s (i INTEGER, t TEXT)');
        $query = $semantics->analyze('SELECT (SELECT i FROM s), (SELECT t FROM s UNION ALL SELECT i FROM s), * FROM s', [$table]);

        self::assertNotNull($query->facts->output);
        $columns = (new QueryColumns())->tableColumns($query->facts->output, $query->facts, Comparison::AsciiInsensitive);
        self::assertSame(['INT', 'INT', 'INT', 'TEXT'], array_map(static fn (Column $column): string => $column->type->name(), $columns));
    }
}
