<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Reindex;

#[CoversClass(Reindex::class)]
#[Medium]
final class ReindexTest extends TestCase
{
    public function testDeriveStatementRecordsNoResolutionForTheName(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('REINDEX nocase', []);

        self::assertSame([], $operation->facts->diagnostics);
        self::assertFalse($operation->facts->covers($operation->statement));
    }

    public function testRenderWritesTheOptionalTarget(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $qualified = $semantics->analyze('reindex main.users');

        self::assertInstanceOf(Reindex::class, $qualified->statement);
        self::assertSame('main', $qualified->statement->target?->schema?->value);
        self::assertSame('REINDEX main.users', $qualified->toString());
        self::assertSame('REINDEX', $semantics->analyze('REINDEX')->toString());
    }
}
