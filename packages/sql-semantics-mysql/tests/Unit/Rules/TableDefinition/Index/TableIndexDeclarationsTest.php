<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexDeclaration;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\TableIndexDeclarations;
use SqlSemantics\Platform\MySql\Semantics;

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
}
