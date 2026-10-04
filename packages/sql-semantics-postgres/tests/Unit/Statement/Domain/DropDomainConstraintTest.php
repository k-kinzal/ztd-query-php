<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Domain\DropDomainConstraint::class)]
#[Medium]
final class DropDomainConstraintTest extends TestCase
{
    public function testRenderWritesTheBehavior(): void
    {
        self::assertSame('ALTER DOMAIN d DROP CONSTRAINT IF EXISTS c CASCADE', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DOMAIN d DROP CONSTRAINT IF EXISTS c CASCADE')->toString());
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DOMAIN d DROP CONSTRAINT c')->facts->diagnostics);
    }
}
