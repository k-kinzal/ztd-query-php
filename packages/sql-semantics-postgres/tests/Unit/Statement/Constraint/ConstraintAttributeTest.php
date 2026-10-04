<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute::class)]
#[Medium]
final class ConstraintAttributeTest extends TestCase
{
    public function testDeferralIsTrueForTheDeferralAttributes(): void
    {
        self::assertSame([
          0 => true,
          1 => false,
        ], [\SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute::InitiallyDeferred->deferral(), \SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute::NotValid->deferral()]);
    }

    public function testDeriveClauseHasNoOperand(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int REFERENCES u DEFERRABLE)', []);
        self::assertSame(1, count($statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int, CHECK (a > 0) NOT VALID NO INHERIT, UNIQUE (a) NOT DEFERRABLE INITIALLY IMMEDIATE)', []);
        self::assertSame('CREATE TABLE t (a INT, CHECK (a > 0) NOT VALID NO INHERIT, UNIQUE (a) NOT DEFERRABLE INITIALLY IMMEDIATE)', $statement->toString());
    }
}
