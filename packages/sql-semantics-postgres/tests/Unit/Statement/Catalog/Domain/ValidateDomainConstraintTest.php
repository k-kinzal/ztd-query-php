<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Domain\ValidateDomainConstraint::class)]
#[Medium]
final class ValidateDomainConstraintTest extends TestCase
{
    public function testRenderWritesValidate(): void
    {
        self::assertSame('ALTER DOMAIN d VALIDATE CONSTRAINT c', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DOMAIN d VALIDATE CONSTRAINT c')->toString());
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DOMAIN s.d VALIDATE CONSTRAINT c')->facts->diagnostics);
    }
}
