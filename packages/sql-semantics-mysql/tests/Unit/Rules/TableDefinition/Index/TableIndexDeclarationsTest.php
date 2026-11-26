<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexDeclaration;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\IndexCandidate;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\TableIndexDeclarations;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;

#[CoversClass(TableIndexDeclarations::class)]
#[Medium]
final class TableIndexDeclarationsTest extends TestCase
{
    public function testCollectUsesElementOrderAndCopiesPhysicalNamesThroughLike(): void
    {
        $context = (new Semantics())->context([
            'CREATE TABLE app.events (Note INT UNIQUE, other INT, INDEX note_2(other), KEY (note,other), UNIQUE(note))',
            'CREATE TEMPORARY TABLE app.local_events LIKE app.events',
        ]);
        self::assertInstanceOf(AnalysisContext::class, $context);
        $indexes = $context->tables[1]->indexes;
        self::assertNotNull($indexes);

        self::assertSame(['Note', 'note_2', 'Note_3', 'Note_4'], array_map(static fn (IndexDeclaration $index): string => $index->name->value, $indexes));
    }


    public function testCollectAllocatesAndCopiesImplicitSupportWithItsPhysicalName(): void
    {
        $catalog = (new Semantics())->context([
            'CREATE TABLE app.p (a INT PRIMARY KEY)',
            'CREATE TABLE app.c (X INT AUTO_INCREMENT, y INT, z INT PRIMARY KEY, KEY x (y), FOREIGN KEY (x) REFERENCES p (a))',
            'CREATE TABLE app.copy LIKE app.c',
        ]);
        self::assertInstanceOf(AnalysisContext::class, $catalog);
        $original = $catalog->tables[1]->indexes;
        $copy = $catalog->tables[2]->indexes;
        self::assertNotNull($original);
        self::assertNotNull($copy);
        self::assertSame('X_2', $original[2]->name->value);
        self::assertSame('X', $original[2]->autoIncrementField?->value);
        self::assertSame('X_2', $copy[2]->name->value);
        self::assertSame('X', $copy[2]->autoIncrementField?->value);
        self::assertCount(1, $catalog->foreignKeys);
    }

    public function testAllocateUsesTheOriginalNativeNamingOrder(): void
    {
        $x = new Name('x');
        $y = new Name('y');
        $generated = new IndexCandidate(IndexKind::Index, null, $x, true, false, null, [[$x, false]]);
        $explicit = new IndexCandidate(IndexKind::Index, $x, $y, true, false, null, [[$y, false]]);
        $collector = new TableIndexDeclarations();
        self::assertNull($collector->allocate([$generated, $explicit], 0, GrammarRelease::MySql910));
        $resolved = $collector->allocate([$explicit, $generated], 0, GrammarRelease::MySql910);
        self::assertNotNull($resolved);
        self::assertSame('x_2', $resolved[1]->name->value);
    }
}
