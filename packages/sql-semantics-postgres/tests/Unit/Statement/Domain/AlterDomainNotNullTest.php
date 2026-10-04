<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Domain\AlterDomainNotNull::class)]
#[Medium]
final class AlterDomainNotNullTest extends TestCase
{
    public function testRenderWritesSet(): void
    {
        self::assertSame('ALTER DOMAIN d SET NOT NULL', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DOMAIN d SET NOT NULL')->toString());
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DOMAIN d DROP NOT NULL')->facts->diagnostics);
    }
}
