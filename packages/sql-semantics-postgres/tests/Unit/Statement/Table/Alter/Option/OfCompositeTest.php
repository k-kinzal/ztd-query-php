<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Alter\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\OfComposite::class)]
#[Medium]
final class OfCompositeTest extends TestCase
{
    public function testDeriveClauseHasNoOperand(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('ALTER TABLE t OF ty', []);
        self::assertSame([
          0 => 'Relation t does not exist.',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('ALTER TABLE t OF s.ty', []);
        self::assertSame('ALTER TABLE t OF s.ty', $statement->toString());
    }
}
