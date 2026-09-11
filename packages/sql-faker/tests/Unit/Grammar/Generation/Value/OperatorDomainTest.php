<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Value\CharacterDomain;
use SqlFaker\Grammar\Generation\Value\OperatorDomain;
use Tests\Fixtures\SqlFaker\PhpDiagnosticGuard;

#[CoversClass(OperatorDomain::class)]
#[UsesClass(CharacterDomain::class)]
final class OperatorDomainTest extends TestCase
{
    public function testChooseConstructsOperatorsWithoutCommentOpeners(): void
    {
        $domain = new OperatorDomain('+*/<>=!@#%^&|`?~-', '~!@#^&|`?%', ['+'], ['--', '/*']);
        self::assertSame('?', $domain->choose(static fn (int $count): int => 0));
        self::assertSame('?' . str_repeat('~', 31), $domain->choose(static fn (int $count): int => $count - 1));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerOperators')]
    public function testMatchHonorsFixedTokensCommentsAndTrailingSignRules(string $value, bool $valid): void
    {
        $domain = new OperatorDomain('+*/<>=!@#%^&|`?~-', '~!@#^&|`?%', ['+', '-', '/', '<=', '!='], ['--', '/*']);
        self::assertSame($valid, in_array(strlen($value), PhpDiagnosticGuard::run(static fn (): array => $domain->match($value)), true));
    }

    /**
     * @return iterable<array{string, bool}>
     */
    public static function providerOperators(): iterable
    {
        foreach (['?', '?|', '|-', '?+'] as $value) {
            yield [$value, true];
        }
        foreach (['+', '<=', '!=', '--', '/*', '>-', 'a', str_repeat('?', 64)] as $value) {
            yield [$value, false];
        }
    }

}
