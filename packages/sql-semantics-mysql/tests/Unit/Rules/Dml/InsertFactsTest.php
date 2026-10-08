<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Dml\InsertFacts;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Fact\Warning;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(InsertFacts::class)]
#[Medium]
final class InsertFactsTest extends TestCase
{
    public function testRowsChecksEveryRowAgainstTheFirst(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('INSERT INTO t VALUES (1, 2), (3)');

        self::assertSame(["Column count doesn't match value count at row 2"], array_map(static fn ($diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    public function testRowsChecksAnEmptyRowAgainstTheOtherRows(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tables = $semantics->analyze('CREATE TABLE t (a INT, b INT)')->declarations();
        $messages = static fn (string $sql): array => array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->analyze($sql, $tables)->facts->diagnostics);

        self::assertSame(["Column count doesn't match value count at row 2"], $messages('INSERT INTO t VALUES (1, 2), ()'));
        self::assertSame(["Column count doesn't match value count at row 2"], $messages('INSERT INTO t VALUES (), (1, 2)'));
        self::assertSame([], $messages('INSERT INTO t VALUES (), ()'));
    }

    public function testOpenReadsAnEmptyColumnListAsEveryColumn(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tables = $semantics->analyze('CREATE TABLE t (a INT, b INT)')->declarations();
        $messages = static fn (string $sql): array => array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->analyze($sql, $tables)->facts->diagnostics);

        self::assertSame([], $messages('INSERT INTO t () VALUES (1, 2)'));
        self::assertSame([], $messages('INSERT INTO t () VALUES ()'));
        self::assertSame([], $messages('INSERT INTO t () SELECT 1, 2'));
    }

    public function testQueryReportsAWidthMismatchAtTheFirstRow(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tables = $semantics->analyze('CREATE TABLE t (a INT, b INT)')->declarations();

        self::assertSame(["Column count doesn't match value count at row 1"], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->analyze('INSERT INTO t SELECT 1', $tables)->facts->diagnostics));
    }

    public function testSetDerivesTheAssignments(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze('INSERT INTO t SET a = 1, b = a', [$t]);
        self::assertInstanceOf(InsertSet::class, $operation->statement);

        self::assertInstanceOf(ResolvedColumn::class, $operation->facts->scalar($operation->statement->assignments[1]->value)->resolution);
    }

    public function testQueryDerivesTheSourceWithoutTheTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze('INSERT INTO t (a) SELECT b', [$t]);

        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[0]);
    }

    public function testOpenReportsAQualifiedStar(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('INSERT INTO t (t.*) VALUES (1)');

        self::assertSame("Unknown column '*' in 'field list'", $operation->facts->diagnostics[0]->message());
    }

    public function testColumnDependsOnAnUndeclaredTable(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('INSERT INTO t VALUES (DEFAULT)');
        self::assertInstanceOf(InsertRows::class, $operation->statement);

        self::assertInstanceOf(Dependent::class, $operation->facts->scalar($operation->statement->rows[0]->values[0])->type);
    }

    public function testColumnIsInvalidBeyondTheWrittenColumns(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze('INSERT INTO t (a) VALUES (1, DEFAULT)', [$t]);
        self::assertInstanceOf(InsertRows::class, $operation->statement);

        self::assertInstanceOf(Invalid::class, $operation->facts->scalar($operation->statement->rows[0]->values[1])->type);
    }

    public function testDuplicatesReportsAnAliasThatIsTheTableName(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze('INSERT INTO t VALUES (1, 2) AS t ON DUPLICATE KEY UPDATE a = 1', [$t]);

        self::assertSame('Not unique table/alias', $operation->facts->diagnostics[0]->message());
    }

    public function testQueryChecksEachRowOfAValuesSourceAgainstTheWrittenColumns(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = $semantics->analyze('CREATE TABLE t (a INT, b INT NOT NULL DEFAULT 0)');
        $second = $semantics->analyze('INSERT INTO t (a, b) (VALUES ROW(1, 2), ROW(3)) LIMIT 1', [$t])->facts->diagnostics;
        $empty = $semantics->analyze('INSERT INTO t () WITH x AS (SELECT 1) VALUES ROW(), ROW()', [$t])->facts->diagnostics;
        $listed = $semantics->analyze('INSERT INTO t (a) (VALUES ROW())', [$t])->facts->diagnostics;
        $default = $semantics->analyze('INSERT INTO t (a, b) (VALUES ROW(1, DEFAULT))', [$t]);

        self::assertSame(["Column count doesn't match value count at row 2"], array_map(static fn ($diagnostic): string => $diagnostic->message(), $second));
        self::assertSame([], $empty);
        self::assertSame(["Column count doesn't match value count at row 1"], array_map(static fn ($diagnostic): string => $diagnostic->message(), $listed));
        self::assertSame([], $default->facts->diagnostics);
    }

    public function testDefaultedHoldsForEmptyRowsWithoutAColumnList(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $empty = $semantics->analyze('INSERT INTO t VALUES ROW(), ROW() LOCK IN SHARE MODE')->statement;
        $listed = $semantics->analyze('INSERT INTO t (a) VALUES ROW()')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery::class, $empty);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery::class, $listed);

        self::assertTrue((new InsertFacts())->defaulted($empty));
        self::assertFalse((new InsertFacts())->defaulted($listed));
    }

    public function testDuplicatesNamesARowAliasThatIsTheTableName(): void
    {
        $diagnostics = (new Semantics(Dialect::MySql))->analyze('INSERT INTO w VALUES (1) AS w ON DUPLICATE KEY UPDATE a = 1')->facts->diagnostics;

        self::assertContainsEquals(new Misuse(MisuseRule::DuplicateAlias, new Name('w')), $diagnostics);
    }

    public function testOpenFindsTheInvisibleColumnsOfTheTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tables = $semantics->analyze('CREATE TABLE v (a INT, e INT INVISIBLE, f INT)')->declarations();
        $messages = static fn (string $sql): array => array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->analyze($sql, $tables)->facts->diagnostics);

        self::assertSame([], $messages('INSERT INTO v (a, e, f) VALUES (1, 2, 3)'));
        self::assertSame([], $messages('INSERT INTO v SET e = 3'));
        self::assertSame([], $messages('INSERT INTO v VALUES (1, 3)'));
        self::assertSame(["Column count doesn't match value count at row 1"], $messages('INSERT INTO v VALUES (1, 2, 3)'));
    }

    public function testOpenWarnsOfDelayedAsEachReleaseDoes(): void
    {
        $legacy = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('REPLACE DELAYED INTO t VALUES (1)');
        $modern = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('INSERT DELAYED INTO t VALUES (1)');

        self::assertSame(["'REPLACE DELAYED' is deprecated and will be removed in a future release. Please use REPLACE instead"], array_map(static fn (Warning $warning): string => $warning->message(), $legacy->facts->warnings));
        self::assertSame(['INSERT DELAYED is no longer supported. The statement was converted to INSERT.'], array_map(static fn (Warning $warning): string => $warning->message(), $modern->facts->warnings));
    }
}
