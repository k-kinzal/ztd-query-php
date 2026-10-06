<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Catalog\PublicationRule::class)]
#[Medium]
final class PublicationRuleTest extends TestCase
{
    public function testStatementLowersAlterPublication(): void
    {
        self::assertSame('ALTER PUBLICATION p SET TABLE t', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER PUBLICATION p SET TABLE t')->toString());
    }

    public function testObjectsReadsAContinuedSchema(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE PUBLICATION p FOR TABLES IN SCHEMA a, b');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\PublicationRule($lowering);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\PublicationSchema::class, $rule->objects($tree->find('pub_obj_list')[0])[1]);
    }

    public function testObjectLowersCurrentSchema(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE PUBLICATION p FOR TABLES IN SCHEMA CURRENT_SCHEMA');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\PublicationRule($lowering);
        self::assertTrue($rule->object($lowering->productions->form($tree->find('PublicationObjSpec')[0]), null)->introduced());
    }

    public function testContinuedReadsATableAfterATable(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE PUBLICATION p FOR TABLE a, b');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\PublicationRule($lowering);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\PublicationTable::class, $rule->continued($lowering->productions->form($tree->find('PublicationObjSpec')[1]), null));
    }

    public function testStarReadsTheTrailingStar(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE PUBLICATION p FOR TABLE t *');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\PublicationRule($lowering);
        self::assertTrue($rule->star($tree->find('relation_expr')[0]));
    }
}
