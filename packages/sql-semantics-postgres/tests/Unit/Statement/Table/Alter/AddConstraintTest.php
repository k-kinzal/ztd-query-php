<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AddConstraint::class)]
#[Medium]
final class AddConstraintTest extends TestCase
{
    public function testDeriveClauseDerivesTheConstraintAgainstTheTable(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('ALTER TABLE t ADD CHECK (zz > 0)', $context);
        self::assertSame([
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('ALTER TABLE t ADD CONSTRAINT k UNIQUE (a) DEFERRABLE', []);
        self::assertSame('ALTER TABLE t ADD CONSTRAINT k UNIQUE (a) DEFERRABLE', $statement->toString());
    }

    public function testRefusesAColumnConstraint(): void
    {
        $this->expectExceptionMessage('ADD takes a table constraint.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AddConstraint(new \SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\NotNull());
    }
}
