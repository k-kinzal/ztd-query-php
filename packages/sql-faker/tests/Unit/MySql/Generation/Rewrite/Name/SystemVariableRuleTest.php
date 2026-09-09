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
use SqlFaker\MySql\Generation\Rewrite\Name\SystemVariableRule;

#[CoversClass(SystemVariableRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class SystemVariableRuleTest extends TestCase
{
    /**
     * @param list<string> $names
     * @param list<string> $expected
     */
    #[DataProvider('providerVariables')]
    public function testRewritePreservesTheScannerContext(array $names, array $expected): void
    {
        $input = TerminalSequence::fromNames($names);
        $result = (new SystemVariableRule())->rewrite($input);
        self::assertSame($expected, $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame(array_column($input->terminals, 'id'), array_column($result->terminals, 'id'));
        self::assertSame($result, (new SystemVariableRule())->rewrite($result));
    }

    /**
     * @return list<array{list<string>, list<string>}>
     */
    public static function providerVariables(): array
    {
        return [[['@', '@', 'TEXT_STRING'], ['@', '@', 'IDENT_QUOTED']], [['@', 'TEXT_STRING'], ['@', 'TEXT_STRING']], [['@', '@', 'IDENT_QUOTED'], ['@', '@', 'IDENT_QUOTED']], [['@', '@', 'GLOBAL_SYM', '.', 'TEXT_STRING'], ['@', '@', 'GLOBAL_SYM', '.', 'TEXT_STRING']], [['TEXT_STRING'], ['TEXT_STRING']]];
    }
}
