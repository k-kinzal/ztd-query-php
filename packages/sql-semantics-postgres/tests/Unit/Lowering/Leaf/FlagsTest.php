<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Flags;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Option\AddOrDrop;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;
use SqlSemantics\Platform\PostgreSql\Statement\Option\NormalForm;
use SqlSemantics\Platform\PostgreSql\Statement\Option\XmlOption;

#[CoversClass(Flags::class)]
#[Small]
final class FlagsTest extends TestCase
{
    public function testPresentTellsWhetherTheKeywordsAreWritten(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE OR REPLACE FUNCTION f() RETURNS int AS \'x\'');
        self::assertTrue($lowering->flags->present($tree->find('opt_or_replace')[0]));
        self::assertFalse($lowering->flags->present((new PostgreSqlParser('pg-17.2'))->parse('TRUNCATE t')->find('opt_table')[0]));
    }

    public function testPresentReportsAProductionThatIsNotAFlag(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1');
        $this->expectExceptionMessage('No semantic rule is implemented for: target_el: a_expr');
        $lowering->flags->present($tree->find('target_el')[0]);
    }

    public function testDropBehaviorLowersTheChoiceOrNothing(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP TABLE t CASCADE');
        self::assertSame(DropBehavior::Cascade, $lowering->flags->dropBehavior($tree->find('opt_drop_behavior')[0]));
        self::assertNull($lowering->flags->dropBehavior((new PostgreSqlParser('pg-17.2'))->parse('DROP TABLE t')->find('opt_drop_behavior')[0]));
    }

    public function testAddOrDropLowersTheChoice(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('ALTER GROUP g DROP USER u');
        self::assertSame(AddOrDrop::Drop, $lowering->flags->addOrDrop($tree->find('add_drop')[0]));
    }

    public function testXmlOptionLowersTheChoice(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SET XML OPTION CONTENT');
        self::assertSame(XmlOption::Content, $lowering->flags->xmlOption($tree->find('document_or_content')[0]));
    }

    public function testNormalFormLowersTheForm(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT normalize(a, NFKC)');
        self::assertSame(NormalForm::Nfkc, $lowering->flags->normalForm($tree->find('unicode_normal_form')[0]));
    }
}
