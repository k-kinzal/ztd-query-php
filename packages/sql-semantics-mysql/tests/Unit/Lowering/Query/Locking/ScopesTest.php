<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Locking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\Locking\Scopes;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Scopes::class)]
#[Medium]
final class ScopesTest extends TestCase
{
    public function testReadKeepsTheLastSetOperandAndNestedLocks(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $lowering = new Lowering((new Platform())->productions($profile), new Leaves(), $profile);
        $tree = (new MySqlParser('mysql-8.4.7'))->parse('(TABLE a UNION TABLE b FOR UPDATE) FOR SHARE');
        [$terms, $clauses] = (new Scopes())->read($tree->find('select_stmt')[0], $lowering, []);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable::class, $terms[0]);
        self::assertSame('b', $terms[0]->name()->name->value);
        self::assertCount(2, $clauses);
    }

    public function testCommonPreservesRecursiveNames(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $lowering = new Lowering((new Platform())->productions($profile), new Leaves(), $profile);
        $tree = (new MySqlParser('mysql-8.4.7'))->parse('WITH RECURSIVE c AS (SELECT 1), d AS (SELECT 2) SELECT 1');
        self::assertEquals([new Name('c'), new Name('d')], (new Scopes())->common($tree->find('query_expression')[0], $lowering));
    }

    public function testLeadingReadsPriorityFromTheFirstSetOperand(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $lowering = new Lowering((new Platform())->productions($profile), new Leaves(), $profile);
        $tree = (new MySqlParser('mysql-8.4.7'))->parse('SELECT HIGH_PRIORITY 1 FROM a UNION SELECT 1 FROM b');
        self::assertSame([\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::HighPriority], (new Scopes())->leading($tree->find('select_stmt')[0], $lowering));
    }

}
