<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Rewrite\LookaheadRule;

#[CoversClass(LookaheadRule::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\PostgreSql\PgLookahead::class)]
#[UsesClass(ProductionOccurrence::class)]
final class LookaheadRuleTest extends TestCase
{
    #[DataProvider('providerOptionNames')]
    public function testRewriteKeepsWithBeforeOptionNamesAsTheGrammarRequires(string $name, string $prefix, string $scope, string $expected): void
    {
        $input = new TerminalSequence([
            new TerminalOccurrence($prefix, 10, [0], ['GrantRoleStmt']),
            new TerminalOccurrence($name, 11, [0, 1, 2], ['GrantRoleStmt', $scope, 'ColLabel']),
            new TerminalOccurrence('OPTION', 12, [0, 1], ['GrantRoleStmt', $scope]),
        ], productions: [new ProductionOccurrence(0, null, 'GrantRoleStmt', 0), new ProductionOccurrence(1, 0, $scope, 0), new ProductionOccurrence(2, 1, 'ColLabel', 0)]);
        $result = (new LookaheadRule())->rewrite($input);
        self::assertSame($expected, $result->terminals[1]->name);
        self::assertSame($input->terminals[1]->id, $result->terminals[1]->id);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result, (new LookaheadRule())->rewrite($result));
    }

    /**
     * @return list<array{string, string, string, string}>
     */
    public static function providerOptionNames(): array
    {
        return [['ORDINALITY', 'WITH', 'grant_role_opt', 'IDENT'], ['TIME', 'WITH_LA', 'grant_role_opt', 'IDENT'], ['IDENT', 'WITH', 'grant_role_opt', 'IDENT'], ['TIME', ',', 'grant_role_opt', 'TIME'], ['ORDINALITY', 'WITH', 'ordinary', 'ORDINALITY']];
    }

    public function testRewriteResolvesAliasesFromTheActualFollowersAndRecordsChanges(): void
    {
        $input = TerminalSequence::fromNames(['WITH_LA', 'IDENT', 'WITH', 'ORDINALITY']);
        $result = (new LookaheadRule())->rewrite($input);
        self::assertSame(['WITH', 'IDENT', 'WITH_LA', 'ORDINALITY'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
        self::assertCount(2, $result->rewrites);
        self::assertSame($result, (new LookaheadRule())->rewrite($result));
    }
}
