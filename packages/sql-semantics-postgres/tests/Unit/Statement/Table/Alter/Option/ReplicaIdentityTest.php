<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Alter\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\ReplicaIdentity::class)]
#[Medium]
final class ReplicaIdentityTest extends TestCase
{
    public function testDeriveClauseHasNoOperand(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('ALTER TABLE t REPLICA IDENTITY NOTHING', []);
        self::assertSame([
          0 => 'Relation t does not exist.',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('ALTER TABLE t REPLICA IDENTITY USING INDEX k', []);
        self::assertSame('ALTER TABLE t REPLICA IDENTITY USING INDEX k', $statement->toString());
    }

    public function testRefusesAnIndexForFull(): void
    {
        $this->expectExceptionMessage('An index is named exactly for USING INDEX.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\ReplicaIdentity(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\ReplicaIdentityKind::Full, new \SqlSemantics\Statement\Identifier\Name('k'));
    }
}
