<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition;

use MySqlMemory\Command\Definition\CreateTableLikeCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTableLike;
use SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Option\CollationOption;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(CreateTableLikeCommand::class)]
#[Small]
final class CreateTableLikeCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new CreateTableLikeCommand())->clearsDiagnostics());
    }

    public function testExecuteCopiesTheDefinitionWithoutTheRowsOrTheCounter(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a INT PRIMARY KEY AUTO_INCREMENT, b VARCHAR(10) DEFAULT 'x', UNIQUE KEY kb (b)) AUTO_INCREMENT=50; INSERT INTO t (b) VALUES ('p')");

        $session->query('CREATE TABLE c LIKE t');
        $session->query("INSERT INTO c (b) VALUES ('q'), (DEFAULT)");
        $rows = $session->query('SELECT * FROM c')[0];

        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([['1', 'q'], ['2', 'x']], $rows->rows);
        self::assertSame(['PRIMARY', 'kb'], array_map(static fn ($key): string => $key->name, $session->instance->dictionary->table('d', 'c')?->definition->keys ?? []));
    }

    public function testExecuteNotesAnExistingTableIfNotExists(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE TABLE c (b INT)');

        $reply = $session->query('CREATE TABLE IF NOT EXISTS c LIKE t')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Note', '1050', "Table 'c' already exists"]], $warnings->rows);
    }

    public function testExecuteChecksTheSourceFirst(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1146);

        $session->query('CREATE TABLE IF NOT EXISTS t LIKE nope');
    }

    public function testExecuteRefusesTheTableItself(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1066);

        $session->query('CREATE TABLE t LIKE t');
    }

    public function testExecuteRefusesAnUnknownDatabaseOfTheSource(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);

        $session->query('CREATE TABLE x LIKE abc.t');
    }

    public function testSourceAnswersTheTableToCopy(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $source = (new CreateTableLikeCommand())->source(new CreateTableLike(new QualifiedName(new Name('c')), new QualifiedName(new Name('t')), 0, false), $session);

        self::assertSame('t', $source->definition->name);
    }

    public function testSourceRefusesAView(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE VIEW v AS SELECT 1 AS a');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1347);

        (new CreateTableLikeCommand())->source(new CreateTableLike(new QualifiedName(new Name('c')), new QualifiedName(new Name('v')), 0, false), $session);
    }

    public function testSourceRefusesAStatementWithoutADatabase(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);

        (new CreateTableLikeCommand())->source(new CreateTableLike(new QualifiedName(new Name('c')), new QualifiedName(new Name('t')), 0, false), $session);
    }

    public function testLayoutDropsTheCounterAndTheForeignKeysAndUnnamesTheChecks(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (a INT PRIMARY KEY); CREATE TABLE t (a INT, CONSTRAINT k CHECK (a > 0), FOREIGN KEY (a) REFERENCES p (a)) AUTO_INCREMENT=5');
        $source = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($source);

        $layout = (new CreateTableLikeCommand())->layout($source, new QualifiedName(new Name('c'), new Name('d')), 1);

        self::assertSame('c', $layout->name->name->value);
        self::assertSame(1, $layout->temporaryWords);
        self::assertCount(1, $layout->options);
        self::assertInstanceOf(CollationOption::class, $layout->options[0]);
        self::assertCount(2, $layout->keys);
        self::assertInstanceOf(IndexDefinition::class, $layout->keys[0][0]);
        self::assertInstanceOf(CheckConstraint::class, $layout->keys[1][0]);
        self::assertNull($layout->keys[1][0]->name);
    }
}
