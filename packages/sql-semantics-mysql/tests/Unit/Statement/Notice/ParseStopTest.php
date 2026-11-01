<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Notice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseStop;

#[CoversClass(ParseStop::class)]
#[Medium]
final class ParseStopTest extends TestCase
{
    public function testProgramStatementCompletionStopsBeforeTheNextOperand(): void
    {
        $sql = "CREATE PROCEDURE d.p() BEGIN SELECT CAST(1 AT LOCAL AS DATE); SIGNAL SQLSTATE '00000'; END";
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));

        self::assertCount(2, $notices);
        self::assertInstanceOf(ParseFailure::class, $notices[0][1]);
        self::assertFalse($notices[0][1]->aborts);
        self::assertSame(ParseStop::ProgramStatement, $notices[1][1]);
        self::assertSame($notices[0][0], $notices[1][0]);
    }
}
