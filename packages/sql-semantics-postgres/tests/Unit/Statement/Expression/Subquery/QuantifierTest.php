<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Subquery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\Quantifier;

#[CoversClass(Quantifier::class)]
#[Small]
final class QuantifierTest extends TestCase
{
    public function testValuesAreTheKeywords(): void
    {
        self::assertSame(['ANY', 'SOME', 'ALL'], array_map(static fn (Quantifier $quantifier): string => $quantifier->value, Quantifier::cases()));
    }
}
