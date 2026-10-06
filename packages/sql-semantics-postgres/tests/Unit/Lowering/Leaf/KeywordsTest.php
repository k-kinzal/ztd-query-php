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
use SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Keywords;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;

#[CoversClass(Keywords::class)]
#[Small]
final class KeywordsTest extends TestCase
{
    public function testWordFoldsTheKeywordToLowerCase(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 AS Select');
        self::assertSame('select', (new Keywords($lowering))->word($tree->find('reserved_keyword')[0]));
    }

    public function testWordReportsANodeThatIsNotAKeywordCategory(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 AS x');
        $this->expectExceptionMessage('No semantic rule is implemented for: ColLabel: IDENT');
        (new Keywords($lowering))->word($tree->find('ColLabel')[0]);
    }
}
