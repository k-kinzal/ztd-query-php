<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateVirtualTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\ModuleArgument;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;

#[CoversClass(CreateVirtualTable::class)]
#[Medium]
final class CreateVirtualTableTest extends TestCase
{
    public function testDeriveStatementDeclaresTheTableWithoutColumns(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE VIRTUAL TABLE aux.docs USING fts5(title, body)', []);
        $table = $operation->declarations()[0];

        self::assertSame('docs', $table->name->name->value);
        self::assertSame('aux', $table->name->schema?->value);
        self::assertSame([], $table->columns);
        self::assertSame([], $table->implicit);
        self::assertFalse($table->complete);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderKeepsEveryArgumentTextExactly(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("create virtual table if not exists docs using fts5( title ,body  TEXT,, tokenize = 'porter ascii', f(x , y) )");
        $statement = $operation->statement;

        self::assertInstanceOf(CreateVirtualTable::class, $statement);
        self::assertSame(['title', 'body  TEXT', '', "tokenize = 'porter ascii'", 'f(x , y)'], array_map(static fn (ModuleArgument $argument): string => $argument->text, $statement->arguments ?? []));
        self::assertSame("CREATE VIRTUAL TABLE IF NOT EXISTS docs USING fts5 (title, body  TEXT,, tokenize = 'porter ascii', f(x , y))", $operation->toString());
    }

    public function testRenderTellsAbsentParenthesesFromEmptyOnes(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $absent = $semantics->analyze('CREATE VIRTUAL TABLE t USING m');
        $empty = $semantics->analyze('CREATE VIRTUAL TABLE t USING m()');

        self::assertInstanceOf(CreateVirtualTable::class, $absent->statement);
        self::assertInstanceOf(CreateVirtualTable::class, $empty->statement);
        self::assertNull($absent->statement->arguments);
        self::assertCount(1, $empty->statement->arguments ?? []);
        self::assertSame('CREATE VIRTUAL TABLE t USING m', $absent->toString());
        self::assertSame('CREATE VIRTUAL TABLE t USING m ()', $empty->toString());
    }

    public function testRenderWritesANewlyBuiltDefinition(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = new Operation($semantics->context(), new CreateVirtualTable(new QualifiedName(new Name('t')), new Name('rtree'), [new ModuleArgument('id'), new ModuleArgument('minX'), new ModuleArgument('maxX')]));

        self::assertSame('CREATE VIRTUAL TABLE t USING rtree (id, minX, maxX)', $operation->toString());
    }
}
