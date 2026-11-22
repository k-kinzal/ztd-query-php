<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceFlag::class)]
#[Medium]
final class SequenceFlagTest extends TestCase
{
    public function testDeriveClauseHasNoOperand(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('CREATE SEQUENCE s CYCLE', []);
        self::assertSame([
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('ALTER SEQUENCE s NO CYCLE NO MAXVALUE RESTART LOGGED', []);
        self::assertSame('ALTER SEQUENCE s NO CYCLE NO MAXVALUE RESTART LOGGED', $statement->toString());
    }
}
