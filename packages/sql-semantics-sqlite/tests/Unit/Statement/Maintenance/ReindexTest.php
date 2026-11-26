<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Reindex;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Reindex::class)]
#[Medium]
final class ReindexTest extends TestCase
{
    public function testDeriveStatementRecordsNoResolutionForTheName(): void
    {
        $operation = (new Inspection())->inspect('REINDEX nocase', []);

        self::assertSame([], $operation->facts->diagnostics);
        self::assertFalse($operation->facts->covers($operation->statement));
    }

    public function testRenderWritesTheOptionalTarget(): void
    {
        $semantics = new Inspection();
        $qualified = $semantics->inspect('reindex main.users');

        self::assertInstanceOf(Reindex::class, $qualified->statement);
        self::assertSame('main', $qualified->statement->target?->schema?->value);
        self::assertSame('REINDEX main.users', $qualified->toString());
        self::assertSame('REINDEX', $semantics->inspect('REINDEX')->toString());
    }
}
