<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Value\CharacterDomain;
use SqlFaker\Grammar\Generation\Value\DollarQuotedDomain;

#[CoversClass(DollarQuotedDomain::class)]
#[UsesClass(CharacterDomain::class)]
final class DollarQuotedDomainTest extends TestCase
{
    public function testChooseReusesTheChosenTagWithoutASecondTagDecision(): void
    {
        $domain = new DollarQuotedDomain(new CharacterDomain(['a', 'b'], 1, 1), new CharacterDomain(['x'], 1, 1));
        self::assertSame('$b$x$b$', $domain->choose(static fn (int $count): int => $count - 1));
        self::assertSame('$$x$$', (new DollarQuotedDomain(new CharacterDomain(['a'], 0, 0), new CharacterDomain(['x'], 1, 1)))->choose(static fn (int $count): int => 0));
    }
}
