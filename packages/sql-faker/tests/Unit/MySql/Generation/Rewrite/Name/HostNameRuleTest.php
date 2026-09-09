<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Rewrite\Name\HostNameRule;

#[CoversClass(HostNameRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class HostNameRuleTest extends TestCase
{
    /**
     * @param list<string> $input
     * @param list<string> $expected
     */
    #[DataProvider('providerStates')]
    public function testRewriteRequiresTheSingleAtSignScannerState(array $input, array $expected): void
    {
        $sequence = TerminalSequence::fromNames($input);
        $rule = new HostNameRule();
        $result = $rule->rewrite($sequence);
        self::assertSame($expected, $result->names());
        self::assertSame($sequence->original, $result->original);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return list<array{list<string>, list<string>}>
     */
    public static function providerStates(): array
    {
        return [
            [['LEX_HOSTNAME'], ['IDENT']],
            [['SERVER', 'LEX_HOSTNAME'], ['SERVER', 'IDENT']],
            [['LEX_HOSTNAME', '@', 'LEX_HOSTNAME'], ['IDENT', '@', 'LEX_HOSTNAME']],
            [['@', 'LEX_HOSTNAME'], ['@', 'LEX_HOSTNAME']],
            [['@', '@', 'LEX_HOSTNAME'], ['@', '@', 'IDENT']],
            [['IDENT'], ['IDENT']],
        ];
    }
}
