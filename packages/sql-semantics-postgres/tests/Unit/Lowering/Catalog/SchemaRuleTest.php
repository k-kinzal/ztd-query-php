<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Catalog\SchemaRule::class)]
#[Medium]
final class SchemaRuleTest extends TestCase
{
    public function testStatementLowersANamedSchema(): void
    {
        self::assertSame('CREATE SCHEMA IF NOT EXISTS s', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE SCHEMA IF NOT EXISTS s')->toString());
    }

    public function testElementsLowersEveryElement(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE SCHEMA s CREATE TABLE t (a int4) CREATE VIEW v AS SELECT 1');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\SchemaRule($lowering);
        self::assertCount(2, $rule->elements($tree->find('OptSchemaEltList')[0]));
    }
}
