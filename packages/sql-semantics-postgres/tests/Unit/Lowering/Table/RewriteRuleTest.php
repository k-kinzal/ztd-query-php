<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Table\RewriteRule::class)]
#[Medium]
final class RewriteRuleTest extends TestCase
{
    public function testRuleLowersAlso(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE RULE r AS ON INSERT TO t DO ALSO NOTHING');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\RewriteRule($lowering))->rule($tree->find('RuleStmt')[0]);
        self::assertSame(false, $value->instead);
    }

    public function testActionsDropsEmptyStatements(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE RULE r AS ON INSERT TO t DO (; SELECT 1;)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\RewriteRule($lowering))->actions($tree->find('RuleActionList')[0]);
        self::assertSame([
          0 => 1,
          1 => true,
        ], [count($value[0]), $value[1]]);
    }

    public function testActionLowersASelection(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE RULE r AS ON INSERT TO t DO SELECT 1');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\RewriteRule($lowering))->action($tree->find('RuleActionStmt')[0]);
        self::assertSame(true, $value instanceof \SqlSemantics\Statement\Query);
    }
}
