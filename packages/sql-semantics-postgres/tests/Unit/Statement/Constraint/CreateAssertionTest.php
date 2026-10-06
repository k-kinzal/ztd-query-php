<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\CreateAssertion::class)]
#[Medium]
final class CreateAssertionTest extends TestCase
{
    public function testDeriveStatementReportsTheUnimplementedStatement(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE ASSERTION a CHECK (1 = 1)', []);
        self::assertSame([
          0 => 'CREATE ASSERTION is not yet implemented',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE ASSERTION s.a CHECK (1 = 1) DEFERRABLE', []);
        self::assertSame('CREATE ASSERTION s.a CHECK (1 = 1) DEFERRABLE', $statement->toString());
    }
}
