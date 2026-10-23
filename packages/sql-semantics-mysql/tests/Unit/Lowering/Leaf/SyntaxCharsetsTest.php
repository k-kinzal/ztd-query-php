<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\Leaf\SyntaxCharsets;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;

#[CoversClass(SyntaxCharsets::class)]
#[Medium]
final class SyntaxCharsetsTest extends TestCase
{
    #[TestWith(['SET NAMES utf8 COLLATE'])]
    #[TestWith(['SELECT CONVERT(1 USING utf8'])]
    #[TestWith(['SELECT CONVERT(1 USING utf8),?'])]
    #[TestWith(['SELECT CHAR(65 USING utf8'])]
    #[TestWith(['SELECT CHAR(65 USING utf8),?'])]
    public function testCompletedRetainsTheAliasBeforeTheEnclosingConstruct(string $sql): void
    {
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));
        self::assertSame([Deprecated::Utf8Alias], array_column($notices, 1));
    }
    #[TestWith(['SET NAMES missing_charset COLLATE'])]
    #[TestWith(['SET CHARACTER SET missing_charset, @v='])]
    #[TestWith(['SET NAMES latin1 COLLATE missing_collation, @v='])]
    public function testCompletedRetainsLookupFailuresBeforeMalformedLaterInput(string $sql): void
    {
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));
        self::assertCount(1, $notices);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $notices[0][1]);
        self::assertTrue($notices[0][1]->aborts);
    }

}
