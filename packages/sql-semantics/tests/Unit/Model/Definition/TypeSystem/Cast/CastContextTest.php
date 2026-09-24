<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\Cast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\TypeSystem\Cast\CastContext;

#[CoversClass(CastContext::class)]
final class CastContextTest extends TestCase
{
    public function testSpellsTheSqlClauses(): void
    {
        self::assertSame(['', 'AS ASSIGNMENT', 'AS IMPLICIT'], array_map(static fn (CastContext $context): string => $context->value, CastContext::cases()));
    }
}
