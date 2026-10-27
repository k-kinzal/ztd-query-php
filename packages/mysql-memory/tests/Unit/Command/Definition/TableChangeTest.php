<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition;

use MySqlMemory\Command\Definition\TableChange;
use MySqlMemory\Command\Definition\TableLayout;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ConvertCharset;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\DropElement;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameElement;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\SetTableOptions;

#[CoversClass(TableChange::class)]
#[Small]
final class TableChangeTest extends TestCase
{
    public function testActPreservesIndexVisibilityThroughRenamingAndColumnChanges(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT,KEY k(a)); ALTER TABLE t ALTER INDEX k INVISIBLE; ALTER TABLE t RENAME INDEX k TO j, ADD b INT');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        self::assertSame(['j', false], [$table->definition->keys[0]->name, $table->definition->keys[0]->visible]);
        $session->query('ALTER TABLE t ALTER INDEX j VISIBLE');

        self::assertTrue($session->instance->dictionary->table('d', 't')?->definition->keys[0]->visible);
    }

    public function testDropClearsReportedWidthsBeforeReusingTheColumnName(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a FLOAT); CREATE TABLE c AS SELECT NULLIF(a,NULL) x FROM t; ALTER TABLE c DROP x, ADD x DOUBLE');
        $table = $session->instance->dictionary->table('d', 'c');

        self::assertNotNull($table);
        self::assertNull($table->definition->columns[0]->domain->display);
        self::assertSame([], TableLayout::of($table->definition)->reportedLengths);
    }

    public function testDropClearsTheOldCollationOriginBeforeAddingAColumnOfTheSameName(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t AS SELECT _latin1'a' x; ALTER TABLE t DROP x, ADD x VARCHAR(1) CHARACTER SET latin1");
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertFalse($table->definition->columns[0]->implicitCollation);
    }

    public function testApplyDropsTheOldColumnBeforeItAddsANewOne(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT, c INT, KEY kb (b))');
        $table = $session->instance->dictionary->table('d', 't');
        $alter = $session->analyze('ALTER TABLE t ADD COLUMN b VARCHAR(3), DROP COLUMN b, ADD x INT FIRST');
        self::assertNotNull($table);
        self::assertInstanceOf(AlterTable::class, $alter);
        $layout = TableLayout::of($table->definition);
        $change = new TableChange($layout, 't', 'd', GrammarRelease::MySql847);

        $change->apply($alter->commands);

        self::assertSame([['x', null], ['a', 0], ['c', 2], ['b', null]], array_map(static fn (array $column): array => [$column[0]->name->column->value, $column[1]], $layout->columns));
        self::assertStringContainsString('INDEX kb (b)', ((new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($session->semantics()->context(), $layout->statement(), new \SqlSemantics\Statement\Source\SourceMap()))->toString());
        self::assertTrue($change->changes);
    }

    public function testApplyRefusesToDropAnIndexItAdds(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        $alter = $session->analyze('ALTER TABLE t ADD INDEX kq (a), DROP INDEX kq');
        self::assertNotNull($table);
        self::assertInstanceOf(AlterTable::class, $alter);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1091);
        $this->expectExceptionMessage("Can't DROP 'kq'; check that column/key exists");

        (new TableChange(TableLayout::of($table->definition), 't', 'd', GrammarRelease::MySql847))->apply($alter->commands);
    }

    public function testApplyRefusesAColumnNamedTwice(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1060);

        $session->query('ALTER TABLE t RENAME COLUMN b TO A');
    }

    public function testApplyRefusesToDropEveryColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1090);

        $session->query('ALTER TABLE t DROP COLUMN a');
    }

    public function testCommandRemembersTheAlgorithmAndTheLock(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        $alter = $session->analyze('ALTER TABLE t ALGORITHM = COPY, LOCK = SHARED, RENAME TO u');
        self::assertNotNull($table);
        self::assertInstanceOf(AlterTable::class, $alter);
        $change = new TableChange(TableLayout::of($table->definition), 't', 'd', GrammarRelease::MySql847);

        $change->command($alter->commands[0]);
        $change->command($alter->commands[1]);
        $change->command($alter->commands[2]);

        self::assertSame(['copy', 'shared', 'u'], [$change->algorithm, $change->lock, $change->layout->name->name->value]);
    }

    public function testCommandRefusesAPartitionOperation(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1505);

        $session->query('ALTER TABLE t REMOVE PARTITIONING');
    }

    public function testDropRefusesACheckConstraintTheTableLacks(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        $alter = $session->analyze('ALTER TABLE t DROP CHECK nope');
        self::assertNotNull($table);
        self::assertInstanceOf(AlterTable::class, $alter);
        self::assertInstanceOf(DropElement::class, $alter->commands[0]);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3821);
        $this->expectExceptionMessage("Check constraint 'nope' is not found in the table.");

        (new TableChange(TableLayout::of($table->definition), 't', 'd', GrammarRelease::MySql847))->drop($alter->commands[0]);
    }

    public function testRenameRefusesAnIndexTheTableLacks(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        $alter = $session->analyze('ALTER TABLE t RENAME INDEX nope TO q');
        self::assertNotNull($table);
        self::assertInstanceOf(AlterTable::class, $alter);
        self::assertInstanceOf(RenameElement::class, $alter->commands[0]);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1176);
        $this->expectExceptionMessage("Key 'nope' doesn't exist in table 't'");

        (new TableChange(TableLayout::of($table->definition), 't', 'd', GrammarRelease::MySql847))->rename($alter->commands[0]);
    }

    public function testConstraintNamesAnUnnamedCheckAfterTheTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, CHECK (a > 0))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame(0, (new TableChange(TableLayout::of($table->definition), 't', 'd', GrammarRelease::MySql847))->constraint('t_chk_1', true));
    }

    public function testOptionsReplacesTheCollationByACharacterSet(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        $alter = $session->analyze("ALTER TABLE t CHARACTER SET latin1 COMMENT = 'x'");
        self::assertNotNull($table);
        self::assertInstanceOf(AlterTable::class, $alter);
        $command = $alter->commands[0];
        self::assertInstanceOf(SetTableOptions::class, $command);
        $layout = TableLayout::of($table->definition);

        (new TableChange($layout, 't', 'd', GrammarRelease::MySql847))->options($command->options);

        self::assertStringEndsWith("CHARSET latin1 COMMENT 'x'", ((new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($session->semantics()->context(), $layout->statement(), new \SqlSemantics\Statement\Source\SourceMap()))->toString());
    }

    public function testKindAnswersTheKindOfAnOption(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        $alter = $session->analyze('ALTER TABLE t AUTO_INCREMENT = 5');
        self::assertNotNull($table);
        self::assertInstanceOf(AlterTable::class, $alter);
        $command = $alter->commands[0];
        self::assertInstanceOf(SetTableOptions::class, $command);

        self::assertSame('number AUTO_INCREMENT', (new TableChange(TableLayout::of($table->definition), 't', 'd', GrammarRelease::MySql847))->kind($command->options[0]));
    }

    public function testConvertCollatesTheCharacterColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a VARCHAR(5), b INT)');
        $table = $session->instance->dictionary->table('d', 't');
        $alter = $session->analyze('ALTER TABLE t CONVERT TO CHARACTER SET latin1');
        self::assertNotNull($table);
        self::assertInstanceOf(AlterTable::class, $alter);
        $command = $alter->commands[0];
        self::assertInstanceOf(ConvertCharset::class, $command);
        $layout = TableLayout::of($table->definition);
        $change = new TableChange($layout, 't', 'd', GrammarRelease::MySql847);

        $change->convert($command);

        self::assertSame('CREATE TABLE d.t (a VARCHAR(5) COLLATE latin1_swedish_ci, b INT) COLLATE latin1_swedish_ci', ((new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($session->semantics()->context(), $layout->statement(), new \SqlSemantics\Statement\Source\SourceMap()))->toString());
        self::assertFalse($change->copies);
    }

    public function testConvertResolvesDefaultBeforePublishingTheNewDefinition(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d COLLATE latin1_bin; USE d; CREATE TABLE t(a VARCHAR(4)) CHARACTER SET utf8mb4');
        $session->query('ALTER TABLE t CONVERT TO CHARACTER SET DEFAULT');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame('latin1_bin', $table->definition->collation);
        self::assertSame('latin1_bin', $table->definition->columns[0]->domain->collation->name);
    }

    public function testActRefusesToHideThePrimaryKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT PRIMARY KEY)');
        $table = $session->instance->dictionary->table('d', 't');
        $alter = $session->analyze('ALTER TABLE t ALTER INDEX `PRIMARY` INVISIBLE');
        self::assertNotNull($table);
        self::assertInstanceOf(AlterTable::class, $alter);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3522);
        $this->expectExceptionMessage('A primary key index cannot be invisible');

        (new TableChange(TableLayout::of($table->definition), 't', 'd', GrammarRelease::MySql847))->act($alter->commands[0]);
    }

    public function testActRenamesTheTableIntoTheCurrentDatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        $alter = $session->analyze('ALTER TABLE t RENAME TO u');
        self::assertNotNull($table);
        self::assertInstanceOf(AlterTable::class, $alter);
        $change = new TableChange(TableLayout::of($table->definition), 't', 'e', GrammarRelease::MySql847);

        $change->act($alter->commands[0]);

        self::assertSame(['e', 'u'], [$change->layout->name->schema?->value, $change->layout->name->name->value]);
    }

    public function testMannerRemembersAnOrderAsACopy(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        $alter = $session->analyze('ALTER TABLE t ORDER BY a');
        self::assertNotNull($table);
        self::assertInstanceOf(AlterTable::class, $alter);
        $change = new TableChange(TableLayout::of($table->definition), 't', 'd', GrammarRelease::MySql847);

        $change->manner($alter->commands[0]);

        self::assertSame([true, true], [$change->ordered, $change->copies]);
    }

    public function testMannerRefusesASecondaryLoad(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        $alter = $session->analyze('ALTER TABLE t SECONDARY_LOAD');
        self::assertNotNull($table);
        self::assertInstanceOf(AlterTable::class, $alter);

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Secondary engine operation failed. No secondary engine defined.');

        (new TableChange(TableLayout::of($table->definition), 't', 'd', GrammarRelease::MySql847))->manner($alter->commands[0]);
    }
}
