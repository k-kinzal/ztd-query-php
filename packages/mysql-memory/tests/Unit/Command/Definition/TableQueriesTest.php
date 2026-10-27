<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition;

use MySqlMemory\Command\Definition\TableQueries;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Plan\ColumnOrigin;
use MySqlMemory\Plan\Path\Source\SingleRow;
use MySqlMemory\Plan\QueryPlan;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\TableQuery;
use SqlSemantics\Platform\MySql\Statement\Type\Enumeration;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Bindings;
use Tests\Generator\Embedding;

#[CoversClass(TableQueries::class)]
#[Small]
final class TableQueriesTest extends TestCase
{
    public function testInferredDefaultsPreservesTemporalDefaultsWithoutTreatingThemAsExplicitInput(): void
    {
        $session = (new Instance('5.6.51', [], ['d']))->connect(database: 'd');
        $session->query("SET sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE'");
        $created = $session->query("CREATE TABLE t SELECT DATE'2024-01-02' AS d")[0];
        $column = $session->instance->dictionary->table('d', 't')?->definition->columns[0];
        $insert = $session->run('INSERT INTO t () VALUES ()')[0];
        $explicit = $session->run("CREATE TABLE explicit (d DATE NOT NULL DEFAULT '0000-00-00')")[0];

        self::assertInstanceOf(Completion::class, $created);
        self::assertSame('0000-00-00', $column?->default->value);
        self::assertInstanceOf(SqlError::class, $insert);
        self::assertSame(1292, $insert->getCode());
        self::assertInstanceOf(SqlError::class, $explicit);
        self::assertSame(1067, $explicit->getCode());
    }

