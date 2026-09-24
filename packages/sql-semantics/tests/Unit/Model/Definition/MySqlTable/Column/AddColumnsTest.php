<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Column\AddColumns;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AddColumns::class)]
#[Medium]
final class AddColumnsTest extends TestCase
{
    public function testReadsColumnsConstraintsAndIndexes(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ADD (a INT, b INT, UNIQUE (a), KEY ix (b))');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(AddColumns::class, $alteration);
        self::assertSame(['a', 'b'], array_map(static fn ($column): string => $column->name, $alteration->columns));
        self::assertCount(1, $alteration->constraints);
        self::assertSame('ix', $alteration->indexes[0]->name);
    }

    public function testRejectsAnotherDialect(): void
    {
        $column = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)')->tables[0]->columns[0];
        $this->expectException(InvalidStructure::class);
        new AddColumns([$column]);
    }
}
