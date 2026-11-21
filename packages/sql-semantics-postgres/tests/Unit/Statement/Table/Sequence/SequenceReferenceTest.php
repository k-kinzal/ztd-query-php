<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceReference::class)]
#[Medium]
final class SequenceReferenceTest extends TestCase
{
    public function testDeriveClauseHasNoOperand(): void
    {
        $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
        $statement = $semantics->inspect('CREATE SEQUENCE s OWNED BY t.a', []);
        self::assertSame([
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
        $statement = $semantics->inspect('CREATE TABLE t (a int GENERATED ALWAYS AS IDENTITY (SEQUENCE NAME x.s))', []);
        self::assertSame('CREATE TABLE t (a INT GENERATED ALWAYS AS IDENTITY (SEQUENCE NAME x.s))', $statement->toString());
    }

    public function testDeriveClauseReportsAnOwnerThatCannotOwn(): void
    {
        $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
        $context = [$semantics->inspect('CREATE MATERIALIZED VIEW m AS SELECT 1 AS a'), $semantics->inspect('CREATE VIEW v AS SELECT 1 AS a')];
        self::assertSame(['sequence cannot be owned by relation "m"'], array_map(static fn ($problem): string => $problem->message(), $semantics->inspect('CREATE SEQUENCE q OWNED BY m.a', $context)->facts->diagnostics));
        self::assertSame([], array_map(static fn ($problem): string => $problem->message(), $semantics->inspect('CREATE SEQUENCE q OWNED BY v.a', $context)->facts->diagnostics));
        self::assertSame([], array_map(static fn ($problem): string => $problem->message(), $semantics->inspect('CREATE SEQUENCE q OWNED BY NONE', $context)->facts->diagnostics));
    }
}
