<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Foreign;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Foreign\DropForeignOption;
use SqlSemantics\Model\Statement\Definition\PostgreSql\AlterForeignDataWrapperStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropForeignOption::class)]
#[Medium]
final class DropForeignOptionTest extends TestCase
{
    public function testRemovalHasARequiredNameWithoutAValueOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FOREIGN DATA WRAPPER fdw OPTIONS (DROP format)');
        self::assertInstanceOf(AlterForeignDataWrapperStatement::class, $statement);
        self::assertInstanceOf(DropForeignOption::class, $statement->options[0]);
        self::assertSame('format', $statement->options[0]->name);
    }

    public function testRemovalRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        new DropForeignOption('');
    }

}
