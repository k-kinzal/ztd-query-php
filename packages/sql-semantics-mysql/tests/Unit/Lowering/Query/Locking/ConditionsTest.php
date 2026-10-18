<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Locking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\Query\Locking\Conditions;
use SqlSemantics\Platform\MySql\Lowering\Query\Locking\Scopes;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;

#[CoversClass(Conditions::class)]
#[CoversClass(Scopes::class)]
#[CoversClass(\SqlSemantics\Platform\MySql\Lowering\Query\Locking\ReachedScopes::class)]
#[Medium]
final class ConditionsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?MisuseRule}>
     */
    public static function providerScopes(): iterable
    {
        yield 'nested table' => ['(TABLE missing FOR UPDATE) FOR UPDATE', MisuseRule::RepeatedLockedTable];
        yield 'deferred CTE' => ['WITH c AS (SELECT ROW(1,2) LIKE 1) (TABLE missing FOR UPDATE) FOR UPDATE', MisuseRule::RepeatedLockedTable];
        yield 'deferred item' => ['SELECT ROW(1,2) LIKE 1 FROM missing FOR UPDATE FOR SHARE', MisuseRule::RepeatedLockedTable];
        yield 'missing target' => ['SELECT ROW(1,2) LIKE 1 FROM missing FOR UPDATE OF absent', MisuseRule::UnknownLockedTable];
        yield 'no input' => ['SELECT 1 WHERE ROW(1,2) LIKE 1 FOR UPDATE OF absent', MisuseRule::UnknownLockedTable];
        yield 'common table' => ['WITH c AS (SELECT 1) SELECT 1 FROM c FOR UPDATE FOR SHARE', null];
        yield 'outer common table' => ['WITH c AS (SELECT 1) SELECT (SELECT 1 FROM c FOR UPDATE FOR SHARE)', null];
        yield 'named common table' => ['WITH c AS (SELECT 1) SELECT 1 FROM c FOR UPDATE OF c FOR SHARE OF c', MisuseRule::RepeatedLockedTable];
        yield 'distinct targets' => ['SELECT 1 FROM a,b FOR UPDATE OF a FOR SHARE OF b', null];
        yield 'last set operand' => ['TABLE a UNION TABLE b FOR UPDATE OF a', MisuseRule::UnknownLockedTable];
        yield 'priority' => ['SELECT HIGH_PRIORITY 1 FROM a UNION SELECT 1 FROM b FOR UPDATE', MisuseRule::RepeatedLockedTable];
        yield 'unused definition' => ['WITH c AS (SELECT 1 FROM absent FOR UPDATE FOR SHARE) SELECT 1', null];
        yield 'used definition' => ['WITH c AS (SELECT 1 FROM absent FOR UPDATE FOR SHARE) SELECT * FROM c', MisuseRule::RepeatedLockedTable];
        yield 'deferred input' => ['SELECT 1 FROM (SELECT ROW(1,2)) AS d FOR UPDATE OF d', MisuseRule::RepeatedLockedTable];
        yield 'database qualification needs session' => ['SELECT 1 FROM a FOR UPDATE OF d.a', null];
    }

    #[DataProvider('providerScopes')]
    public function testCompletedChecksLockTargetsWithoutInventingScalarProjections(string $sql, ?MisuseRule $expected): void
    {
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));
        $problems = array_values(array_filter(array_column($notices, 1), static fn ($notice): bool => $notice instanceof ParseFailure && $notice->problem instanceof Misuse));
        self::assertSame($expected === null ? [] : [$expected], array_map(static fn (ParseFailure $notice): ?MisuseRule => $notice->problem instanceof Misuse ? $notice->problem->rule : null, $problems));
    }
    public function testScopeDoesNotRequireProjectionOperandsToLower(): void
    {
        $platform = new \SqlSemantics\Platform\MySql\Platform();
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $lowering = new \SqlSemantics\Platform\MySql\Lowering\Lowering($platform->productions($profile), new \SqlSemantics\Lowering\Leaves(), $profile);
        $tree = (new MySqlParser('mysql-8.4.7'))->parse('SELECT ROW(1,2) FROM t FOR UPDATE FOR SHARE');
        self::assertSame(MisuseRule::RepeatedLockedTable, (new Conditions())->scope($tree->find('select_stmt')[0], $lowering, [])?->rule);
    }

}
