<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexDeclaration;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\TableIndexDeclarations;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Testing\MySql\Inspection;

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

    public function testExplicitSupportUsesOrderedSettledFields(): void
    {
        $sql = 'CREATE TABLE app.c (x INT, y INT, KEY xy(x,y), FOREIGN KEY (x) REFERENCES app.p (a))';
        $catalog = (new Semantics())->context([$sql]);
        self::assertInstanceOf(AnalysisContext::class, $catalog);
        $indexes = $catalog->tables[0]->indexes;
        self::assertNotNull($indexes);
        $statement = (new Inspection())->inspect($sql)->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $collector = new TableIndexDeclarations();

        self::assertTrue($collector->explicitSupport($statement, $indexes, GrammarRelease::MySql910));
        self::assertFalse($collector->explicitSupport($statement, [], GrammarRelease::MySql910));
    }
}
