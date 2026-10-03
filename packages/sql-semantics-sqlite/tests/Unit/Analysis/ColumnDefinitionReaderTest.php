<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\Analysis\ColumnDefinitionReader;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Schema\Definition\ColumnNullability;
use SqlSemantics\Statement\Schema\Definition\ColumnPrimaryKey;
use SqlSemantics\Statement\Schema\Definition\ColumnUnique;
use SqlSemantics\Statement\Schema\Definition\ConflictAction;
use SqlSemantics\Statement\Schema\Definition\KeyDirection;

#[CoversClass(ColumnDefinitionReader::class)]
#[Medium]
final class ColumnDefinitionReaderTest extends TestCase
{
    public function testReadDerivesTheSharedColumnDeclaration(): void
    {
        $tree = (new SqliteParser())->parse('CREATE TABLE bar (foo INTEGER NOT NULL)');
        $column = (new ColumnDefinitionReader())->read($tree->find('columnname')[0], $tree->find('carglist')[0]);
        self::assertSame('foo', $column->column->name->value);
        self::assertSame(Builtin::Integer, $column->column->type->name);
        self::assertSame(Nullability::NotNull, $column->column->nullability);
    }

    public function testConstraintsAttachNamesToTheirTypedRule(): void
    {
        $tree = (new SqliteParser())->parse('CREATE TABLE bar (foo INT CONSTRAINT required NOT NULL CONSTRAINT uq UNIQUE)');
        $rules = (new ColumnDefinitionReader())->constraints($tree->find('carglist')[0]);
        self::assertCount(2, $rules);
        self::assertInstanceOf(ColumnNullability::class, $rules[0]);
        self::assertInstanceOf(ColumnUnique::class, $rules[1]);
        self::assertSame('required', $rules[0]->name?->value);
        self::assertSame('uq', $rules[1]->name?->value);
    }

    public function testRulePreservesPrimaryKeyOrderAndAllocationRequests(): void
    {
        $tree = (new SqliteParser())->parse('CREATE TABLE bar (foo INTEGER PRIMARY KEY DESC ON CONFLICT FAIL AUTOINCREMENT)');
        $rule = (new ColumnDefinitionReader())->rule($tree->find('ccons')[0]);
        self::assertInstanceOf(ColumnPrimaryKey::class, $rule);
        self::assertSame(KeyDirection::Descending, $rule->direction);
        self::assertTrue($rule->autoIncrement);
        self::assertSame(ConflictAction::Fail, $rule->conflict);
    }

    public function testConflictDecodesAnExplicitViolationPolicy(): void
    {
        $tree = (new SqliteParser())->parse('CREATE TABLE bar (foo TEXT NOT NULL ON CONFLICT IGNORE)');
        self::assertSame(ConflictAction::Ignore, (new ColumnDefinitionReader())->conflict($tree->find('ccons')[0]));
    }
}
