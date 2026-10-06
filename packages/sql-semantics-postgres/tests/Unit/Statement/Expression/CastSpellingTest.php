<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\CastSpelling;

#[CoversClass(CastSpelling::class)]
#[Small]
final class CastSpellingTest extends TestCase
{
    public function testCasesAreTheTwoSpellings(): void
    {
        self::assertSame([CastSpelling::Operator, CastSpelling::Function], CastSpelling::cases());
    }
}
