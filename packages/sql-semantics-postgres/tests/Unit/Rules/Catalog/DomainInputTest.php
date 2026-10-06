<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Catalog\DomainInput::class)]
#[Medium]
final class DomainInputTest extends TestCase
{
    public function testEnvironmentSeesValue(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DOMAIN d text CHECK (length(VALUE) > 0)')->facts->diagnostics);
    }

    public function testUndeclaredDependsOnTheDomain(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DOMAIN s.d ADD CHECK (VALUE IS NOT NULL)')->facts->diagnostics);
    }
}