    public function testCreateReusesTheSameSemanticQueryAcrossCatalogAndLexicalChanges(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $query = new Select([], [new SelectExpression(new StringLiteral(['a\b']), new Name('v')), new SelectExpression(new NumberLiteral('1e2'), new Name('n'))]);
        $definition = new CreateTable(new QualifiedName(new Name('kept')), query: new TableQuery($query));
        $session->text = 'not an SQL definition';
        $statement1 = (new Embedding())->statement($definition);
        $parameters1 = array_fill(0, (new Bindings())->count($statement1), [null, Domain::null()]);
        $session->activity->begin();
        StatementCounters::received($session);

        $session->dots = [];
        try {
            $operation1 = (new Binding())->operation($session, $statement1, $parameters1, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
            $result1 = (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1), $parameters1);
        } catch (SqlError $executionError) {
            $result1 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        $first = $result1;
        $before = $session->query('SELECT * FROM kept')[0];
        $session->text = 'not an SQL definition';
        $statement2 = (new Embedding())->statement($definition);
        $parameters2 = array_fill(0, (new Bindings())->count($statement2), [null, Domain::null()]);
        $session->activity->begin();
        StatementCounters::received($session);

        $session->dots = [];
        try {
            $operation2 = (new Binding())->operation($session, $statement2, $parameters2, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
            $result2 = (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2), $parameters2);
        } catch (SqlError $executionError) {
            $result2 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        $existing = $result2;
        $session->query("DROP TABLE kept; SET sql_mode='NO_BACKSLASH_ESCAPES'");
        $session->text = 'another unrelated label';
        $statement3 = (new Embedding())->statement($definition);
        $parameters3 = array_fill(0, (new Bindings())->count($statement3), [null, Domain::null()]);
        $session->activity->begin();
        StatementCounters::received($session);

        $session->dots = [];
        try {
            $operation3 = (new Binding())->operation($session, $statement3, $parameters3, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement3)));
            $result3 = (new Execution($session))->perform($operation3, (new Dispatcher())->command($operation3), $parameters3);
        } catch (SqlError $executionError) {
            $result3 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        $again = $result3;
        $after = $session->query('SELECT * FROM kept')[0];
        self::assertNotInstanceOf(SqlError::class, $first);
        self::assertSame(1050, ($existing instanceof SqlError ? [$existing->getCode(), $existing->sqlState(), $existing->getMessage()] : null)[0] ?? null);
        self::assertNotInstanceOf(SqlError::class, $again);
        self::assertInstanceOf(ResultSet::class, $before);
        self::assertInstanceOf(ResultSet::class, $after);
        self::assertSame([['a\b', '100']], $before->rows);
        self::assertSame($before->rows, $after->rows);
        self::assertSame(3, $after->columns[1]->length);
    }

    public function testElementsRetainsDecodedMembersWithoutConsultingLexicalMode(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET sql_mode = 'NO_BACKSLASH_ESCAPES'");
        $session->text = 'unrelated input that is not SQL';
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $members = ['a\\b', "c'd"];
        $plan = new QueryPlan(new SingleRow(), [new Domain(Kind::String, Field::Enum, 3, collation: Collation::known('utf8mb4_bin'), members: $members)], ['e']);
        $columns = (new TableQueries($session, $context, new Connection($session->variables, $context)))->elements($plan, []);
        $type = $columns[0]->specification->dataType();

        self::assertInstanceOf(Enumeration::class, $type);
        self::assertSame($members, array_map(static fn ($member): string => $member->bytes(), $type->members));
        self::assertSame('unrelated input that is not SQL', $session->text);
    }

    public function testFillRefusesUnpartitionedSourceRowsAndIgnoreSkipsThem(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $refused = $session->run('CREATE TABLE d.t PARTITION BY LIST(a) (PARTITION p VALUES IN(1)) SELECT 2 AS a')[0];
        $ignored = $session->query('CREATE TABLE d.u PARTITION BY LIST(a) (PARTITION p VALUES IN(1)) IGNORE SELECT 2 AS a')[0];
        $rows = $session->query('SELECT a FROM d.u')[0];

        self::assertInstanceOf(SqlError::class, $refused);
        self::assertSame(1526, $refused->getCode());
        self::assertNull($session->instance->dictionary->table('d', 't'));
        self::assertInstanceOf(Completion::class, $ignored);
        self::assertSame('Records: 1  Duplicates: 1  Warnings: 1', $ignored->info);
        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([], $rows->rows);
    }

    public function testCreateRetainsPartitioningWhenQueryColumnsAreMaterialized(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query('CREATE TABLE d.t PARTITION BY RANGE(a) (PARTITION p VALUES LESS THAN(10)) SELECT 7 AS a');

        $rows = $session->query('SELECT a FROM d.t PARTITION(p)')[0];
        $definition = $session->instance->dictionary->table('d', 't')?->definition;

        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([['7']], $rows->rows);
        self::assertNotNull($definition?->partitioning);
        self::assertSame([0], $definition->partitioning->read);
    }

    public function testReportedLengthsRetainsComputedWidthsOnlyForUndeclaredColumns(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $queries = new TableQueries($session, $context, new Connection($session->variables, $context));
        $plan = new QueryPlan(new SingleRow(), [new Domain(Kind::Double, Field::Double, 12, Domain::NOT_FIXED), new Domain(Kind::Double, Field::Double, 12, Domain::NOT_FIXED), new Domain(Kind::Double, Field::Double, 23, Domain::NOT_FIXED)], ['X', 'y', 'wide']);
        $query = $session->analyze('SELECT 1e2 AS X, 1e2 AS y, ABS(1e2) AS wide')->statementInformation()->output;
        self::assertNotNull($query);

        self::assertSame(['x' => 12, 'wide' => 22], $queries->reportedLengths($plan, ['y' => true], $query));
    }

    public function testImplicitCollationsDistinguishesComputedAndCopiedColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t(a VARCHAR(10) CHARACTER SET latin1); CREATE TABLE r(z VARCHAR(10) CHARACTER SET latin1) AS SELECT a, CONCAT(a,'') x, CONCAT(a,'') z FROM t");
        $table = $session->instance->dictionary->table('d', 'r');

        self::assertNotNull($table);
        self::assertSame([false, false, true], array_map(static fn ($column): bool => $column->implicitCollation, $table->definition->columns));
    }

    public function testCreateCreatesTheTableWithTheRowsOfTheQuery(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a INT PRIMARY KEY, b VARCHAR(10) DEFAULT 'x'); INSERT INTO t VALUES (1,'p'),(2,NULL)");

