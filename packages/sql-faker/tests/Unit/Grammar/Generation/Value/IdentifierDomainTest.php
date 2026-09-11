<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Value\CharacterDomain;
use SqlFaker\Grammar\Generation\Value\IdentifierDomain;
use Tests\Fixtures\SqlFaker\PhpDiagnosticGuard;

#[CoversClass(IdentifierDomain::class)]
#[UsesClass(CharacterDomain::class)]
final class IdentifierDomainTest extends TestCase
{
    public function testChooseKeepsTheSafePrefixAndLengthBudget(): void
    {
        $domain = new IdentifierDomain('a_', 'a0_', '_sf', 5);
        self::assertSame('_sf', $domain->choose(static fn (int $count): int => 0));
        self::assertSame('_sf__', $domain->choose(static fn (int $count): int => $count - 1));
    }

    public function testMatchUsesStartAndContinuationClassesWithoutRequiringTheSamplePrefix(): void
    {
        $domain = new IdentifierDomain('a_', 'a0_');
        self::assertSame([2, 3, 4], PhpDiagnosticGuard::run(static fn (): array => $domain->match('!a0_ ', 1)));
        self::assertSame([], PhpDiagnosticGuard::run(static fn (): array => $domain->match('0a')));
        self::assertSame([], PhpDiagnosticGuard::run(static fn (): array => $domain->match('')));
        self::assertNotContains(3, PhpDiagnosticGuard::run(static fn (): array => (new IdentifierDomain('Ss', 'QqLl', excluded: ['SQL']))->match('sQl')));
    }
}
