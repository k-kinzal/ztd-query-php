<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Catalog\TextSearchRule::class)]
#[Medium]
final class TextSearchRuleTest extends TestCase
{
    public function testStatementLowersAConfigurationChange(): void
    {
        self::assertSame('ALTER TEXT SEARCH CONFIGURATION c ALTER MAPPING FOR a REPLACE b WITH c', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TEXT SEARCH CONFIGURATION c ALTER MAPPING FOR a REPLACE b WITH c')->toString());
    }

    public function testWithAcceptsTheMandatoryWith(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER TEXT SEARCH CONFIGURATION c ADD MAPPING FOR a WITH b');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\TextSearchRule($lowering);
        $rule->with($lowering->productions->form($tree->find('AlterTSConfigurationStmt')[0]));
        self::assertCount(1, $tree->find('any_with'));
    }
}
