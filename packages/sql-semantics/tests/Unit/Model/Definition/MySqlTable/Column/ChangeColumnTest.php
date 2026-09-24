<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Column\ChangeColumn;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ChangeColumn::class)]
#[Medium]
final class ChangeColumnTest extends TestCase
{
    public function testReadsTheOldNameAndTheNewDeclaration(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t CHANGE n total BIGINT NOT NULL');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(ChangeColumn::class, $alteration);
        self::assertSame('n', $alteration->column);
        self::assertSame('total', $alteration->definition->name);
        self::assertNull($alteration->position);
    }

    public function testRejectsAnEmptyOldName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t CHANGE n total BIGINT');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(ChangeColumn::class, $alteration);
        $this->expectException(InvalidStructure::class);
        new ChangeColumn('', $alteration->definition);
    }
}
