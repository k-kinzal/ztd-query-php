<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Schema\ColumnAttributes as Subject;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use Tests\Statement\MySqlStatements;

#[CoversClass(Subject::class)]
final class ColumnAttributesTest extends TestCase
{
    public function testReadDefaultsToAPlainColumn(): void
    {
        $attributes = (new Subject())->read(MySqlStatements::columns('id INT')[0]->specification->columnAttributes());

        self::assertFalse($attributes->autoIncrement);
        self::assertFalse($attributes->primaryKey);
        self::assertNull($attributes->default);
    }

    public function testReadRecognizesDefaultAndAutoIncrement(): void
    {
        $attributes = (new Subject())->read(MySqlStatements::columns("id INT NOT NULL DEFAULT 1 AUTO_INCREMENT UNIQUE KEY COMMENT 'x'")[0]->specification->columnAttributes());

        self::assertTrue($attributes->autoIncrement);
        self::assertFalse($attributes->primaryKey);
        self::assertInstanceOf(NumberLiteral::class, $attributes->default);
    }

    public function testReadTreatsPrimaryKeyAndBareKeyAsPrimary(): void
    {
        $columns = MySqlStatements::columns('a INT PRIMARY KEY, b INT UNIQUE KEY');
        $bare = (MySqlStatements::semantics())->analyze('CREATE TABLE t (b INT KEY)')->statement;
        self::assertInstanceOf(CreateTable::class, $bare);
        $key = $bare->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $key);

        self::assertTrue((new Subject())->read($columns[0]->specification->columnAttributes())->primaryKey);
        self::assertFalse((new Subject())->read($columns[1]->specification->columnAttributes())->primaryKey);
        self::assertTrue((new Subject())->read($key->specification->columnAttributes())->primaryKey);
    }

    public function testReadTreatsSerialDefaultValueAsAutoIncrement(): void
    {
        $attributes = (new Subject())->read(MySqlStatements::columns('a INT SERIAL DEFAULT VALUE')[0]->specification->columnAttributes());

        self::assertTrue($attributes->autoIncrement);
        self::assertNull($attributes->default);
    }

    public function testReadLetsTheLastDefaultDecide(): void
    {
        $columns = MySqlStatements::columns("a INT DEFAULT 1 DEFAULT 2, b VARCHAR(3) DEFAULT 'x' DEFAULT ('y')");

        $first = (new Subject())->read($columns[0]->specification->columnAttributes())->default;
        self::assertInstanceOf(NumberLiteral::class, $first);
        self::assertSame('2', $first->text);
        self::assertNull((new Subject())->read($columns[1]->specification->columnAttributes())->default);
    }
}
