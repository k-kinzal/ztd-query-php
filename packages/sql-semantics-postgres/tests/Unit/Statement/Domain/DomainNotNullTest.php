<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Domain\DomainNotNull::class)]
#[Medium]
final class DomainNotNullTest extends TestCase
{
    public function testRenderWritesTheName(): void
    {
        self::assertSame('ALTER DOMAIN d ADD CONSTRAINT nn NOT NULL', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DOMAIN d ADD CONSTRAINT nn NOT NULL')->toString());
    }

    public function testDeriveClauseReportsNotValid(): void
    {
        self::assertSame('NOT NULL constraints cannot be marked NOT VALID', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DOMAIN d ADD NOT NULL NOT VALID')->facts->diagnostics[0]->message());
    }
}
