<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Declaration\ColumnDefinition;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\SchemaReader;
use SqlSemantics\Dialect;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(ColumnDefinition::class)]
#[Medium]
final class ColumnDefinitionTest extends TestCase
{
    public function testReaderRetainsDefaultGeneratedAndAttributeSyntax(): void
    {
        $tree = (new DialectParser(Dialect::PostgreSql))->parse('CREATE TABLE t(id INTEGER NOT NULL DEFAULT 1 COLLATE "C", n TEXT GENERATED ALWAYS AS (id) STORED)');
        $table = (new SchemaReader(new Identifiers(Dialect::PostgreSql), 'public'))->table($tree);
        $id = $table->columns[0];
        self::assertSame('id', $id->name);
        self::assertSame('integer', $id->type->name);
        self::assertSame(Nullability::NotNull, $id->nullability);
        self::assertSame('DEFAULT 1', trim($id->defaultExpression?->toString() ?? ''));
        self::assertNull($id->generatedExpression);
        self::assertSame(['NOT NULL', 'DEFAULT 1', 'COLLATE "C"'], array_map(static fn (Node $attribute): string => trim($attribute->toString()), $id->attributes));
        self::assertSame(['collation' => 'C'], $id->options);
        self::assertSame('id INTEGER NOT NULL DEFAULT 1 COLLATE "C"', trim($id->source->toString()));
        $n = $table->columns[1];
        self::assertSame(Nullability::MaybeNull, $n->nullability);
        self::assertNull($n->defaultExpression);
        self::assertSame('id', trim($n->generatedExpression?->toString() ?? ''));
        self::assertSame(['generated_storage' => 'stored'], $n->options);
    }

    public function testDefaultsLeaveOptionalSyntaxAbsent(): void
    {
        $source = new Node('columnDef', 0, []);
        $column = new ColumnDefinition('n', TypeDescriptor::builtin(Dialect::MySql, 'integer'), Nullability::Unknown, $source);
        self::assertSame('n', $column->name);
        self::assertSame($source, $column->source);
        self::assertNull($column->defaultExpression);
        self::assertSame([], $column->attributes);
        self::assertNull($column->generatedExpression);
        self::assertSame([], $column->options);
    }
}
