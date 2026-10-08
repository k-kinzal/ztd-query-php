<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition;

use MySqlMemory\Command\Definition\ColumnChange;
use MySqlMemory\Command\Definition\TableLayout;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumn;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\ChangeColumn;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\ColumnVisibility;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\DefaultSetting;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameElement;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Statement\Operation;

#[CoversClass(ColumnChange::class)]
#[Small]
final class ColumnChangeTest extends TestCase
{
    public function testExistingRefusesAnUnknownColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Unknown column 'zz' in 't'");

        (new ColumnChange(TableLayout::of($table->definition), 't'))->existing('zz');
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

        (new ColumnChange(TableLayout::of($table->definition), 't'))->place($command->column, $command->position, null);
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

        self::assertCount(2, (new ColumnChange(TableLayout::of($table->definition), 't'))->attributes($create->elements[0]));
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
        $change = new ColumnChange(TableLayout::of($table->definition), 't');

        self::assertSame([], $change->attributes($change->attributed($create->elements[0], [])));
    }

    public function testChangeReplacesAColumnInItsPlace(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT)');
        $table = $session->instance->dictionary->table('d', 't');
        $alter = $session->analyze('ALTER TABLE t CHANGE a c BIGINT')->statement;
        self::assertNotNull($table);
        self::assertInstanceOf(AlterTable::class, $alter);
        $command = $alter->commands[0];
        self::assertInstanceOf(ChangeColumn::class, $command);
        $layout = TableLayout::of($table->definition);

        $placed = (new ColumnChange($layout, 't'))->change($command);

        self::assertSame([], $placed);
        self::assertSame([['c', 0], ['b', 1]], array_map(static fn (array $column): array => [$column[0]->name->column->value, $column[1]], $layout->columns));
    }

    public function testChangeAnswersAColumnToPlaceLater(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT)');
        $table = $session->instance->dictionary->table('d', 't');
        $alter = $session->analyze('ALTER TABLE t MODIFY a BIGINT AFTER b')->statement;
        self::assertNotNull($table);
        self::assertInstanceOf(AlterTable::class, $alter);
        $command = $alter->commands[0];
        self::assertInstanceOf(ChangeColumn::class, $command);
        $layout = TableLayout::of($table->definition);

        $placed = (new ColumnChange($layout, 't'))->change($command);

        self::assertSame([['a', 'b', 0]], array_map(static fn (array $entry): array => [$entry[0]->name->column->value, $entry[1]?->after?->value, $entry[2]], $placed));
        self::assertSame([['b', 1]], array_map(static fn (array $column): array => [$column[0]->name->column->value, $column[1]], $layout->columns));
    }

    public function testAlterDefaultRemembersADroppedDefault(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT DEFAULT 1)');
        $table = $session->instance->dictionary->table('d', 't');
        $alter = $session->analyze('ALTER TABLE t ALTER COLUMN a DROP DEFAULT')->statement;
        self::assertNotNull($table);
        self::assertInstanceOf(AlterTable::class, $alter);
        $command = $alter->commands[0];
        self::assertInstanceOf(DefaultSetting::class, $command);
        $layout = TableLayout::of($table->definition);

        (new ColumnChange($layout, 't'))->alterDefault($command);

        self::assertSame(['a' => true], $layout->undefaulted);
        self::assertSame('CREATE TABLE d.t (a INT) COLLATE utf8mb4_0900_ai_ci', (new Operation($session->semantics()->context(), $layout->statement()))->toString());
    }

    public function testAlterVisibilityHidesAColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT)');
        $table = $session->instance->dictionary->table('d', 't');
        $alter = $session->analyze('ALTER TABLE t ALTER COLUMN b SET INVISIBLE')->statement;
        self::assertNotNull($table);
        self::assertInstanceOf(AlterTable::class, $alter);
        $command = $alter->commands[0];
        self::assertInstanceOf(ColumnVisibility::class, $command);
        $layout = TableLayout::of($table->definition);

        (new ColumnChange($layout, 't'))->alterVisibility($command);

        self::assertSame('CREATE TABLE d.t (a INT, b INT INVISIBLE) COLLATE utf8mb4_0900_ai_ci', (new Operation($session->semantics()->context(), $layout->statement()))->toString());
    }

    public function testRenameKeepsADroppedDefaultDropped(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        $alter = $session->analyze('ALTER TABLE t RENAME COLUMN a TO b')->statement;
        self::assertNotNull($table);
        self::assertInstanceOf(AlterTable::class, $alter);
        $command = $alter->commands[0];
        self::assertInstanceOf(RenameElement::class, $command);
        $layout = TableLayout::of($table->definition);
        $layout->undefaulted = ['a' => true];

        (new ColumnChange($layout, 't'))->rename($command);

        self::assertSame(['b' => true], $layout->undefaulted);
        self::assertSame('b', $layout->columns[0][0]->name->column->value);
    }

    public function testStoredRefusesAChangeBetweenVirtualAndStored(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE g (a INT, b INT AS (a))');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3106);
        $this->expectExceptionMessage("'Changing the STORED status' is not supported for generated columns.");

        $session->query('ALTER TABLE g MODIFY b INT AS (a) STORED');
    }

    public function testStoredLetsAStoredColumnBecomeAnOrdinaryOne(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE g (a INT, c INT AS (a) STORED); INSERT INTO g (a) VALUES (1); ALTER TABLE g MODIFY c INT');

        $result1 = $session->query('SHOW CREATE TABLE g')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result1);
        self::assertSame("CREATE TABLE `g` (\n  `a` int DEFAULT NULL,\n  `c` int DEFAULT NULL\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci", $result1->rows[0][1]);
    }
}
