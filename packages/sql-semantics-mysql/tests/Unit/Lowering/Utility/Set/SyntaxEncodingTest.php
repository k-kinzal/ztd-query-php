<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Lowering\Utility\Set\SyntaxEncoding;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

#[CoversClass(SyntaxEncoding::class)]
#[Medium]
final class SyntaxEncodingTest extends TestCase
{
    public function testCompletedRetainsTheEncodingBeforeMalformedLaterInput(): void
    {
        $sql = 'SET NAMES latin1 COLLATE missing_collation, @v=';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));
        self::assertCount(1, $notices);
        self::assertInstanceOf(ParseFailure::class, $notices[0][1]);
        self::assertSame("Unknown collation: 'missing_collation'", $notices[0][1]->message());
        self::assertTrue($notices[0][1]->aborts);
    }
}
