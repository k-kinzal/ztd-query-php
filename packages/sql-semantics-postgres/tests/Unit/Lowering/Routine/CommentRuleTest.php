<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Lowering\Routine\CommentRule;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;

#[CoversClass(CommentRule::class)]
#[Small]
final class CommentRuleTest extends TestCase
{
    public function testCommentLowersTheObject(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("COMMENT ON CONSTRAINT c ON DOMAIN d IS 'x'");
        self::assertSame(ObjectKind::DomainConstraint, (new CommentRule($lowering))->comment($tree->find('CommentStmt')[0])->kind);
    }

    public function testLabelLowersTheProvider(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SECURITY LABEL FOR p ON LARGE OBJECT 5 IS NULL');
        $label = (new CommentRule($lowering))->label($tree->find('SecLabelStmt')[0]);
        self::assertSame([ObjectKind::LargeObject, null], [$label->kind, $label->label]);
        self::assertInstanceOf(Word::class, $label->provider);
    }

    public function testTextLowersNull(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('COMMENT ON SCHEMA s IS NULL');
        self::assertNull((new CommentRule($lowering))->text($tree->find('comment_text')[0]));
    }

    public function testProviderLowersNoProvider(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SECURITY LABEL ON SCHEMA s IS NULL');
        self::assertNull((new CommentRule($lowering))->provider($tree->find('opt_provider')[0]));
    }
}
