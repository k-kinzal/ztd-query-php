<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Catalog\DatabaseRule::class)]
#[Medium]
final class DatabaseRuleTest extends TestCase
{
    public function testStatementLowersCreateDatabase(): void
    {
        self::assertSame('CREATE DATABASE d', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DATABASE d')->toString());
    }

    public function testOptionsLowersEveryItem(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE DATABASE d OWNER a ENCODING b');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\DatabaseRule($lowering);
        self::assertCount(2, $rule->options($tree->find('createdb_opt_list')[0]));
    }

    public function testOptionLowersDefault(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE DATABASE d OWNER DEFAULT');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\DatabaseRule($lowering);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\DefaultSetting::Default, $rule->option($tree->find('createdb_opt_item')[0])->value);
    }

    public function testEqualAcceptsTheNoiseSign(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE DATABASE d OWNER = a');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\DatabaseRule($lowering);
        $rule->equal($tree->find('opt_equal')[0]);
        self::assertSame('opt_equal', $tree->find('opt_equal')[0]->name);
    }

    public function testDropOptionsLowersForce(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('DROP DATABASE d (FORCE)');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\DatabaseRule($lowering);
        self::assertSame([\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\DropDatabaseOption::Force], $rule->dropOptions($tree->find('drop_option_list')[0]));
    }

    public function testOwnerOfATablespace(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLESPACE t OWNER CURRENT_USER LOCATION \'x\'');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\DatabaseRule($lowering);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind::CurrentUser, $rule->owner($tree->find('OptTableSpaceOwner')[0])?->kind);
    }
}
