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

#[CoversClass(TableLayout::class)]
#[Small]
final class TableLayoutTest extends TestCase
{
    public function testRetainedPreservesSqlColumnsAndLeavesComputedIndexSlotsForRecompilation(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT,KEY ((a+1)))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        self::assertNotNull($table->definition->statement);
        [$columns] = TableLayout::retained($table->definition, TableLayout::elements($table->definition->statement));
        self::assertCount(1, $columns);
        self::assertSame('a', $columns[0][0]->name->column->value);
        self::assertSame(0, $columns[0][1]);
    }

    public function testElementsIndexesColumnDefinitionsWithoutKeys(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('CREATE TABLE t(A DOUBLE, b INT, PRIMARY KEY(b))');
        self::assertInstanceOf(CreateTable::class, $statement);

        self::assertSame(['a', 'b'], array_keys(TableLayout::elements($statement)));
        self::assertSame($statement->elements[0], TableLayout::elements($statement)['a']);
    }

    public function testOfRetainsHiddenFloatingWidthsAcrossCopiesAndChanges(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a FLOAT); INSERT INTO t VALUES(1.25); CREATE TABLE c AS SELECT NULLIF(a,NULL) x FROM t');
        $table = $session->instance->dictionary->table('d', 'c');
        self::assertNotNull($table);
        self::assertSame(['x' => 12], TableLayout::of($table->definition)->reportedLengths);
        $session->query('CREATE TABLE copied LIKE c; ALTER TABLE c ADD y INT; CREATE TABLE recopied AS SELECT x FROM c');
        self::assertSame(12, $session->instance->dictionary->table('d', 'copied')?->definition->columns[0]->domain->display);
        self::assertSame(12, $session->instance->dictionary->table('d', 'recopied')?->definition->columns[0]->domain->display);
        $session->query('ALTER TABLE c MODIFY x DOUBLE');
        self::assertNull($session->instance->dictionary->table('d', 'c')?->definition->columns[0]->domain->display);
    }

    public function testOfStatesTheKeysCollationsAndNullabilityOfTheTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT PRIMARY KEY, b VARCHAR(5) UNIQUE)');

        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $layout = TableLayout::of($table->definition);

        self::assertSame('CREATE TABLE d.t (a INT NOT NULL, b VARCHAR(5) COLLATE utf8mb4_0900_ai_ci, PRIMARY KEY (a), UNIQUE b (b)) COLLATE utf8mb4_0900_ai_ci', ((new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($session->semantics()->context(), $layout->statement(), new \SqlSemantics\Statement\Source\SourceMap()))->toString());
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
        $create = $session->analyze('CREATE TABLE t (a INT PRIMARY KEY UNIQUE)');
        self::assertInstanceOf(CreateTable::class, $create);
        $element = $create->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $element);

        $unkeyed = TableLayout::unkeyed($element, null, true);

        self::assertSame('CREATE TABLE t (a INT NOT NULL)', ((new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($session->semantics()->context(), new CreateTable(new QualifiedName(new Name('t')), [$unkeyed]), new \SqlSemantics\Statement\Source\SourceMap()))->toString());
    }

    public function testSpecifiedKeepsTheTypeOfAColumn(): void
    {
        $session = (new Instance())->connect();
        $create = $session->analyze('CREATE TABLE t (a INT NULL)');
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

        self::assertSame('CREATE TABLE d.t (a INT) COLLATE utf8mb4_0900_ai_ci', ((new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($session->semantics()->context(), $layout->statement(), new \SqlSemantics\Statement\Source\SourceMap()))->toString());
    }

    public function testRenamedAnswersTheNewNameOfAColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $layout = TableLayout::of($table->definition);
        array_splice($layout->columns, 1, 1);

        self::assertSame(['a', false, null], [$layout->renamed('A'), $layout->renamed('b'), $layout->renamed('z')]);
    }
}
