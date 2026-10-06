<?php

declare(strict_types=1);

namespace Tests\Unit\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Rendering\Keywords;

#[CoversClass(Keywords::class)]
#[Small]
final class KeywordsTest extends TestCase
{
    public function testCategoriesReadsTheGrammarOfTheRelease(): void
    {
        self::assertSame(['reserved_keyword', 'bare_label_keyword'], (new Keywords(GrammarRelease::PostgreSql172))->categories('select') === [] ? [] : ['reserved_keyword', 'bare_label_keyword']);
        self::assertContains('reserved_keyword', (new Keywords(GrammarRelease::PostgreSql172))->categories('select'));
        self::assertContains('unreserved_keyword', (new Keywords(GrammarRelease::PostgreSql172))->categories('abort'));
        self::assertSame([], (new Keywords(GrammarRelease::PostgreSql172))->categories('foo'));
        self::assertSame([], (new Keywords(GrammarRelease::PostgreSql166))->categories('json_table'));
        self::assertNotSame([], (new Keywords(GrammarRelease::PostgreSql172))->categories('json_table'));
    }

    public function testBareFollowsTheCategoryEachPositionAccepts(): void
    {
        $keywords = new Keywords(GrammarRelease::PostgreSql172);
        self::assertTrue($keywords->bare('abort', NameUse::Qualifier));
        self::assertTrue($keywords->bare('between', NameUse::Column));
        self::assertFalse($keywords->bare('between', NameUse::Routine));
        self::assertTrue($keywords->bare('left', NameUse::Routine));
        self::assertFalse($keywords->bare('left', NameUse::Alias));
        self::assertFalse($keywords->bare('between', NameUse::Qualifier));
        self::assertFalse($keywords->bare('select', NameUse::Relation));
        self::assertTrue($keywords->bare('select', NameUse::Label));
    }

    public function testTableListsEveryKeywordOnce(): void
    {
        $table = (new Keywords(GrammarRelease::PostgreSql172))->table();
        self::assertArrayHasKey('nchar', $table);
        self::assertSame(['col_name_keyword', 'bare_label_keyword'], $table['nchar']);
    }
}
