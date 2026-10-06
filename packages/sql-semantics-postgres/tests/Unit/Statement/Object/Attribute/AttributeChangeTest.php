<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\AttributeChange;

#[CoversClass(AttributeChange::class)]
#[Medium]
final class AttributeChangeTest extends TestCase
{
    public function testRenderKeepsNoneAndTheNameAlone(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql, 'pg-17.2'))->analyze('ALTER OPERATOR = (int4, int4) SET (restrict = NONE, join, merges = true)');
        self::assertSame('ALTER OPERATOR = (int4, int4) SET (restrict = NONE, join, merges = TRUE)', $operation->toString());
    }

    public function testDeriveClauseDerivesTheValue(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('ALTER TYPE t SET (receive = r, send = NONE)');
        self::assertSame([], $operation->facts->diagnostics);
    }
}
