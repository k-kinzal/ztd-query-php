<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Domain\DomainCheck::class)]
#[Medium]
final class DomainCheckTest extends TestCase
{
    public function testRenderWritesTheName(): void
    {
        self::assertSame('ALTER DOMAIN d ADD CONSTRAINT c CHECK (value <> 0)', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DOMAIN d ADD CONSTRAINT c CHECK (VALUE <> 0)')->toString());
    }

    public function testDeriveClauseReportsDeferrable(): void
    {
        self::assertSame('CHECK constraints cannot be marked DEFERRABLE', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DOMAIN d ADD CHECK (VALUE <> 0) DEFERRABLE')->facts->diagnostics[0]->message());
    }

    public function testDeriveClauseReportsNoInherit(): void
    {
        self::assertSame('check constraints for domains cannot be marked NO INHERIT', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DOMAIN d ADD CHECK (VALUE <> 0) NO INHERIT')->facts->diagnostics[0]->message());
    }
}
