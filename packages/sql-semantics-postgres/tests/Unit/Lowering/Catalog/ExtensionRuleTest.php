<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Catalog\ExtensionRule::class)]
#[Medium]
final class ExtensionRuleTest extends TestCase
{
    public function testStatementLowersCreateLanguage(): void
    {
        self::assertSame('CREATE LANGUAGE l HANDLER h', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE LANGUAGE l HANDLER h')->toString());
    }

    public function testContentsLowersAnAggregate(): void
    {
        self::assertSame('ALTER EXTENSION e ADD AGGREGATE agg (*)', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER EXTENSION e ADD AGGREGATE agg(*)')->toString());
    }

    public function testOptionsLowersEveryItem(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE EXTENSION e SCHEMA s VERSION \'1\' CASCADE');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\ExtensionRule($lowering);
        self::assertCount(3, $rule->options($tree->find('create_extension_opt_list')[0]));
    }

    public function testVersionsLowersEveryTo(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER EXTENSION e UPDATE TO a TO \'b\'');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\ExtensionRule($lowering);
        self::assertCount(2, $rule->versions($tree->find('alter_extension_opt_list')[0]));
    }

    public function testHandlerLowersADottedName(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE ACCESS METHOD m TYPE INDEX HANDLER s.h');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\ExtensionRule($lowering);
        self::assertCount(2, $rule->handler($tree->find('handler_name')[0])->parts);
    }

    public function testInlineOfNoHandler(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE LANGUAGE l HANDLER h');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\ExtensionRule($lowering);
        self::assertNull($rule->inline($tree->find('opt_inline_handler')[0]));
    }

    public function testValidatorOfTheNoForm(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE LANGUAGE l HANDLER h NO VALIDATOR');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\ExtensionRule($lowering);
        self::assertNull($rule->validator($tree->find('opt_validator')[0])?->function);
    }

    public function testAccessMethodKindOfAnIndexMethod(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE ACCESS METHOD m TYPE INDEX HANDLER h');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\ExtensionRule($lowering);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\AccessMethodKind::Index, $rule->accessMethodKind($tree->find('am_type')[0]));
    }
}
