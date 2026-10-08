<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition;

use MySqlMemory\Command\Definition\AlterTableCommand;
use MySqlMemory\Command\Definition\TableChange;
use MySqlMemory\Command\Definition\TableLayout;
use MySqlMemory\Command\Definition\TableRebuild;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\DropIndex;
use SqlSemantics\Platform\MySql\Statement\Table\CreateIndex;

#[CoversClass(AlterTableCommand::class)]
#[Small]
final class AlterTableCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new AlterTableCommand())->clearsDiagnostics());
    }

    public function testExecuteConvertsTheRowsOfAChangedColumnWithWarnings(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; SET sql_mode = ''; CREATE TABLE t (a INT, b VARCHAR(10)); INSERT INTO t VALUES (1,'x'),(2,'abc'),(3,'12'),(NULL,NULL)");

        $reply = $session->query('ALTER TABLE t MODIFY b INT')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];
        $rows = $session->query('SELECT * FROM t')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([4, 'Records: 4  Duplicates: 0  Warnings: 2'], [$reply->affectedRows, $reply->info]);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1366', "Incorrect integer value: 'x' for column 'b' at row 1"], ['Warning', '1366', "Incorrect integer value: 'abc' for column 'b' at row 2"]], $warnings->rows);
        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([['1', '0'], ['2', '0'], ['3', '12'], [null, null]], $rows->rows);
    }

    public function testExecuteRefusesAValueTheNewTypeCannotHoldUnderAStrictMode(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b VARCHAR(10)); INSERT INTO t VALUES (1,'hello')");

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1265);
        $this->expectExceptionMessage("Data truncated for column 'b' at row 1");

        $session->query('ALTER TABLE t MODIFY b VARCHAR(3)');
    }

    public function testExecuteAddsColumnsInPlace(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); INSERT INTO t VALUES (1)');

        $reply = $session->query('ALTER TABLE t ADD COLUMN c INT DEFAULT 5, ADD d INT FIRST, ADD e INT NOT NULL AFTER a')[0];
        $rows = $session->query('SELECT * FROM t')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([0, 'Records: 0  Duplicates: 0  Warnings: 0'], [$reply->affectedRows, $reply->info]);
        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([[null, '1', '0', '5']], $rows->rows);
    }

    public function testExecuteEnforcesAUniqueIndexItAdds(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT); INSERT INTO t VALUES (1,1),(2,2)');

        $session->query('CREATE UNIQUE INDEX ub ON t (b)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1062);
        $this->expectExceptionMessage("Duplicate entry '1' for key 't.ub'");

        $session->query('INSERT INTO t VALUES (3,1)');
    }

    public function testExecuteRefusesAUniqueIndexTheRowsRepeat(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b VARCHAR(10)); INSERT INTO t VALUES (1,'Z'),(2,'a'),(3,'z'),(4,'A')");

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1062);
        $this->expectExceptionMessage("Duplicate entry 'a' for key 't.ub'");

        $session->query('CREATE UNIQUE INDEX ub ON t (b)');
    }

    public function testExecuteDropsAnIndex(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, UNIQUE KEY ua (a)); INSERT INTO t VALUES (1)');

        $session->query('DROP INDEX ua ON t');
        $reply = $session->query('INSERT INTO t VALUES (1)')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(1, $reply->affectedRows);
    }

    public function testExecuteRenamesTheTableWithoutInformation(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); INSERT INTO t VALUES (1)');

        $reply = $session->query('ALTER TABLE t RENAME TO u')[0];
        $rows = $session->query('SELECT * FROM u')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame('', $reply->info);
        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([['1']], $rows->rows);
        self::assertNull($session->instance->dictionary->table('d', 't'));
    }

    public function testExecuteRefusesAnUnknownDatabaseBeforeTheKeyParts(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);

        $session->query('CREATE INDEX i ON abc.t ((zz + 1))');
    }

    public function testReadRefusesAnOrderOfAFullTextKeyPart(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('CREATE FULLTEXT INDEX i ON t (a ASC)');
        $statement = $operation->statement;
        self::assertInstanceOf(CreateIndex::class, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Incorrect usage of spatial/fulltext/hash index and explicit index order');

        (new AlterTableCommand())->read((new AlterTableCommand())->request($statement)[1], $operation);
    }

    public function testReadRefusesAParserThatIsNotInstalledFirst(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('CREATE FULLTEXT INDEX i ON t (a DESC) WITH PARSER zz ALGORITHM = bogus');
        $statement = $operation->statement;
        self::assertInstanceOf(CreateIndex::class, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1128);
        $this->expectExceptionMessage("Function 'zz' is not defined");

        (new AlterTableCommand())->read((new AlterTableCommand())->request($statement)[1], $operation);
    }

    public function testReadRefusesWithValidationOutsideAnExchange(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('ALTER TABLE t WITH VALIDATION, FORCE');
        $statement = $operation->statement;
        self::assertInstanceOf(AlterTable::class, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Incorrect usage of ALTER and WITH VALIDATION');

        (new AlterTableCommand())->read($statement->commands, $operation);
    }

    public function testRequestAnswersDropIndexAsAnAction(): void
    {
        $session = (new Instance())->connect();

        $statement = $session->analyze('DROP INDEX i ON t LOCK = NONE')->statement;
        self::assertInstanceOf(DropIndex::class, $statement);

        [$table, $commands] = (new AlterTableCommand())->request($statement);

        self::assertSame(['t', 2], [$table->name->value, count($commands)]);
    }

    public function testOthersLeavesTheTableOut(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE TABLE u (a INT)');

        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertCount(1, (new AlterTableCommand())->others($session, $table));
    }

    public function testCopiesRefusesAlgorithmInplaceForAChangeOfType(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1846);
        $this->expectExceptionMessage('ALGORITHM=INPLACE is not supported. Reason: Cannot change column type INPLACE. Try ALGORITHM=COPY.');

        $session->query('ALTER TABLE t MODIFY a BIGINT, ALGORITHM=INPLACE');
    }

    public function testCopiesAnswersFalseForANewIndex(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE TABLE u (a INT, KEY (a))');
        $old = $session->instance->dictionary->table('d', 't');
        $new = $session->instance->dictionary->table('d', 'u');
        self::assertNotNull($old);
        self::assertNotNull($new);
        $change = new TableChange(TableLayout::of($old->definition), 't', 'd', GrammarRelease::MySql847);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertFalse((new AlterTableCommand())->copies($old->definition, $new->definition, [0], $change, $context));
    }

    public function testMoveMovesTheTableToTheNameOfItsDefinition(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE TABLE u (a INT)');
        $table = $session->instance->dictionary->table('d', 'u');
        self::assertNotNull($table);
        unset($session->instance->dictionary->schemas['d']->tables['u']);
        $session->instance->dictionary->schemas['d']->tables['t'] = $table;

        (new AlterTableCommand())->move($session, $table, 'd', 't');

        self::assertSame(['u'], array_keys($session->instance->dictionary->schemas['d']->tables));
    }

    public function testTableAnswersTheBaseTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        self::assertSame($session->instance->dictionary->table('d', 't'), (new AlterTableCommand())->table($session, 'd', 't'));
    }

    public function testTableRefusesAView(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE VIEW v AS SELECT 1 AS a');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1347);
        $this->expectExceptionMessage("'d.v' is not BASE TABLE");

        (new AlterTableCommand())->table($session, 'd', 'v');
    }

    public function testTableRefusesAnUnknownDatabase(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);
        $this->expectExceptionMessage("Unknown database 'nope'");

        (new AlterTableCommand())->table($session, 'nope', 't');
    }

    public function testResolveRefusesAFunctionThatIsNotDeclared(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $operation = $session->analyze('ALTER TABLE t ADD COLUMN b INT DEFAULT (zz())');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1305);
        $this->expectExceptionMessage('FUNCTION d.zz does not exist');

        (new AlterTableCommand())->resolve($operation, $session);
    }

    public function testRebuildCountsTheCopiedRows(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); INSERT INTO t VALUES (1), (2)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $statement = $session->analyze('ALTER TABLE t MODIFY a BIGINT')->statement;
        self::assertInstanceOf(AlterTable::class, $statement);
        $layout = TableLayout::of($table->definition);
        $change = new TableChange($layout, 't', 'd', GrammarRelease::MySql847);
        $change->apply($statement->commands);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $rebuild = new TableRebuild($session, $context, new Connection($session->variables, $context));

        $completion = (new AlterTableCommand())->rebuild($rebuild, $table, $layout, $change);

        self::assertSame([2, 'Records: 2  Duplicates: 0  Warnings: 0'], [$completion->affectedRows, $completion->info]);
        self::assertCount(2, $table->data->rows);
    }

    public function testIndexesAnswersTheAddedIndexesInOrder(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('ALTER TABLE t ADD COLUMN b INT, ADD INDEX i (a), ADD UNIQUE KEY j (b)')->statement;
        self::assertInstanceOf(AlterTable::class, $statement);

        $indexes = (new AlterTableCommand())->indexes($statement->commands);

        self::assertSame(['i', 'j'], array_map(static fn ($index): ?string => $index->name?->column->value, $indexes));
    }

    public function testMisorderedAnswersTrueForAnOrderedFullTextKeyPart(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('CREATE FULLTEXT INDEX i ON t (a ASC)')->statement;
        self::assertInstanceOf(CreateIndex::class, $statement);
        $index = (new AlterTableCommand())->indexes((new AlterTableCommand())->request($statement)[1])[0];

        self::assertTrue((new AlterTableCommand())->misordered($index));
    }

    public function testMisorderedAnswersFalseForAnOrderedBTreeKeyPart(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('CREATE INDEX i ON t (a DESC)')->statement;
        self::assertInstanceOf(CreateIndex::class, $statement);
        $index = (new AlterTableCommand())->indexes((new AlterTableCommand())->request($statement)[1])[0];

        self::assertFalse((new AlterTableCommand())->misordered($index));
    }

    public function testCopiesColumnAnswersTrueForAColumnBecomingNotNullOutsideAStrictMode(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; SET sql_mode = ''; CREATE TABLE t (a INT, b INT NOT NULL)");
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertTrue((new AlterTableCommand())->copiesColumn($table->definition->columns[0], $table->definition->columns[1], $context));
    }

    public function testCopiesColumnAnswersFalseForAColumnBecomingNotNullUnderAStrictMode(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT NOT NULL)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertFalse((new AlterTableCommand())->copiesColumn($table->definition->columns[0], $table->definition->columns[1], $context));
    }
}
