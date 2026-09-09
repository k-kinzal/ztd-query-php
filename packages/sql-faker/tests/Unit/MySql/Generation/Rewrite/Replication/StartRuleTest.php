<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;
use SqlFaker\MySql\Generation\Rewrite\Replication\StartRule;

#[CoversClass(StartRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
final class StartRuleTest extends TestCase
{
    /**
     * @param list<string> $threads
     * @param list<string> $authentication
     */
    #[DataProvider('providerStarts')]
    public function testRewriteEnablesTheIoThreadOnlyWhenAuthenticationRequiresIt(string $scope, string $verb, array $threads, array $authentication, bool $added): void
    {
        $option = $scope === 'slave' ? 'opt_slave_thread_option_list' : 'opt_replica_thread_option_list';
        $trace = new DerivationTrace($scope);
        $trace->expand(0, new Production([new Terminal($verb), new Terminal('REPLICA_SYM'), new NonTerminal($option), new NonTerminal('authentication')]), 0);
        $trace->expand(2, new Production(array_map(static fn (string $name): Terminal => new Terminal($name), $threads)), 0);
        $trace->expand(2 + count($threads), new Production(array_map(static fn (string $name): Terminal => new Terminal($name), $authentication)), 0);
        $input = $trace->terminals();
        $rule = new StartRule();
        $result = $rule->rewrite($input);
        self::assertSame([$verb, 'REPLICA_SYM', ...$threads, ...($added ? [',', 'RELAY_THREAD'] : []), ...$authentication], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
        self::assertSame($result, $rule->rewrite($result));
        $ids = array_map(static fn ($terminal): int => $terminal->id, $result->terminals);
        self::assertCount(count($ids), array_unique($ids));
    }

    /**
     * @return iterable<array{string, string, list<string>, list<string>, bool}>
     */
    public static function providerStarts(): iterable
    {
        foreach (['start_replica_stmt', 'slave'] as $scope) {
            foreach (['USER', 'PASSWORD', 'DEFAULT_AUTH_SYM', 'PLUGIN_DIR_SYM'] as $auth) {
                yield [$scope, 'START_SYM', ['SQL_THREAD'], [$auth, 'EQ', 'TEXT_STRING'], true];
            }
            yield [$scope, 'START_SYM', ['SQL_THREAD'], [], false];
            yield [$scope, 'START_SYM', ['SQL_THREAD', ',', 'RELAY_THREAD'], ['USER'], false];
            yield [$scope, 'START_SYM', ['RELAY_THREAD'], ['USER'], false];
            yield [$scope, 'START_SYM', [], ['USER'], false];
            yield [$scope, 'STOP_SYM', ['SQL_THREAD'], ['USER'], false];
        }
        yield ['ordinary', 'START_SYM', ['SQL_THREAD'], ['USER'], false];
    }
}
