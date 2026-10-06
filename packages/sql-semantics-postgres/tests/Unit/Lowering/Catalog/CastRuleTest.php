<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Catalog\CastRule::class)]
#[Medium]
final class CastRuleTest extends TestCase
{
    public function testStatementLowersDropCast(): void
    {
        self::assertSame('DROP CAST (a AS b) RESTRICT', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DROP CAST (a AS b) RESTRICT')->toString());
    }

    public function testContextOfNoContext(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE CAST (a AS b) WITH INOUT');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\CastRule($lowering);
        self::assertNull($rule->context($tree->find('cast_context')[0]));
    }

    public function testFunctionsKeepsTheOrder(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TRANSFORM FOR t LANGUAGE l (TO SQL WITH FUNCTION a(internal), FROM SQL WITH FUNCTION b(internal))');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\CastRule($lowering);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast\TransformDirection::ToSql, $rule->functions($lowering->productions->form($tree->find('transform_element_list')[0]))[0]->direction);
    }
}
