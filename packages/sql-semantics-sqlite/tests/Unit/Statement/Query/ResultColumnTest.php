<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Spelling\Layout;
use SqlSemantics\Statement\Spelling\Spelled;

#[CoversClass(ResultColumn::class)]
#[Medium]
final class ResultColumnTest extends TestCase
{
    public function testRenderWritesTheAliasWithAsWhenItWasWritten(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('select 1 one, a as "x y", b from t');

        self::assertInstanceOf(Select::class, $query->statement);
        $columns = $query->statement->columns;
        self::assertInstanceOf(ResultColumn::class, $columns[0]);
        self::assertSame('one', $columns[0]->alias?->value);
        self::assertInstanceOf(ResultColumn::class, $columns[1]);
        self::assertSame('x y', $columns[1]->alias?->value);
        self::assertInstanceOf(ResultColumn::class, $columns[2]);
        self::assertNull($columns[2]->alias);
        self::assertInstanceOf(ColumnUse::class, $columns[2]->expression);
        self::assertSame('SELECT 1 one, a AS `x y`, b FROM t', $query->toString());
        self::assertFalse($columns[0]->as);
        self::assertTrue($columns[1]->as);
    }

    public function testRenderWritesANewlyBuiltColumn(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = new Operation($semantics->context(), new Select([new ResultColumn(new IntegerLiteral('1'), new Name('n'), null, false), new ResultColumn(new IntegerLiteral('2')), new ResultColumn(new Binary(BinaryOperator::Add, new IntegerLiteral('1'), new IntegerLiteral('1')), null, new Layout([new Spelled('', '1'), new Spelled('', '+'), new Spelled('', '1')]))]));

        self::assertSame('SELECT 1 n, 2, 1+1', $operation->toString());
        self::assertSame(['n', '2', '1+1'], array_map(static fn (Field $field): ?string => $field->name?->value, $operation->fields()->items ?? []));
    }

    public function testRenderWritesTheExpressionInItsLayout(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze("SELECT 1 + /* one */ 1, a  COLLATE  nocase, (select 2 x), 'y' AS y FROM t");
        self::assertInstanceOf(Select::class, $query->statement);
        $columns = $query->statement->columns;

        self::assertInstanceOf(ResultColumn::class, $columns[0]);
        self::assertSame('1 + /* one */ 1', $columns[0]->layout?->text());
        self::assertInstanceOf(ResultColumn::class, $columns[2]);
        self::assertSame('(select 2 x)', $columns[2]->layout?->text());
        self::assertInstanceOf(ResultColumn::class, $columns[3]);
        self::assertNull($columns[3]->layout);
        self::assertSame("SELECT 1 + /* one */ 1, a  COLLATE  nocase, (select 2 x), 'y' AS y FROM t", $query->toString());
    }

    public function testRenderKeepsNoLayoutForTheCanonicalSpelling(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 + 1, a FROM t');
        self::assertInstanceOf(Select::class, $query->statement);
        $columns = $query->statement->columns;

        self::assertInstanceOf(ResultColumn::class, $columns[0]);
        self::assertNull($columns[0]->layout);
        self::assertSame('1 + 1', $query->field(0)->name?->value);
    }

    #[TestWith(['SELECT "1+1" FROM (SELECT 1+1)'])]
    #[TestWith(["SELECT 1 + /* c */ 1, a  COLLATE  nocase, 'x', -a, a NOTNULL, a not null, b IS NOT NULL FROM t"])]
    #[TestWith(['SELECT * FROM (SELECT A, a+1, true, "zz", (a), t.b, a COLLATE nocase FROM t)'])]
    #[TestWith(["SELECT * FROM (SELECT 1000, 0X1f, 1.50E-3, x'0aff', 'text'.a, CROSS.b FROM t AS 'text', t AS CROSS)"])]
    #[TestWith(['WITH w AS (SELECT a COLLATE nocase, likely(b), cast(a as text), max(a) OVER () FROM t) SELECT * FROM w'])]
    #[TestWith(['SELECT * FROM (SELECT (SELECT 1 FROM t x), a  +  1 FROM t UNION SELECT 2, 3), (VALUES ("zz", 1))'])]
    #[TestWith(['SELECT * FROM (SELECT a, a, a AS "a:1", true, 1 AS false FROM t)'])]
    #[TestWith(["INSERT INTO t VALUES (2, 'y') RETURNING a+1, b  COLLATE nocase, rowid"])]
    #[TestWith(['SELECT 1+1 /*c*/ ,2'])]
    #[TestWith(["SELECT a+1 -- c\n , b /* d */ FROM t"])]
    #[TestWith(['SELECT 1+1 -- c'])]
    #[TestWith(["SELECT 1+1 /* c */ \n\t"])]
    #[TestWith(['SELECT * FROM (SELECT a+1 /* x */ , b /* y */ FROM t)'])]
    #[TestWith(['WITH w AS (SELECT a /* x */, a+1 /* y */ FROM t) SELECT * FROM w'])]
    #[TestWith(['SELECT (SELECT 1+1 /* x */ ) /* y */ UNION SELECT 2'])]
    #[TestWith(["INSERT INTO t VALUES (2, 'y') RETURNING a+1 /* c */ , b -- d"])]
    public function testRenderKeepsTheNamesAndTheResultsSqliteGives(string $sql): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t (a INTEGER, b TEXT)')]);
        $source = new PDO('sqlite::memory:');
        $rendered = new PDO('sqlite::memory:');
        $source->exec("CREATE TABLE t (a INTEGER, b TEXT); INSERT INTO t VALUES (1, 'x')");
        $rendered->exec("CREATE TABLE t (a INTEGER, b TEXT); INSERT INTO t VALUES (1, 'x')");
        $original = $source->query($sql);
        $rebuilt = $rendered->query($operation->toString());
        self::assertInstanceOf(PDOStatement::class, $original);
        self::assertInstanceOf(PDOStatement::class, $rebuilt);
        $names = array_map(static fn (int $position): mixed => $original->getColumnMeta($position) === false ? null : $original->getColumnMeta($position)['name'], range(0, $original->columnCount() - 1));

