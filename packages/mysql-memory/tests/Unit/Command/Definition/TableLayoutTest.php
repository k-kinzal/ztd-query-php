<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition;

use MySqlMemory\Command\Definition\TableLayout;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;

#[CoversClass(TableLayout::class)]
#[Small]
final class TableLayoutTest extends TestCase
{
    public function testOfStatesTheKeysCollationsAndNullabilityOfTheTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT PRIMARY KEY, b VARCHAR(5) UNIQUE)');

        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $layout = TableLayout::of($table->definition);

        self::assertSame('CREATE TABLE d.t (a INT NOT NULL, b VARCHAR(5) COLLATE utf8mb4_0900_ai_ci, PRIMARY KEY (a), UNIQUE b (b)) COLLATE utf8mb4_0900_ai_ci', (new Operation($session->semantics()->context(), $layout->statement()))->toString());
        self::assertSame([0, 1], array_map(static fn (array $column): ?int => $column[1], $layout->columns));
        self::assertSame(['a', 'b'], $layout->names);
    }

    public function testOfRemembersAColumnWithoutADefault(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT DEFAULT 1, b INT); ALTER TABLE t ALTER COLUMN a DROP DEFAULT');

        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame(['a' => true], TableLayout::of($table->definition)->undefaulted);
    }

    public function testOfRefusesATableWithoutItsStatement(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $definition = $table->definition;

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1235);

        TableLayout::of(new TableDefinition('d', 't', $definition->columns, $definition->keys, $definition->declaration));
    }

    public function testUnkeyedDropsTheKeyAttributes(): void
    {
        $session = (new Instance())->connect();
        $create = $session->analyze('CREATE TABLE t (a INT PRIMARY KEY UNIQUE)')->statement;
        self::assertInstanceOf(CreateTable::class, $create);
        $element = $create->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $element);

        $unkeyed = TableLayout::unkeyed($element, null, true);

        self::assertSame('CREATE TABLE t (a INT NOT NULL)', (new Operation($session->semantics()->context(), new CreateTable(new QualifiedName(new Name('t')), [$unkeyed])))->toString());
    }

    public function testSpecifiedKeepsTheTypeOfAColumn(): void
    {
        $session = (new Instance())->connect();
        $create = $session->analyze('CREATE TABLE t (a INT NULL)')->statement;
        self::assertInstanceOf(CreateTable::class, $create);
        self::assertInstanceOf(ColumnDefinition::class, $create->elements[0]);
        $specification = $create->elements[0]->specification;
        self::assertInstanceOf(OrdinaryColumn::class, $specification);

        self::assertSame([], TableLayout::specified($specification, [])->attributes);
    }

    public function testIndexAnswersTheDefinitionOfAKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a VARCHAR(5), KEY k (a(2) DESC))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        $index = TableLayout::index($table->definition->keys[0], $table->definition);
        $part = $index->parts[0];

        self::assertInstanceOf(ColumnPart::class, $part);
        self::assertSame(['k', 'a', '2'], [$index->name?->column->value, $part->column->value, $part->length?->text]);
    }

    public function testColumnFindsAColumnWithoutRegardToCase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, Bb INT)');

        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame(1, TableLayout::of($table->definition)->column('bB'));
    }

    public function testCoveredAnswersANewColumnOfTheOldName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $layout = TableLayout::of($table->definition);
        $layout->columns[1][1] = null;

        self::assertSame(['a', 'b'], [$layout->covered(0), $layout->covered(1)]);
    }

    public function testKeyFindsThePrimaryKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT, KEY k (b), PRIMARY KEY (a))');

        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame(0, TableLayout::of($table->definition)->key('primary'));
    }

    public function testStatementDropsAnIndexWhoseColumnsAreGone(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT, KEY k (b))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $layout = TableLayout::of($table->definition);
        array_splice($layout->columns, 1, 1);

        self::assertSame('CREATE TABLE d.t (a INT) COLLATE utf8mb4_0900_ai_ci', (new Operation($session->semantics()->context(), $layout->statement()))->toString());
    }
}
