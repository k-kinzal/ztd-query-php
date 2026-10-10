<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use MySqlMemory\Instance;
use MySqlMemory\Plan\Views;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Query;

#[CoversClass(Views::class)]
#[Small]
final class ViewsTest extends TestCase
{
    public function testPlanReadsTheRowsOfTheQuery(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('INSERT INTO t VALUES (1, 2), (3, 4)');
        $session->query('CREATE VIEW v (x, y) AS SELECT a, a + b FROM t');

        $result1 = $session->query('SELECT * FROM v WHERE x > 1')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['3', '7']], $result1->rows);
    }

    public function testPlanRefusesAViewWhoseTableWasDropped(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE VIEW v AS SELECT a FROM t');
        $session->query('DROP TABLE t');

        $this->expectExceptionCode(1356);

        $session->query('SELECT * FROM v');
    }

    public function testRefreshReadsTheColumnsOfAReplacedTableByName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b VARCHAR(10))');
        $session->query('CREATE VIEW v AS SELECT * FROM t');
        $session->query('DROP TABLE t');
        $session->query('CREATE TABLE t (b INT, a INT)');
        $session->query('INSERT INTO t VALUES (5, 6)');

        $result2 = $session->query('SELECT * FROM v')[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        self::assertSame([[1, 0], [['6', '5']]], [Views::refresh($schema->views['v'], $session->instance->dictionary, $session->settings()), $result2->rows]);
    }

    public function testRefreshAllLeavesAnInvalidViewAsItIs(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE VIEW v AS SELECT a FROM t');
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        $declaration = $schema->views['v']->declaration;
        $session->query('DROP TABLE t');

        Views::refreshAll($session->instance->dictionary, $session->settings());

        self::assertSame($declaration, $schema->views['v']->declaration);
    }

    public function testQueryPlansTheColumnsInTheOrderOfTheView(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('CREATE VIEW v AS SELECT * FROM t');
        $session->query('DROP TABLE t');
        $session->query('CREATE TABLE t (b INT, a INT)');
        $view = $session->instance->dictionary->schema('d')?->views['v'];
        self::assertNotNull($view);
        $operation = $session->analyze('SELECT 1');
        $context = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new \MySqlMemory\Plan\Planner($operation->statement, $operation->facts, $session->settings(), new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $context), $session->instance->dictionary);

        self::assertSame(['a', 'b'], (new Views($planner))->query($view)->names);
    }

    public function testTableNamesTheColumnsByTheView(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE VIEW v (x) AS SELECT a FROM t');
        $view = $session->instance->dictionary->schema('d')?->views['v'];
        self::assertNotNull($view);
        $operation = $session->analyze('SELECT 1');
        $context = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new \MySqlMemory\Plan\Planner($operation->statement, $operation->facts, $session->settings(), new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $context), $session->instance->dictionary);

        self::assertSame(['x'], (new Views($planner))->table($view)->names);
    }

    public function testStoredGivesAComputedColumnThatIsNeverNullTheZeroOfItsType(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query("CREATE TABLE t (a INT NOT NULL DEFAULT 5, b VARCHAR(10) DEFAULT 'q')");
        $session->query('CREATE VIEW v AS SELECT a, b, a + 0.5 AS i, NULL AS h FROM t');
        $view = $session->instance->dictionary->schema('d')?->views['v'];
        self::assertNotNull($view);

        $columns = Views::stored($view, $session->instance->dictionary)->definition->columns;

        self::assertSame([5, 'q', '0.0', null], [$columns[0]->default->value, $columns[1]->default->value, $columns[2]->default->value, $columns[3]->default->value]);
    }

    public function testOutputsAnswersTheLowerCaseNamesOfTheColumns(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1 AS A, 2 AS b');
        $query = $operation->statement;

        self::assertInstanceOf(Query::class, $query);
        self::assertSame(['a', 'b'], Views::outputs($operation, $query));
    }

    public function testTablesAnswersTheDeclarationsTheQueryReads(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $operation = $session->analyze('SELECT a FROM t');
        $query = $operation->statement;

        self::assertInstanceOf(Query::class, $query);
        self::assertSame(['d.t'], array_keys(Views::tables($operation, $query, 'd')));
    }

    public function testDeclarationAnswersTheDeclarationOfATableOrAView(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE VIEW v AS SELECT a FROM t');
        $dictionary = $session->instance->dictionary;

        self::assertSame([$dictionary->table('d', 't')?->definition->declaration, $dictionary->schema('d')?->views['v']->declaration, null], [Views::declaration($dictionary, 'd', 't'), Views::declaration($dictionary, 'd', 'v'), Views::declaration($dictionary, 'e', 't')]);
    }

    public function testBaseDefaultAnswersTheDefaultOfTheColumnAnOutputReads(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT NOT NULL DEFAULT 5)');
        $session->query('CREATE VIEW v AS SELECT a, a + 1 AS b FROM t');
        $view = $session->instance->dictionary->schema('d')?->views['v'];
        self::assertNotNull($view);

        self::assertSame([5, null], [Views::baseDefault($view, $session->instance->dictionary, 0)?->value, Views::baseDefault($view, $session->instance->dictionary, 1)]);
    }

    public function testZeroAnswersTheZeroValueOfEachType(): void
    {
        self::assertSame([0, '0.00', '0', 0.0, '0000-00-00', '0000-00-00 00:00:00', '00:00:00', '', null], [
            Views::zero(new Domain(Kind::Integer, Field::Long)),
            Views::zero(new Domain(Kind::Decimal, Field::NewDecimal, 5, 2)),
            Views::zero(new Domain(Kind::Decimal, Field::NewDecimal, 5)),
            Views::zero(new Domain(Kind::Double, Field::Double)),
            Views::zero(new Domain(Kind::Date, Field::Date)),
            Views::zero(new Domain(Kind::DateTime, Field::DateTime)),
            Views::zero(new Domain(Kind::Time, Field::Time)),
            Views::zero(new Domain(Kind::String, Field::VarString)),
            Views::zero(new Domain(Kind::Null, Field::Null)),
        ]);
    }

    public function testBaseAnswersTheColumnAViewColumnReads(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query("CREATE TABLE t (a INT AUTO_INCREMENT PRIMARY KEY, b INT COMMENT 'bee')");
        $s->query('CREATE VIEW v AS SELECT a, b, a + 1 AS c FROM t');
        $view = $s->instance->dictionary->schema('d')->views['v'] ?? null;
        self::assertNotNull($view);

        self::assertSame(['a', 'b', null], [Views::base($view, $s->instance->dictionary, 0)?->name, Views::base($view, $s->instance->dictionary, 1)?->name, Views::base($view, $s->instance->dictionary, 2)?->name]);
        $read1 = $s->query('SHOW COLUMNS FROM v')[0];
        self::assertInstanceOf(ResultSet::class, $read1);
        self::assertSame([['a', 'int', 'NO', '', '0', ''], ['b', 'int', 'YES', '', null, '']], array_slice($read1->rows, 0, 2));
        $read2 = $s->query("SHOW FULL COLUMNS FROM v LIKE 'b'")[0];
        self::assertInstanceOf(ResultSet::class, $read2);
        self::assertSame('bee', $read2->rows[0][8]);
    }
}