        self::assertSame($names, array_map(static fn (int $position): mixed => $rebuilt->getColumnMeta($position) === false ? null : $rebuilt->getColumnMeta($position)['name'], range(0, $rebuilt->columnCount() - 1)));
        self::assertSame($names, array_map(static fn (Field $field): ?string => $field->name?->value, $operation->fields()->items ?? []));
        self::assertSame($original->fetchAll(PDO::FETCH_NUM), $rebuilt->fetchAll(PDO::FETCH_NUM));
    }

    public function testRenderRefusesALayoutThatDoesNotSpellTheExpression(): void
    {
        $this->expectExceptionMessage('The layout of a result column spells one token per token its expression renders.');

        new ResultColumn(new IntegerLiteral('1'), null, new Layout([new Spelled('', '1'), new Spelled('', '+')]));
    }

    public function testRenderKeepsTheCommentAfterTheExpression(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze("SELECT 1+1 /*c*/ ,2, 3 -- d\n");
        self::assertInstanceOf(Select::class, $query->statement);
        $columns = $query->statement->columns;

        self::assertInstanceOf(ResultColumn::class, $columns[0]);
        self::assertSame(' /*c*/ ', $columns[0]->layout?->trail);
        self::assertInstanceOf(ResultColumn::class, $columns[1]);
        self::assertNull($columns[1]->layout);
        self::assertInstanceOf(ResultColumn::class, $columns[2]);
        self::assertSame(" -- d\n", $columns[2]->layout?->trail);
        self::assertSame(['1+1 /*c*/', '2', '3 -- d'], array_map(static fn (Field $field): ?string => $field->name?->value, $query->fields()->items ?? []));
        self::assertSame("SELECT 1+1 /*c*/ , 2, 3 -- d\n", $query->toString());
    }

    public function testRenderRefusesWhitespaceAloneAfterTheExpression(): void
    {
        $this->expectExceptionMessage('The layout of a result column keeps the trivia after its expression only when it holds a comment.');

        new ResultColumn(new IntegerLiteral('1'), null, new Layout([new Spelled('', '1')], ' '));
    }

    public function testRenderRefusesTheCanonicalSpellingAsALayout(): void
    {
        $this->expectExceptionMessage('A result column written in the canonical spelling has no layout.');

        new ResultColumn(new Binary(BinaryOperator::Add, new IntegerLiteral('1'), new IntegerLiteral('1')), null, new Layout([new Spelled('', '1'), new Spelled(' ', '+'), new Spelled(' ', '1')]));
    }

    public function testRenderRefusesALayoutOfAnAliasedColumn(): void
    {
        $this->expectExceptionMessage('An aliased result column is named by its alias and keeps no layout.');

        new ResultColumn(new IntegerLiteral('1'), new Name('x'), new Layout([new Spelled('', '01')]));
    }

    public function testRenderRefusesToLeaveOutAsWithoutAnAlias(): void
    {
        $this->expectExceptionMessage('AS is left out only before an alias.');

        new ResultColumn(new IntegerLiteral('1'), null, null, false);
    }

    public function testRenderRefusesALayoutThatChangesAToken(): void
    {
        $this->expectExceptionMessage('The rendered SQL does not correspond to the statement');

        new Operation((new Semantics(Dialect::Sqlite))->context(), new Select([new ResultColumn(new IntegerLiteral('1'), null, new Layout([new Spelled('', '2')]))]));
    }

    public function testRenderKeepsDigitSeparatorsThatNameAColumn(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT * FROM (SELECT 1_000)');

        self::assertSame('1_000', $operation->fields()?->at(0)->name?->value);
        self::assertSame('SELECT * FROM (SELECT 1_000)', $operation->toString());
    }
}
