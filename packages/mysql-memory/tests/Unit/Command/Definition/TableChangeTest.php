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
use SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumn;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ConvertCharset;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\DropElement;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameElement;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\SetTableOptions;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Statement\Operation;

#[CoversClass(TableChange::class)]
#[Small]
final class TableChangeTest extends TestCase
{
    public function testApplyDropsTheOldColumnBeforeItAddsANewOne(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT, c INT, KEY kb (b))');
        $table = $session->instance->dictionary->table('d', 't');
        $alter = $session->analyze('ALTER TABLE t ADD COLUMN b VARCHAR(3), DROP COLUMN b, ADD x INT FIRST')->statement;
        self::assertNotNull($table);
        self::assertInstanceOf(AlterTable::class, $alter);
        $layout = TableLayout::of($table->definition);
        $change = new TableChange($layout, 't', 'd', GrammarRelease::MySql847);

        $change->apply($alter->commands);

        self::assertSame([['x', null], ['a', 0], ['c', 2], ['b', null]], array_map(static fn (array $column): array => [$column[0]->name->column->value, $column[1]], $layout->columns));
        self::assertStringContainsString('INDEX kb (b)', (new Operation($session->semantics()->context(), $layout->statement()))->toString());
        self::assertTrue($change->changes);
    }

    public function testApplyRefusesToDropAnIndexItAdds(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        $alter = $session->analyze('ALTER TABLE t ADD INDEX kq (a), DROP INDEX kq')->statement;
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
        $alter = $session->analyze('ALTER TABLE t ALGORITHM = COPY, LOCK = SHARED, RENAME TO u')->statement;
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

    public function testExistingRefusesAnUnknownColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Unknown column 'zz' in 't'");

        (new TableChange(TableLayout::of($table->definition), 't', 'd', GrammarRelease::MySql847))->existing('zz');
    }

    public function testDropRefusesACheckConstraintTheTableLacks(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        $alter = $session->analyze('ALTER TABLE t DROP CHECK nope')->statement;
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
        $alter = $session->analyze('ALTER TABLE t RENAME INDEX nope TO q')->statement;
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

    public function testPlaceRefusesAnUnknownColumnToFollow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        $alter = $session->analyze('ALTER TABLE t ADD b INT AFTER zz')->statement;
        self::assertNotNull($table);
        self::assertInstanceOf(AlterTable::class, $alter);
        $command = $alter->commands[0];
        self::assertInstanceOf(AddColumn::class, $command);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1054);

        (new TableChange(TableLayout::of($table->definition), 't', 'd', GrammarRelease::MySql847))->place($command->column, $command->position, null);
    }

    public function testOptionsReplacesTheCollationByACharacterSet(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        $alter = $session->analyze("ALTER TABLE t CHARACTER SET latin1 COMMENT = 'x'")->statement;
        self::assertNotNull($table);
        self::assertInstanceOf(AlterTable::class, $alter);
        $command = $alter->commands[0];
        self::assertInstanceOf(SetTableOptions::class, $command);
        $layout = TableLayout::of($table->definition);

        (new TableChange($layout, 't', 'd', GrammarRelease::MySql847))->options($command->options);

        self::assertStringEndsWith("CHARSET latin1 COMMENT 'x'", (new Operation($session->semantics()->context(), $layout->statement()))->toString());
    }

    public function testKindAnswersTheKindOfAnOption(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        $alter = $session->analyze('ALTER TABLE t AUTO_INCREMENT = 5')->statement;
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
        $alter = $session->analyze('ALTER TABLE t CONVERT TO CHARACTER SET latin1')->statement;
        self::assertNotNull($table);
        self::assertInstanceOf(AlterTable::class, $alter);
        $command = $alter->commands[0];
        self::assertInstanceOf(ConvertCharset::class, $command);
        $layout = TableLayout::of($table->definition);
        $change = new TableChange($layout, 't', 'd', GrammarRelease::MySql847);

        $change->convert($command);

        self::assertSame('CREATE TABLE d.t (a VARCHAR(5) COLLATE latin1_swedish_ci, b INT) CHARSET latin1', (new Operation($session->semantics()->context(), $layout->statement()))->toString());
        self::assertTrue($change->copies);
    }

    public function testAttributesAnswersTheAttributesOfAColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        $create = $session->analyze('CREATE TABLE u (a INT NOT NULL DEFAULT 1)')->statement;
        self::assertNotNull($table);
        self::assertInstanceOf(CreateTable::class, $create);
        self::assertInstanceOf(ColumnDefinition::class, $create->elements[0]);

        self::assertCount(2, (new TableChange(TableLayout::of($table->definition), 't', 'd', GrammarRelease::MySql847))->attributes($create->elements[0]));
    }

    public function testAttributedAnswersAColumnWithOtherAttributes(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        $create = $session->analyze('CREATE TABLE u (a INT NOT NULL)')->statement;
        self::assertNotNull($table);
        self::assertInstanceOf(CreateTable::class, $create);
        self::assertInstanceOf(ColumnDefinition::class, $create->elements[0]);
        $change = new TableChange(TableLayout::of($table->definition), 't', 'd', GrammarRelease::MySql847);

        self::assertSame([], $change->attributes($change->attributed($create->elements[0], [])));
    }
}