        $reply = $session->query('CREATE TABLE e (z INT, a INT DEFAULT 7) SELECT a, b, a + 1 AS x FROM t')[0];
        $rows = $session->query('SELECT * FROM e')[0];
        $defaulted = $session->query('INSERT INTO e (z) VALUES (9)')[0];
        $after = $session->query('SELECT * FROM e WHERE z = 9')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([2, 'Records: 2  Duplicates: 0  Warnings: 0'], [$reply->affectedRows, $reply->info]);
        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([[null, '1', 'p', '2'], [null, '2', null, '3']], $rows->rows);
        self::assertSame(['LONG', 11, 'LONGLONG', 12], [$rows->columns[1]->type->name === 'Long' ? 'LONG' : '', $rows->columns[1]->length, $rows->columns[3]->type->name === 'LongLong' ? 'LONGLONG' : '', $rows->columns[3]->length]);
        self::assertInstanceOf(Completion::class, $defaulted);
        self::assertInstanceOf(ResultSet::class, $after);
        self::assertSame([['9', '7', 'x', '0']], $after->rows);
    }

    public function testCreateSkipsARepeatedRowWithIgnore(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $reply = $session->query("CREATE TABLE f (UNIQUE (b)) IGNORE SELECT 'a' AS b UNION ALL SELECT 'a'")[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([1, 'Records: 2  Duplicates: 1  Warnings: 1'], [$reply->affectedRows, $reply->info]);
    }

    public function testCreateRefusesARepeatedRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $error = $session->run("CREATE TABLE f (UNIQUE (b)) SELECT 'a' AS b UNION ALL SELECT 'a'")[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame([1062, "Duplicate entry 'a' for key 'f.b'"], [$error->getCode(), $error->getMessage()]);
        self::assertNull($session->instance->dictionary->table('d', 'f'));
    }

    public function testCreateRefusesAColumnNamedTwice(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1060);

        $session->query('CREATE TABLE g SELECT 1 AS a, 2 AS a');
    }

    public function testElementsLeavesOutTheDeclaredColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $plan = new QueryPlan(new SingleRow(), [new Domain(Kind::Integer, Field::LongLong, 2, 0, false, null, false), new Domain(Kind::Null, Field::Null)], ['a', 'n']);

        $elements = (new TableQueries($session, $context, new Connection($session->variables, $context)))->elements($plan, ['a' => true]);

        self::assertSame(['n'], array_map(static fn ($element): string => $element->name->column->value, $elements));
    }

    public function testCopiedAnswersTheDefinitionOfTheColumnRead(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (b VARCHAR(10) DEFAULT 'x')");
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        $element = (new TableQueries($session, $context, new Connection($session->variables, $context)))->copied(new ColumnOrigin('d', 'u', 't', 'b'), 'c');

        self::assertSame('c', $element?->name->column->value);
    }

    public function testFillStoresAValueOutsideTheRangeWithAWarningOutsideAStrictMode(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; SET sql_mode = ''");

        $session->query('CREATE TABLE k (a TINYINT NOT NULL) SELECT 300 AS a UNION ALL SELECT NULL');
        $warnings = $session->query('SHOW WARNINGS')[0];
        $rows = $session->query('SELECT * FROM k')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1264', "Out of range value for column 'a' at row 1"], ['Warning', '1048', "Column 'a' cannot be null"]], $warnings->rows);
        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([['127'], ['0']], $rows->rows);
    }

    public function testDefaultedRefusesAColumnWithoutADefaultUnderAStrictMode(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1364);

        $session->query('CREATE TABLE k (z INT NOT NULL) SELECT 1 AS a');
    }

    public function testStoredRefusesNullForANotNullColumnUnderAStrictMode(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1048);

        $session->query('CREATE TABLE k (a INT NOT NULL) SELECT NULL AS a');
    }

    public function testCreateMakesANullColumnABinaryBeforeMySql81(): void
    {
        $older = (new Instance('8.0.44', [], ['d']))->connect('root', 'localhost', 'd');
        $older->query('CREATE TABLE n AS SELECT NULL AS a; CREATE TABLE m AS SELECT NULL AS a UNION SELECT NULL');
        $newer = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $newer->query('CREATE TABLE n AS SELECT NULL AS a');
        $plain = $older->query('SHOW CREATE TABLE n')[0];
        $united = $older->query('SHOW CREATE TABLE m')[0];
        $current = $newer->query('SHOW CREATE TABLE n')[0];

        self::assertInstanceOf(ResultSet::class, $plain);
        self::assertInstanceOf(ResultSet::class, $united);
        self::assertInstanceOf(ResultSet::class, $current);
        self::assertStringContainsString('`a` binary(0) DEFAULT NULL', (string) $plain->rows[0][1]);
        self::assertStringContainsString('`a` binary(0) DEFAULT NULL', (string) $united->rows[0][1]);
        self::assertStringContainsString('`a` varbinary(0) DEFAULT NULL', (string) $current->rows[0][1]);
    }
}
