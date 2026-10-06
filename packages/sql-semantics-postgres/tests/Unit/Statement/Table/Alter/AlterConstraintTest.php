<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterConstraint::class)]
#[Medium]
final class AlterConstraintTest extends TestCase
{
    public function testDeriveClauseChecksTheAttributes(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('ALTER TABLE t ALTER CONSTRAINT f NOT DEFERRABLE INITIALLY DEFERRED', []);
        self::assertSame([
          0 => 'Relation t does not exist.',
          1 => 'constraint declared INITIALLY DEFERRED must be DEFERRABLE',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('ALTER TABLE t ALTER CONSTRAINT f DEFERRABLE INITIALLY IMMEDIATE', []);
        self::assertSame('ALTER TABLE t ALTER CONSTRAINT f DEFERRABLE INITIALLY IMMEDIATE', $statement->toString());
    }
}
