<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateIndex;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\LiteralColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TablePrimaryKey;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableUnique;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(LiteralColumn::class)]
#[Medium]
final class LiteralColumnTest extends TestCase
{
    public function testNameIsTheValueOfTheLiteral(): void
    {
        self::assertSame("a'b", (new LiteralColumn(new TextLiteral("a'b")))->name()->value);
    }

    public function testDeriveScalarResolvesTheColumnOfTheTableBeingDefined(): void
    {
        $create = (new Semantics(Dialect::Sqlite))->analyze("CREATE TABLE t (a INTEGER, b, UNIQUE ('b'), PRIMARY KEY ('A'))");
        $statement = $create->statement;
        $table = $create->declarations()[0];

        self::assertInstanceOf(CreateTable::class, $statement);
        $unique = $statement->constraints[0]->items[0];
        $key = $statement->constraints[1]->items[0];
        self::assertInstanceOf(TableUnique::class, $unique);
        self::assertInstanceOf(TablePrimaryKey::class, $key);
        self::assertInstanceOf(LiteralColumn::class, $unique->terms[0]->expression);
        self::assertInstanceOf(LiteralColumn::class, $key->terms[0]->expression);
        $resolution = $create->facts->scalar($unique->terms[0]->expression)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($table->columns[1], $resolution->slot->column);
        self::assertSame($table->columns[0], $table->implicit[0]->column);
        self::assertSame([], $create->facts->diagnostics);
        self::assertEquals(new Known(Storage::Text), $create->facts->scalar($key->terms[0]->expression->literal)->type);
    }

    public function testDeriveScalarReportsANameThatIsNoColumn(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a)');
        $create = $semantics->analyze("CREATE TABLE u (a, UNIQUE ('zz'))");
        $index = $semantics->analyze("CREATE INDEX i ON t ('zz')", [$table]);

        self::assertCount(1, $create->facts->diagnostics);
        self::assertInstanceOf(MissingColumn::class, $create->facts->diagnostics[0]);
        self::assertSame('zz', $create->facts->diagnostics[0]->name->value);
        self::assertInstanceOf(MissingColumn::class, $index->facts->diagnostics[0]);
    }

    public function testRenderWritesTheLiteralAsWritten(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $index = $semantics->analyze("create index i on t ('a' collate nocase desc, (('b')))");

        self::assertInstanceOf(CreateIndex::class, $index->statement);
        self::assertSame("CREATE INDEX i ON t ('a' COLLATE nocase DESC, (('b')))", $index->toString());
        self::assertSame("CREATE TABLE t (a, PRIMARY KEY ('a' COLLATE nocase COLLATE binary))", $semantics->analyze("CREATE TABLE t (a, PRIMARY KEY ('a' COLLATE nocase COLLATE binary))")->toString());
    }
}
