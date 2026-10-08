<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition;

use MySqlMemory\Command\Definition\TableLayout;
use MySqlMemory\Command\Definition\TableRebuild;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(TableRebuild::class)]
#[Small]
final class TableRebuildTest extends TestCase
{
    public function testDefinitionDeclaresTheLayoutAndWarnsOfADuplicatedKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, KEY i (a), KEY j (a))');
        $session->diagnostics->clear();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        $definition = (new TableRebuild($session, $context, new Connection($session->variables, $context)))->definition(TableLayout::of($table->definition), []);

        self::assertSame(['t', ['i', 'j']], [$definition->name, array_map(static fn ($key): string => $key->name, $definition->keys)]);
        self::assertSame([['Warning', 1831, "Duplicate index 'j' defined on the table 'd.t'. This is deprecated and will be disallowed in a future release."]], $session->diagnostics->conditions);
    }

    public function testRowsStoresNullAsTheImplicitDefaultOutsideAStrictMode(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; SET sql_mode = ''; CREATE TABLE t (a INT, b INT); INSERT INTO t VALUES (1, 1), (NULL, 2)");
        $session->query('ALTER TABLE t MODIFY a INT NOT NULL');

        $rows = $session->query('SELECT * FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([['1', '1'], ['0', '2']], $rows->rows);
    }

    public function testRowsRefusesNullForANotNullColumnUnderAStrictMode(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); INSERT INTO t VALUES (NULL)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1138);

        $session->query('ALTER TABLE t MODIFY a INT NOT NULL');
    }

    public function testRowsNumbersTheRowsOfANewAutoIncrementColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); INSERT INTO t VALUES (5), (7)');
        $session->query('ALTER TABLE t ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST');

        $rows = $session->query('SELECT * FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([['1', '5'], ['2', '7']], $rows->rows);
    }

    public function testUniqueReportsAResequencedAutoIncrementValue(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT PRIMARY KEY); INSERT INTO t VALUES (0), (1)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1062);
        $this->expectExceptionMessage("ALTER TABLE causes auto_increment resequencing, resulting in duplicate entry '1' for key 't.PRIMARY'");

        $session->query('ALTER TABLE t MODIFY a INT AUTO_INCREMENT');
    }

    public function testFreshAnswersTheImplicitDefaultOfANotNullColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b VARCHAR(3) NOT NULL); INSERT INTO t VALUES (1, \'x\')');
        $session->query('ALTER TABLE t ADD c VARCHAR(3) NOT NULL');

        $rows = $session->query('SELECT c FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([['']], $rows->rows);
    }

    public function testKeptWarnsOfNullInANotNullColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; SET sql_mode = ''; CREATE TABLE t (a INT, b INT); INSERT INTO t VALUES (NULL, 1)");
        $session->query('ALTER TABLE t MODIFY a INT NOT NULL');

        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1265', "Data truncated for column 'a' at row 1"]], $warnings->rows);
    }

    public function testChangedTellsWhetherTheStorageChanged(): void
    {
        self::assertSame([false, true], [
            TableRebuild::changed(new Domain(Kind::Integer, Field::Long, 11), new Domain(Kind::Integer, Field::Long, 11, 0, false, null, false)),
            TableRebuild::changed(new Domain(Kind::Integer, Field::Long, 11), new Domain(Kind::Integer, Field::LongLong, 20)),
        ]);
    }

    public function testInPlaceTellsWhetherTheServerChangesAColumnInPlace(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');

        self::assertSame([true, false, true, false], [
            TableRebuild::inPlace(new Domain(Kind::String, Field::VarChar, 10, 0, false, $collation), new Domain(Kind::String, Field::VarChar, 20, 0, false, $collation)),
            TableRebuild::inPlace(new Domain(Kind::String, Field::VarChar, 20, 0, false, $collation), new Domain(Kind::String, Field::VarChar, 200, 0, false, $collation)),
            TableRebuild::inPlace(new Domain(Kind::String, Field::Enum, 1, 0, false, $collation, true, ['x', 'y']), new Domain(Kind::String, Field::Enum, 1, 0, false, $collation, true, ['x', 'y', 'z'])),
            TableRebuild::inPlace(new Domain(Kind::String, Field::Enum, 1, 0, false, $collation, true, ['x', 'y']), new Domain(Kind::String, Field::Enum, 1, 0, false, $collation, true, ['y', 'x'])),
        ]);
    }

    public function testReferencedRefusesRowsANewForeignKeyFindsNoParentFor(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY); CREATE TABLE c (id INT, pid INT); INSERT INTO c VALUES (1, 2)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1452);
        $this->expectExceptionMessage('Cannot add or update a child row: a foreign key constraint fails (`d`.`#sql-1_1`, CONSTRAINT `c_ibfk_1` FOREIGN KEY (`pid`) REFERENCES `p` (`id`))');

        $session->query('ALTER TABLE c ADD FOREIGN KEY (pid) REFERENCES p(id)');
    }
}
