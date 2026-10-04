<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Catalog\ForeignDataRule::class)]
#[Medium]
final class ForeignDataRuleTest extends TestCase
{
    public function testStatementLowersImportForeignSchema(): void
    {
        self::assertSame('IMPORT FOREIGN SCHEMA r FROM SERVER s INTO l', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('IMPORT FOREIGN SCHEMA r FROM SERVER s INTO l')->toString());
    }

    public function testFunctionsLowersEveryClause(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE FOREIGN DATA WRAPPER w HANDLER h NO VALIDATOR');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\ForeignDataRule($lowering);
        self::assertCount(2, $rule->functions($tree->find('opt_fdw_options')[0]));
    }

    public function testTypeOfAServer(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE SERVER s TYPE \'x\' FOREIGN DATA WRAPPER w');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\ForeignDataRule($lowering);
        self::assertSame('x', $rule->type($tree->find('opt_type')[0])?->value);
    }

    public function testVersionOfNull(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER SERVER s VERSION NULL');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\ForeignDataRule($lowering);
        self::assertNull($rule->version($tree->find('foreign_server_version')[0])?->version);
    }

    public function testUserOfTheKeyword(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('DROP USER MAPPING FOR USER SERVER s');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\ForeignDataRule($lowering);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\ForeignData\MappingUser::User, $rule->user($tree->find('auth_ident')[0]));
    }

    public function testRestrictionOfNone(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('IMPORT FOREIGN SCHEMA r FROM SERVER s INTO l');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\ForeignDataRule($lowering);
        self::assertNull($rule->restriction($tree->find('import_qualification')[0]));
    }
}
