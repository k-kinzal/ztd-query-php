<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\CreateIndex;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\CreateIndexLowering;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(CreateIndexLowering::class)]
#[Small]
final class CreateIndexLoweringTest extends TestCase
{
    public function testLowerResolvesTheTempTableAndNativeQuotedKeyRole(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context([
            'CREATE TABLE main.items (code TEXT, n INTEGER)',
            'CREATE TABLE temp.items (n INTEGER, code TEXT)',
        ]);
        $index = $semantics->analyze("CREATE INDEX indexes ON items('CODE' DESC) WHERE (?09 + ?1) NOT IN ()", $context);

        self::assertInstanceOf(CreateIndex::class, $index);
        self::assertSame('temp', $index->schema->value);
        self::assertInstanceOf(ColumnReference::class, $index->terms[0]->expression);
        self::assertSame(1, $index->terms[0]->expression->slot);
        self::assertInstanceOf(FoldedMembership::class, $index->where);
        self::assertSame(['09', '1'], array_column($index->where->bindings, 'label'));
    }
}
