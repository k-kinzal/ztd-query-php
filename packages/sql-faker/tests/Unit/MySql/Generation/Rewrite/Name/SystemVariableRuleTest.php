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
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Name\HostNameRule::class)]
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

    #[DataProvider('providerPrefixes')]
    public function testRewriteRejectsReservedComponentPrefixesOnlyInTheVariableName(string $scope, string $prefix, string $separator, string $expected): void
    {
        $tokens = array_map(static fn (string $name, int $id): TerminalOccurrence => new TerminalOccurrence($name, $id, [0], [$scope]), [$prefix, $separator, 'IDENT'], [0, 1, 2]);
        $input = new TerminalSequence($tokens, $tokens, productions: [new ProductionOccurrence(0, null, $scope, 0)]);
        $result = (new SystemVariableRule())->rewrite($input);
        self::assertSame([$expected, $separator, 'IDENT'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame(array_column($tokens, 'id'), array_column($result->terminals, 'id'));
        self::assertSame($result, (new SystemVariableRule())->rewrite($result));
    }

    /**
     * @return list<array{string, string, string, string}>
     */
    public static function providerPrefixes(): array
    {
        return [
            ['rvalue_system_variable', 'GLOBAL_SYM', '.', 'IDENT_QUOTED'],
            ['rvalue_system_variable', 'LOCAL_SYM', '.', 'IDENT_QUOTED'],
            ['lvalue_variable', 'SESSION_SYM', '.', 'IDENT_QUOTED'],
            ['lvalue_variable', 'DEFAULT_SYM', '.', 'DEFAULT_SYM'],
            ['rvalue_system_variable', 'IDENT', '.', 'IDENT'],
            ['rvalue_system_variable', 'SESSION_SYM', 'EQ', 'SESSION_SYM'],
            ['opt_rvalue_system_variable_type', 'GLOBAL_SYM', '.', 'GLOBAL_SYM'],
        ];
    }
}
