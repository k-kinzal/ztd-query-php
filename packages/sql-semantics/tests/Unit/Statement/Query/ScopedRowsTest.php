<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction\Expression\ColumnUse;
use SqlSemantics\Statement\Construction\Query\RowDefinition;
use SqlSemantics\Statement\Construction\Query\RowsDefinition;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query\ScopedRows;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(ScopedRows::class)]
#[Small]
final class ScopedRowsTest extends TestCase
{
    public function testContextUsesTheLexicalOwnersExactSnapshot(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')));
        $outer = new Scope($catalog);
        $rows = new ScopedRows($outer, new RowsDefinition(new RowDefinition(new NullConstant())));
        self::assertSame($catalog, $rows->context());
        self::assertSame($outer, $rows->scope->parent);
        self::assertFalse((new SemanticGraph())->isSemanticOperation($rows));
    }

    public function testProfileReadsTheFixedLexicalContext(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')));
        $rows = new ScopedRows(new Scope($catalog), new RowsDefinition(new RowDefinition(new NullConstant())));
        self::assertSame($catalog->profile, $rows->profile());
    }

    public function testWidthsPreservesInconsistentRowPositions(): void
    {
        $outer = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $rows = new ScopedRows($outer, new RowsDefinition(new RowDefinition(new NullConstant()), new RowDefinition(new NullConstant(), new NullConstant())));
        self::assertSame([1, 2], $rows->widths());
    }

    public function testToStringUsesOnlyTheNewlyDerivedOperands(): void
    {
        $outer = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $rows = new ScopedRows($outer, new RowsDefinition(new RowDefinition(new ColumnUse(new Name('id')))));
        self::assertSame('VALUES (id)', $rows->toString());
        self::assertSame($rows->scope, $rows->rows[0]->expressions[0]->references()[0]->scope);
    }
}
