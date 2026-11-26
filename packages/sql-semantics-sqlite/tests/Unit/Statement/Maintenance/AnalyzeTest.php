<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Analyze;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Analyze::class)]
#[Medium]
final class AnalyzeTest extends TestCase
{
    public function testDeriveStatementRecordsNoResolutionForTheName(): void
    {
        $operation = (new Inspection())->inspect('ANALYZE users', []);

        self::assertSame([], $operation->facts->diagnostics);
        self::assertFalse($operation->facts->covers($operation->statement));
    }

    public function testRenderWritesTheOptionalTarget(): void
    {
        $semantics = new Inspection();
        $qualified = $semantics->inspect('analyze main.users');

        self::assertInstanceOf(Analyze::class, $qualified->statement);
        self::assertSame('users', $qualified->statement->target?->name->value);
        self::assertSame('ANALYZE main.users', $qualified->toString());
        self::assertSame('ANALYZE', $semantics->inspect('ANALYZE')->toString());
    }
}
